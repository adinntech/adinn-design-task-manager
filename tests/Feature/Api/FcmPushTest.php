<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** FCM sender: OAuth exchange, payload shape, dead-token pruning, opt-outs. Scratch DB only. */
class FcmPushTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        $pem = $this->generatePrivateKey();

        config([
            'fcm.project_id' => null,
            'fcm.credentials' => null,
            'fcm.credentials_json' => json_encode([
                'project_id' => 'adinn-test', 'client_email' => 'svc@adinn-test.iam.gserviceaccount.com',
                'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
        ]);
        Cache::forget('fcm:access-token:'.md5('svc@adinn-test.iam.gserviceaccount.com'));

        $this->user = User::create([
            'name' => 'Push Designer', 'email' => 'fcm'.uniqid().'@example.test', 'password' => 'Password@123',
            'role' => 'designer', 'is_active' => true,
        ]);
    }

    /** PHP on Windows/XAMPP often has no default openssl.cnf; try the usual locations, else skip. */
    private function generatePrivateKey(): string
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $candidates = [null, getenv('OPENSSL_CONF') ?: null, dirname(PHP_BINARY).'/extras/ssl/openssl.cnf', dirname(PHP_BINARY, 2).'/apache/conf/openssl.cnf'];

        foreach ($candidates as $cnf) {
            if ($cnf !== null && ! is_file($cnf)) {
                continue;
            }

            $key = openssl_pkey_new($cnf ? $options + ['config' => $cnf] : $options);
            if ($key && openssl_pkey_export($key, $pem, null, $cnf ? ['config' => $cnf] : [])) {
                return $pem;
            }
        }

        $this->markTestSkipped('OpenSSL cannot generate a test RSA key here (no usable openssl.cnf).');
    }

    private function device(string $token, ?User $user = null): DeviceToken
    {
        return DeviceToken::create([
            'user_id' => ($user ?? $this->user)->id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'platform' => 'android',
        ]);
    }

    public function test_sends_to_every_device_with_a_signed_oauth_token_and_string_data(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/adinn-test/messages/1']),
        ]);
        $this->device('tok-a');
        $this->device('tok-b');

        $sent = app(FcmService::class)->send($this->user, 'New task', 'DT-1 — Banner', 'https://x/t/1', 'assigned-1', ['task_id' => 7]);

        $this->assertSame(2, $sent);

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'oauth2.googleapis.com')) {
                return false;
            }
            parse_str($r->body(), $form);
            [, $claims] = explode('.', $form['assertion']);
            $claims = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

            return $form['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $claims['iss'] === 'svc@adinn-test.iam.gserviceaccount.com'
                && str_contains($claims['scope'], 'firebase.messaging');
        });

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'fcm.googleapis.com/v1/projects/adinn-test/messages:send')) {
                return false;
            }
            $m = $r->data()['message'];

            return $r->hasHeader('Authorization', 'Bearer ya29.test')
                && $m['notification']['title'] === 'New task'
                && $m['data']['task_id'] === '7'
                && $m['data']['url'] === 'https://x/t/1'
                && in_array($m['token'], ['tok-a', 'tok-b'], true);
        });
    }

    public function test_unregistered_tokens_are_pruned_and_good_ones_kept(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'NOT_FOUND', 'message' => 'gone', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                ->push(['name' => 'ok']),
        ]);
        $this->device('dead');
        $this->device('alive');

        $sent = app(FcmService::class)->send($this->user, 't', 'b');

        $this->assertSame(1, $sent);
        $this->assertSame(1, DeviceToken::where('user_id', $this->user->id)->count());
    }

    public function test_transient_errors_keep_the_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE', 'message' => 'try later']], 503),
        ]);
        $this->device('tok');

        $this->assertSame(0, app(FcmService::class)->send($this->user, 't', 'b'));
        $this->assertSame(1, DeviceToken::where('user_id', $this->user->id)->count());
    }

    public function test_respects_opt_out_missing_config_and_no_devices(): void
    {
        Http::fake();
        $this->device('tok');

        $this->user->update(['web_push_enabled' => false]);
        $this->assertSame(0, app(FcmService::class)->send($this->user->fresh(), 't', 'b'));

        $this->user->update(['web_push_enabled' => true]);
        config(['fcm.credentials_json' => null]);
        $this->assertFalse(app(FcmService::class)->isConfigured());
        $this->assertSame(0, app(FcmService::class)->send($this->user->fresh(), 't', 'b'));

        Http::assertNothingSent();
    }

    public function test_web_push_service_also_triggers_fcm(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
        $this->device('tok');

        app(\App\Services\WebPushService::class)->send($this->user, 'Hello', 'World', null, 'tag', ['task_id' => 3]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'messages:send') && $r->data()['message']['data']['task_id'] === '3');
    }
}
