<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Designer Head mobile API: team board, approvals, team scope, chat. Scratch DB only. */
class MobileDesignerHeadApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $otherHead;

    private User $bd;

    private User $designer;

    private User $teammate;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $this->head = $this->makeUser('designer_head');
        $this->otherHead = $this->makeUser('designer_head');
        $this->bd = $this->makeUser('bd');
        $this->designer = $this->makeUser('designer', $this->head->id);
        $this->teammate = $this->makeUser('designer', $this->head->id);
        $this->outsider = $this->makeUser('designer', $this->otherHead->id);
    }

    private function makeUser(string $role, ?int $headId = null): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "mdh{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
            'designer_head_id' => $headId,
        ]);
    }

    private function task(User $designer, array $overrides = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Head task',
            'vertical' => 'roadshow',
            'task_nature' => 'new_creative_design',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3),
            'designer_id' => $designer->id,
            'total_creatives' => 3,
            'status' => 'review_analysis',
            'requirements' => [],
        ], $overrides));
    }

    private function declineRequest(DesignTask $task, User $by): DesignTaskRequest
    {
        return DesignTaskRequest::create([
            'design_task_id' => $task->id, 'request_type' => 'decline', 'requested_by' => $by->id,
            'overall_status' => 'pending_approval', 'designer_head_status' => 'pending', 'admin_status' => 'pending',
            'reason' => 'Overloaded',
        ]);
    }

    public function test_board_shows_only_team_tasks_and_roles_are_enforced(): void
    {
        $mine = $this->task($this->designer);
        $theirs = $this->task($this->outsider);

        $res = $this->actingAs($this->head, 'sanctum')->getJson('/api/v1/designer-head/tasks')->assertOk();
        $ids = collect($res->json('tasks'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
        $this->assertCount(2, $res->json('designers'));

        $this->actingAs($this->head, 'sanctum')->getJson("/api/v1/designer-head/tasks?designer_id={$this->outsider->id}")->assertForbidden();
        $this->actingAs($this->designer, 'sanctum')->getJson('/api/v1/designer-head/tasks')->assertForbidden();
    }

    public function test_pending_requests_are_team_scoped(): void
    {
        $mine = $this->declineRequest($this->task($this->designer), $this->designer);
        $theirs = $this->declineRequest($this->task($this->outsider), $this->outsider);

        $ids = collect($this->actingAs($this->head, 'sanctum')->getJson('/api/v1/designer-head/requests')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_approve_decline_reassigns_task_and_cannot_be_repeated(): void
    {
        $task = $this->task($this->designer);
        $req = $this->declineRequest($task, $this->designer);
        $this->actingAs($this->head, 'sanctum');

        $this->postJson("/api/v1/designer-head/requests/{$req->id}/approve", [])->assertStatus(422)->assertJsonValidationErrors('approved_designer_id');

        $this->postJson("/api/v1/designer-head/requests/{$req->id}/approve", ['approved_designer_id' => $this->teammate->id])
            ->assertOk()->assertJsonPath('request.overall_status', 'approved');
        $this->assertSame($this->teammate->id, (int) $task->fresh()->designer_id);

        $this->postJson("/api/v1/designer-head/requests/{$req->id}/approve", ['approved_designer_id' => $this->teammate->id])->assertStatus(422);
    }

    public function test_reject_requires_reason(): void
    {
        $req = $this->declineRequest($this->task($this->designer), $this->designer);
        $this->actingAs($this->head, 'sanctum');

        $this->postJson("/api/v1/designer-head/requests/{$req->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/designer-head/requests/{$req->id}/reject", ['decision_reason' => 'Not valid'])
            ->assertOk()->assertJsonPath('request.overall_status', 'rejected');
    }

    public function test_head_cannot_decide_other_teams_requests_or_open_their_tasks(): void
    {
        $task = $this->task($this->outsider);
        $req = $this->declineRequest($task, $this->outsider);
        $this->actingAs($this->head, 'sanctum');

        $this->postJson("/api/v1/designer-head/requests/{$req->id}/reject", ['decision_reason' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/designer-head/requests/{$req->id}/approve", ['approved_designer_id' => $this->teammate->id])->assertForbidden();
        $this->assertSame('pending_approval', $req->fresh()->overall_status);

        $this->getJson("/api/v1/designer-head/tasks/{$task->id}")->assertForbidden();
        $this->postJson("/api/v1/designer-head/tasks/{$task->id}/comments", ['comment' => 'hi'])->assertForbidden();
    }

    public function test_detail_and_chat(): void
    {
        $task = $this->task($this->designer);
        $this->actingAs($this->head, 'sanctum');

        $this->getJson("/api/v1/designer-head/tasks/{$task->id}")->assertOk()->assertJsonPath('task.id', $task->id);

        $this->postJson("/api/v1/designer-head/tasks/{$task->id}/comments", ['comment' => 'Please prioritise'])
            ->assertCreated()->assertJsonPath('comment.user.role', 'designer_head');
        $this->getJson("/api/v1/designer-head/tasks/{$task->id}/comments")->assertOk()->assertJsonCount(1, 'data');
    }
}
