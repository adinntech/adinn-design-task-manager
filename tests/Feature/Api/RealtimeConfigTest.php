<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** GET /api/v1/realtime hands the app its Reverb settings (or says realtime is off). Scratch DB only. */
class RealtimeConfigTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        $this->user = User::create([
            'name' => 'Rt User', 'email' => 'rt'.uniqid().'@example.test', 'password' => 'Password@123', 'role' => 'designer', 'is_active' => true,
        ]);
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/v1/realtime')->assertUnauthorized();
    }

    public function test_returns_reverb_settings_and_the_users_private_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'app-key',
            'broadcasting.connections.reverb.options.host' => 'ws.example.test',
            'broadcasting.connections.reverb.options.port' => '8081',
            'broadcasting.connections.reverb.options.scheme' => 'https',
        ]);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/realtime')
            ->assertOk()
            ->assertExactJson([
                'enabled' => true,
                'key' => 'app-key',
                'host' => 'ws.example.test',
                'port' => 8081,
                'scheme' => 'https',
                'channel' => 'private-App.Models.User.'.$this->user->id,
                'event' => 'Illuminate\Notifications\Events\BroadcastNotificationCreated',
            ]);
    }

    public function test_disabled_when_not_reverb_or_unconfigured(): void
    {
        $this->actingAs($this->user, 'sanctum');

        config(['broadcasting.default' => 'log', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.options.host' => 'h']);
        $this->getJson('/api/v1/realtime')->assertOk()->assertExactJson(['enabled' => false]);

        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => null]);
        $this->getJson('/api/v1/realtime')->assertOk()->assertExactJson(['enabled' => false]);

        config(['broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.options.host' => '']);
        $this->getJson('/api/v1/realtime')->assertOk()->assertExactJson(['enabled' => false]);
    }

    /**
     * Needs the real Reverb driver to sign channel auth. The documented scratch run uses BROADCAST_CONNECTION=log,
     * so this is skipped there; run it with
     * BROADCAST_CONNECTION=reverb REVERB_APP_KEY=k REVERB_APP_SECRET=s REVERB_APP_ID=1 REVERB_HOST=localhost.
     */
    public function test_broadcast_auth_accepts_a_bearer_token_for_own_channel_only(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            $this->markTestSkipped('Needs BROADCAST_CONNECTION=reverb to sign channel auth.');
        }

        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
        $this->actingAs($this->user, 'sanctum');

        $own = $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$this->user->id]);
        $own->assertOk();
        $this->assertStringStartsWith('k:', $own->json('auth'));

        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.($this->user->id + 1)])->assertForbidden();
    }
}
