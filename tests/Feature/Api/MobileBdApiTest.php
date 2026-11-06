<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** BD mobile API: board, ownership, chat, rework, rating/completion, designer-task approvals. Scratch DB only. */
class MobileBdApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $bd;

    private User $otherBd;

    private User $designer;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->bd = $this->makeUser('bd');
        $this->otherBd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer');
    }

    private function makeUser(string $role): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "mbd{$role}{$n}@example.test",
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
            'task_name' => 'BD task',
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
            'status' => 'waiting_confirmation',
            'requirements' => [],
        ], $overrides));
    }

    private function fullProgress(DesignTask $task): void
    {
        DesignTaskEodRecord::create([
            'design_task_id' => $task->id, 'designer_id' => $this->designer->id, 'update_type' => 'progress',
            'completed_count' => 3, 'total_creatives_snapshot' => 3, 'cumulative_completed' => 3, 'remaining_creatives' => 0,
            'attachment_disk' => 'spaces', 'attachment_path' => 'x/y.zip', 'attachment_original_name' => 'y.zip', 'submitted_at' => now(),
        ]);
    }

    private function ratings(array $o = []): array
    {
        return array_merge([
            'designer_attitude' => 5, 'design_satisfaction' => 4.5, 'rework_iteration' => 4, 'meeting_deadline' => 5, 'client_satisfaction' => 4.5,
            'completion_type' => 'completed',
        ], $o);
    }

    public function test_board_is_own_tasks_only_and_role_scoped(): void
    {
        $mine = $this->task();
        $theirs = $this->task(['assigned_by' => $this->otherBd->id]);
        $draft = $this->task(['status' => 'draft']);

        $res = $this->actingAs($this->bd, 'sanctum')->getJson('/api/v1/bd/tasks')->assertOk();
        $ids = collect($res->json('tasks'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($draft->id));
        $this->assertFalse($ids->contains($theirs->id));

        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/bd/tasks')->assertForbidden();
    }

    public function test_cannot_touch_another_bds_task(): void
    {
        $theirs = $this->task(['assigned_by' => $this->otherBd->id]);
        $this->actingAs($this->bd, 'sanctum');

        $this->getJson("/api/v1/bd/tasks/{$theirs->id}")->assertForbidden();
        $this->postJson("/api/v1/bd/tasks/{$theirs->id}/rework", ['number_of_creatives' => 1, 'comment' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/bd/tasks/{$theirs->id}/complete", $this->ratings())->assertForbidden();
        $this->postJson("/api/v1/bd/tasks/{$theirs->id}/comments", ['comment' => 'hi'])->assertForbidden();
    }

    public function test_detail_exposes_review_actions(): void
    {
        $task = $this->task();

        $this->actingAs($this->bd, 'sanctum')->getJson("/api/v1/bd/tasks/{$task->id}")
            ->assertOk()->assertJsonPath('actions.can_review', true)->assertJsonPath('actions.can_decide_confirmation', false);
    }

    public function test_rework_moves_task_and_logs_history(): void
    {
        $task = $this->task();
        $this->actingAs($this->bd, 'sanctum');

        $this->postJson("/api/v1/bd/tasks/{$task->id}/rework", ['number_of_creatives' => 9, 'comment' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/bd/tasks/{$task->id}/rework", ['number_of_creatives' => 2])->assertStatus(422);

        $this->postJson("/api/v1/bd/tasks/{$task->id}/rework", ['number_of_creatives' => 2, 'comment' => 'Fix colours'])
            ->assertOk()->assertJsonPath('task.status', 'rework');

        $this->assertDatabaseHas('design_task_bd_reviews', ['design_task_id' => $task->id, 'action' => 'rework', 'number_of_creatives' => 2]);
        $this->assertDatabaseHas('design_task_status_histories', ['design_task_id' => $task->id, 'to_status' => 'rework', 'change_source' => 'bd_rework']);

        // No longer waiting for confirmation.
        $this->postJson("/api/v1/bd/tasks/{$task->id}/rework", ['number_of_creatives' => 1, 'comment' => 'again'])->assertStatus(422);
    }

    public function test_complete_with_rating_requires_full_progress_and_valid_stars(): void
    {
        $task = $this->task();
        $this->actingAs($this->bd, 'sanctum');

        // 0% progress.
        $this->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings())->assertStatus(422)->assertJsonValidationErrors('status');

        $this->fullProgress($task);

        $this->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings(['designer_attitude' => 4.3]))->assertStatus(422);
        $this->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings(['designer_attitude' => 1, 'design_satisfaction' => 1, 'rework_iteration' => 1, 'meeting_deadline' => 1, 'client_satisfaction' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors('rating_comment');

        $this->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings())
            ->assertOk()->assertJsonPath('task.status', 'completed');
        $this->assertDatabaseHas('design_task_bd_reviews', ['design_task_id' => $task->id, 'action' => 'completed', 'overall_rating' => 4.5]);

        // A second rating is refused.
        $this->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings())->assertStatus(422);
    }

    public function test_complete_can_route_to_prepare_printing_file(): void
    {
        $task = $this->task();
        $this->fullProgress($task);

        $this->actingAs($this->bd, 'sanctum')
            ->postJson("/api/v1/bd/tasks/{$task->id}/complete", $this->ratings(['completion_type' => 'prepare_printing_file']))
            ->assertOk()->assertJsonPath('task.status', 'prepare_printing_file');
    }

    public function test_approve_and_reject_designer_created_tasks(): void
    {
        $a = $this->task(['status' => 'pending_bd_approval']);
        $b = $this->task(['status' => 'pending_bd_approval']);
        $this->actingAs($this->bd, 'sanctum');

        $this->postJson("/api/v1/bd/tasks/{$a->id}/approve", ['comment' => 'Looks good'])
            ->assertOk()->assertJsonPath('task.status', 'assigned_tasks');

        $this->postJson("/api/v1/bd/tasks/{$b->id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->postJson("/api/v1/bd/tasks/{$b->id}/reject", ['comment' => 'Wrong client'])
            ->assertOk()->assertJsonPath('task.status', 'bd_rejected');

        // Already decided → not pending any more.
        $this->postJson("/api/v1/bd/tasks/{$a->id}/approve", [])->assertNotFound();
    }

    public function test_chat_and_clarification_threads_are_separate(): void
    {
        $task = $this->task();
        $this->actingAs($this->bd, 'sanctum');

        $this->postJson("/api/v1/bd/tasks/{$task->id}/comments", ['comment' => 'General note'])->assertCreated();
        $this->postJson("/api/v1/bd/tasks/{$task->id}/comments", ['comment' => 'Answer to designer', 'clarification' => true])->assertCreated();

        $this->getJson("/api/v1/bd/tasks/{$task->id}/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/bd/tasks/{$task->id}")->assertJsonCount(1, 'clarifications');

        $done = $this->task(['status' => 'completed']);
        $this->postJson("/api/v1/bd/tasks/{$done->id}/comments", ['comment' => 'late', 'clarification' => true])->assertForbidden();
    }
}
