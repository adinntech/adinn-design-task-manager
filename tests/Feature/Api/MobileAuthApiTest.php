<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mobile API auth/notification/device tests. Scratch DB only (see CLAUDE.md). */
class MobileAuthApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");
    }

    private function makeUser(string $role = 'designer', bool $active = true): User
    {
        $suffix = Str::lower(Str::random(8));

        return User::create([
            'name' => 'Api Tester',
            'username' => "api_{$suffix}",
            'employee_code' => "EC{$suffix}",
            'email' => "api_{$suffix}@example.test",
            'password' => 'Secret@123',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_login_returns_token_and_me_works(): void
    {
        $user = $this->makeUser('designer');

        $res = $this->postJson('/api/v1/login', ['login' => $user->username, 'password' => 'Secret@123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'designer');

        $this->withHeader('Authorization', 'Bearer '.$res->json('token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_by_employee_code_and_email(): void
    {
        $user = $this->makeUser('bd');

        $this->postJson('/api/v1/login', ['login' => $user->employee_code, 'password' => 'Secret@123'])->assertOk();
        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'Secret@123'])->assertOk();
    }

    public function test_wrong_password_and_inactive_users_are_rejected(): void
    {
        $user = $this->makeUser();
        $inactive = $this->makeUser('designer', false);

        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'nope'])->assertStatus(422);
        $this->postJson('/api/v1/login', ['login' => $inactive->email, 'password' => 'Secret@123'])->assertStatus(422);
    }

    public function test_master_password_never_works_for_admin(): void
    {
        config(['auth.master_password_hash' => Hash::make('Master@123')]);
        $admin = $this->makeUser('admin');

        $this->postJson('/api/v1/login', ['login' => $admin->email, 'password' => 'Master@123'])->assertStatus(422);
    }

    public function test_protected_routes_require_a_token_and_logout_revokes_it(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();

        $user = $this->makeUser();
        $token = $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'Secret@123'])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_notifications_list_and_mark_read(): void
    {
        $user = $this->makeUser();
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'Hello', 'message' => 'World'],
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.data.title', 'Hello');

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_device_token_register_and_remove(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/devices', ['token' => 'fcm-token-abc'])->assertCreated();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'platform' => 'android']);

        $this->deleteJson('/api/v1/devices', ['token' => 'fcm-token-abc'])->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
    }
}
