<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Designer mobile API: board, detail, status moves, access boundaries, chat. Scratch DB only. */
class MobileDesignerApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $designer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
        $this->other = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "mda{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function task(array $overrides = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Mobile test task',
            'vertical' => 'roadshow',
            'task_nature' => 'new_creative_design',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3),
            'designer_id' => $this->designer->id,
            'total_creatives' => 3,
            'status' => 'assigned_tasks',
            'requirements' => [],
        ], $overrides));
    }

    public function test_board_lists_only_own_tasks_with_statuses(): void
    {
        $mine = $this->task();
        $theirs = $this->task(['designer_id' => $this->other->id]);

        $res = $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer/tasks')->assertOk();

        $ids = collect($res->json('tasks'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->assertNotEmpty($res->json('statuses'));
    }

    public function test_detail_and_role_boundaries(): void
    {
        $task = $this->task();

        $this->actingAs($this->designer, 'sanctum')->getJson("/api/v1/designer/tasks/{$task->id}")
            ->assertOk()->assertJsonPath('task.id', $task->id)->assertJsonPath('access.read_only', false);

        $this->actingAs($this->other, 'sanctum')->getJson("/api/v1/designer/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/designer/tasks')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/designer/tasks')->assertUnauthorized();
    }

    public function test_move_follows_state_machine_and_writes_history(): void
    {
        $task = $this->task();
        $this->actingAs($this->designer, 'sanctum');

        $this->postJson("/api/v1/designer/tasks/{$task->id}/move", ['status' => 'review_analysis'])
            ->assertOk()->assertJsonPath('task.status', 'review_analysis');

        $this->assertDatabaseHas('design_task_status_histories', [
            'design_task_id' => $task->id, 'to_status' => 'review_analysis', 'change_source' => 'mobile_app',
        ]);

        // Designers can never jump straight to completed/rework.
        $this->postJson("/api/v1/designer/tasks/{$task->id}/move", ['status' => 'completed'])->assertStatus(422);
        $this->assertSame('review_analysis', $task->fresh()->status);
    }

    public function test_cannot_move_someone_elses_task(): void
    {
        $task = $this->task(['designer_id' => $this->other->id]);

        $this->actingAs($this->designer, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/move", ['status' => 'review_analysis'])
            ->assertForbidden();
    }

    public function test_in_progress_cannot_skip_to_review_until_creatives_done(): void
    {
        $task = $this->task(['status' => 'in_progress']);

        $this->actingAs($this->designer, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/move", ['status' => 'waiting_confirmation'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_backward_request_creates_status_change_request(): void
    {
        $task = $this->task(['status' => 'in_progress']);
        $this->actingAs($this->designer, 'sanctum');

        $this->postJson("/api/v1/designer/tasks/{$task->id}/backward-request", ['to_status' => 'yet_to_start', 'reason' => 'Need re-brief'])
            ->assertCreated();
        $this->assertDatabaseHas('design_task_requests', ['design_task_id' => $task->id, 'request_type' => 'status_change']);

        $this->postJson("/api/v1/designer/tasks/{$task->id}/backward-request", ['to_status' => 'waiting_confirmation', 'reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_chat_post_and_poll_after_id(): void
    {
        $task = $this->task();
        $this->actingAs($this->designer, 'sanctum');

        $first = $this->postJson("/api/v1/designer/tasks/{$task->id}/comments", ['comment' => 'Hello BD'])
            ->assertCreated()->assertJsonPath('comment.comment', 'Hello BD')->json('comment.id');

        $this->postJson("/api/v1/designer/tasks/{$task->id}/comments", ['comment' => 'Second'])->assertCreated();

        $this->getJson("/api/v1/designer/tasks/{$task->id}/comments")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/designer/tasks/{$task->id}/comments?after_id={$first}")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/v1/designer/tasks/{$task->id}/comments", ['comment' => 'intruder'])->assertForbidden();
    }
}
