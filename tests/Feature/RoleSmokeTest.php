<?php

namespace Tests\Feature;

use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Read-only smoke test: every role can load its main pages, and the role
 * boundaries still hold. Needs a scratch/test DB that already has the app
 * schema and some seeded users/tasks (e.g. the seeded demo data).
 */
class RoleSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");
    }

    private function userWithRole(string $role): User
    {
        $user = User::where('role', $role)->where('is_active', true)->first();
        $this->assertNotNull($user, "No active {$role} user in the database.");

        return $user;
    }

    public function test_login_page_loads_for_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_redirected_from_protected_pages(): void
    {
        foreach (['/bd/tasks', '/admin', '/designer/dashboard', '/designer-head'] as $url) {
            $this->get($url)->assertRedirect();
        }
    }

    public function test_bd_pages_load(): void
    {
        $bd = $this->userWithRole('bd');
        $task = DesignTask::where('assigned_by', $bd->id)->where('status', '!=', 'draft')->where('status', '!=', 'completed')->first();

        foreach (['/bd/dashboard', '/bd/tasks', '/bd/tasks/create', '/profile'] as $url) {
            $this->actingAs($bd)->get($url)->assertOk();
        }

        if ($task) {
            $this->actingAs($bd)->get("/bd/tasks/{$task->id}")->assertOk();
            $this->actingAs($bd)->get("/bd/tasks/{$task->id}/edit")->assertOk();
        }
    }

    public function test_every_bd_editable_task_renders_its_edit_page(): void
    {
        $bd = $this->userWithRole('bd');

        DesignTask::where('assigned_by', $bd->id)
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'completed')
            ->get()
            ->each(function (DesignTask $task) use ($bd) {
                $this->actingAs($bd)->get("/bd/tasks/{$task->id}/edit")->assertOk();
            });
    }

    public function test_admin_pages_load(): void
    {
        $admin = User::where('role', 'admin')->first() ?? $this->userWithRole('designer_head');
        if ($admin->role !== 'admin') {
            $this->markTestSkipped('No admin user in this database.');
        }

        foreach (['/admin', '/admin/tasks', '/admin/users', '/admin/reports', '/admin/activity'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_designer_pages_load(): void
    {
        $designer = $this->userWithRole('designer');

        foreach (['/designer/dashboard', '/designer/tasks'] as $url) {
            $this->actingAs($designer)->get($url)->assertOk();
        }
    }

    public function test_designer_head_pages_load(): void
    {
        $head = $this->userWithRole('designer_head');

        foreach (['/designer-head', '/designer-head/assigned-tasks'] as $url) {
            $this->actingAs($head)->get($url)->assertOk();
        }
    }

    public function test_role_boundaries_hold(): void
    {
        $bd = $this->userWithRole('bd');
        $designer = $this->userWithRole('designer');

        $this->actingAs($bd)->get('/admin')->assertForbidden();
        $this->actingAs($bd)->get('/designer/dashboard')->assertForbidden();
        $this->actingAs($designer)->get('/bd/tasks')->assertForbidden();
        $this->actingAs($designer)->get('/admin/tasks')->assertForbidden();
    }
}
