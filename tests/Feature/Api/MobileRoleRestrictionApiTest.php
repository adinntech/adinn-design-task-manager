<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Only BD and Designer may use the mobile app; Admin and Designer Head are refused. Scratch DB only (see CLAUDE.md). */
class MobileRoleRestrictionApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");
    }

    private function makeUser(string $role): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'name' => 'Role Tester',
            'username' => "role_{$suffix}",
            'employee_code' => "RC{$suffix}",
            'email' => "role_{$suffix}@example.test",
            'password' => 'Secret@123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_bd_and_designer_can_sign_in(): void
    {
        foreach (['bd', 'designer'] as $role) {
            $user = $this->makeUser($role);

            $this->postJson('/api/v1/login', ['login' => $user->username, 'password' => 'Secret@123'])
                ->assertOk()
                ->assertJsonPath('user.role', $role);
        }
    }

    public function test_admin_and_designer_head_cannot_sign_in_and_get_no_token(): void
    {
        foreach (['admin', 'designer_head'] as $role) {
            $user = $this->makeUser($role);

            $this->postJson('/api/v1/login', ['login' => $user->username, 'password' => 'Secret@123'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('login')
                ->assertJsonPath('errors.login.0', config('mobile.blocked_message'))
                ->assertJsonMissing(['token']);

            $this->assertSame(0, $user->tokens()->count(), "{$role} must not be issued a token");
            $this->assertNull($user->fresh()->last_login_at, "{$role} sign-in attempt must not count as a login");
        }
    }

    public function test_a_wrong_password_never_reveals_the_role(): void
    {
        $admin = $this->makeUser('admin');
        $designer = $this->makeUser('designer');

        $adminReply = $this->postJson('/api/v1/login', ['login' => $admin->username, 'password' => 'wrong'])->assertStatus(422);
        $designerReply = $this->postJson('/api/v1/login', ['login' => $designer->username, 'password' => 'wrong'])->assertStatus(422);

        $this->assertSame($designerReply->json('errors.login.0'), $adminReply->json('errors.login.0'));
        $this->assertSame('The provided credentials are incorrect.', $adminReply->json('errors.login.0'));
    }

    public function test_a_token_issued_before_the_restriction_is_revoked_on_its_next_request(): void
    {
        $head = $this->makeUser('designer_head');
        // Issued while Heads were still allowed.
        $token = $head->createToken('mobile')->plainTextToken;
        DeviceToken::create(['user_id' => $head->id, 'token' => 'fcm-token-1', 'token_hash' => hash('sha256', 'fcm-token-1'), 'platform' => 'android', 'last_seen_at' => now()]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'role_not_allowed')
            ->assertJsonPath('message', config('mobile.blocked_message'));

        $this->assertSame(0, $head->tokens()->count(), 'the token is deleted');
        $this->assertSame(0, DeviceToken::where('user_id', $head->id)->count(), 'push registrations are removed');

        // The same token no longer authenticates at all.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_realtime_login_and_every_other_route_is_closed_to_them_too(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['/api/v1/me', '/api/v1/notifications', '/api/v1/realtime', '/api/v1/admin/dashboard'] as $url) {
            $token = $admin->createToken('mobile')->plainTextToken;
            $this->app['auth']->forgetGuards();

            $this->withHeader('Authorization', 'Bearer '.$token)->getJson($url)->assertUnauthorized();
        }
    }

    public function test_bd_and_designer_tokens_keep_working(): void
    {
        foreach (['bd', 'designer'] as $role) {
            $user = $this->makeUser($role);
            $token = $user->createToken('mobile')->plainTextToken;
            $this->app['auth']->forgetGuards();

            $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.role', $role);
        }
    }

    public function test_the_allowed_roles_can_be_widened_by_config_without_code_changes(): void
    {
        config(['mobile.allowed_roles' => ['bd', 'designer', 'designer_head']]);
        $head = $this->makeUser('designer_head');
        $admin = $this->makeUser('admin');

        $this->postJson('/api/v1/login', ['login' => $head->username, 'password' => 'Secret@123'])->assertOk();
        $this->postJson('/api/v1/login', ['login' => $admin->username, 'password' => 'Secret@123'])->assertStatus(422);
    }
}
