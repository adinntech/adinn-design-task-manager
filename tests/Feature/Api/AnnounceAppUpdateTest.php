<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** php artisan app:announce-update. Scratch DB only. */
class AnnounceAppUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        config([
            'fcm.project_id' => 'proj-1',
            'fcm.credentials_json' => json_encode(['client_email' => 'svc@proj-1.iam.gserviceaccount.com', 'private_key' => 'x', 'project_id' => 'proj-1']),
            'webpush.roles' => ['designer', 'bd', 'designer_head', 'admin'],
        ]);
        Cache::put('fcm:access-token:'.md5('svc@proj-1.iam.gserviceaccount.com'), 'token', 600);
    }

    private function deviceFor(string $role): DeviceToken
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => 'U'.$n, 'email' => "aau{$n}@example.test", 'password' => 'Password@123', 'role' => $role, 'is_active' => true]);

        $token = "tok-{$n}-".uniqid();

        return DeviceToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'token' => $token, 'platform' => 'android']);
    }

    public function test_it_pushes_an_update_notice_to_every_device(): void
    {
        config(['app_update.android' => ['min_build' => 0, 'latest_build' => 4, 'latest_version' => '1.3.0', 'url' => 'https://x.test/a.apk', 'notes' => 'n']]);
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200)]);
        $this->deviceFor('designer');
        $this->deviceFor('bd');

        $this->artisan('app:announce-update')->assertSuccessful();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'proj-1/messages:send')
            && $r['message']['notification']['title'] === 'Update available'
            && $r['message']['data']['type'] === 'app_update'
            && $r['message']['data']['build'] === '4');
        $this->assertGreaterThanOrEqual(2, count(Http::recorded()));
    }

    public function test_dry_run_sends_nothing(): void
    {
        config(['app_update.android' => ['min_build' => 0, 'latest_build' => 4, 'latest_version' => null, 'url' => null, 'notes' => null]]);
        Http::fake();
        $this->deviceFor('designer');

        $this->artisan('app:announce-update --dry-run')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_nothing_is_announced_without_a_release(): void
    {
        config(['app_update.android' => ['min_build' => 0, 'latest_build' => 0, 'latest_version' => null, 'url' => null, 'notes' => null]]);
        Http::fake();

        $this->artisan('app:announce-update')->assertFailed();

        Http::assertNothingSent();
    }
}
