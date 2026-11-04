<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Admin mobile API: dashboard, monitor, users, split/swap approvals. Scratch DB only. */
class MobileAdminApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $bd;

    private User $designer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->admin = $this->makeUser('admin');
        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
        $this->other = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n, 'email' => "mad{$role}{$n}@example.test", 'password' => 'Password@123',
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function task(array $o = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(), 'assigned_at' => now(), 'assigned_by' => $this->bd->id, 'task_name' => 'Admin view task',
            'vertical' => 'roadshow', 'task_nature' => 'new_creative_design', 'party_type' => 'client', 'party_name' => 'Acme',
            'contact_person' => 'Jo', 'mobile_number' => '9898989898', 'priority' => 'medium', 'due_at' => now()->addDays(3),
            'designer_id' => $this->designer->id, 'total_creatives' => 3, 'status' => 'review_analysis', 'requirements' => [],
        ], $o));
    }

    private function request(DesignTask $task, string $type): DesignTaskRequest
    {
        return DesignTaskRequest::create([
            'design_task_id' => $task->id, 'request_type' => $type, 'requested_by' => $this->designer->id,
            'overall_status' => 'pending_approval', 'designer_head_status' => 'pending', 'admin_status' => 'pending', 'reason' => 'Because',
            'split_details' => $type === 'split' ? ['creative_count' => 1, 'details' => 'x'] : ['notes' => ''],
        ]);
    }

    public function test_admin_only_access(): void
    {
        foreach (['dashboard', 'tasks', 'users', 'requests'] as $path) {
            $this->actingAs($this->bd, 'sanctum')->getJson("/api/v1/admin/{$path}")->assertForbidden();
        }
        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_dashboard_and_task_monitor(): void
    {
        $task = $this->task(['task_name' => 'Zebra banner']);
        $this->task(['status' => 'draft']);
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonStructure(['stats' => ['total_tasks', 'overdue'], 'pipeline', 'request_stats', 'designer_workload']);

        $ids = collect($this->getJson('/api/v1/admin/tasks?search=Zebra')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$task->id], $ids->all());

        $all = collect($this->getJson('/api/v1/admin/tasks?per_page=50')->json('data'))->pluck('status');
        $this->assertFalse($all->contains('draft'));

        $this->getJson("/api/v1/admin/tasks/{$task->id}")->assertOk()->assertJsonPath('task.id', $task->id);
    }

    public function test_user_list_and_toggle(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/v1/admin/users?role=designer')->assertOk();

        $token = $this->designer->createToken('t');
        $this->patchJson("/api/v1/admin/users/{$this->designer->id}/toggle")->assertOk()->assertJsonPath('user.is_active', false);
        $this->assertSame(0, $this->designer->tokens()->count());

        $this->patchJson("/api/v1/admin/users/{$this->designer->id}/toggle")->assertOk()->assertJsonPath('user.is_active', true);
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}/toggle")->assertStatus(422);
    }

    public function test_requests_lists_split_swap_and_admin_can_approve_a_swap(): void
    {
        $swapTask = $this->task();
        $swap = $this->request($swapTask, 'swap');
        $decline = $this->request($this->task(), 'decline');
        $this->actingAs($this->admin, 'sanctum');

        $ids = collect($this->getJson('/api/v1/admin/requests')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($swap->id));
        $this->assertFalse($ids->contains($decline->id));

        $this->postJson("/api/v1/admin/requests/{$swap->id}/approve", [])->assertStatus(422)->assertJsonValidationErrors('approved_designer_id');
        $this->postJson("/api/v1/admin/requests/{$swap->id}/approve", ['approved_designer_id' => $this->other->id])
            ->assertOk()->assertJsonPath('request.overall_status', 'approved');
        // The original stays as the swapper's read-only record; a fresh active task goes to the new designer.
        $this->assertSame('swap_tasks', $swapTask->fresh()->status);
        $this->assertDatabaseHas('design_tasks', ['designer_id' => $this->other->id, 'status' => 'assigned_tasks', 'party_name' => 'Acme']);

        // Decline is the Designer Head's call, not the Admin's.
        $this->postJson("/api/v1/admin/requests/{$decline->id}/approve", ['approved_designer_id' => $this->other->id])->assertForbidden();
    }

    public function test_admin_can_reject_with_reason(): void
    {
        $split = $this->request($this->task(), 'split');
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson("/api/v1/admin/requests/{$split->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/requests/{$split->id}/reject", ['decision_reason' => 'No'])
            ->assertOk()->assertJsonPath('request.overall_status', 'rejected');
    }
}
