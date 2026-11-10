<?php

namespace Tests\Feature\Api;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Delegate approvals (Designers the Admin allowed to approve Swap/Split of teammates). Scratch DB only. */
class MobileDesignerApprovalsApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $bd;

    private User $approver;

    private User $teammate;

    private User $third;

    private User $plain;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();
        Storage::fake('spaces');

        $otherHead = $this->makeUser('designer_head');
        $this->head = $this->makeUser('designer_head');
        $this->bd = $this->makeUser('bd');
        $this->approver = $this->makeUser('designer', $this->head->id, true);
        $this->teammate = $this->makeUser('designer', $this->head->id);
        $this->third = $this->makeUser('designer', $this->head->id);
        $this->plain = $this->makeUser('designer', $this->head->id); // no approval right
        $this->outsider = $this->makeUser('designer', $otherHead->id);
    }

    private function makeUser(string $role, ?int $headId = null, bool $canApprove = false): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "mda{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
            'designer_head_id' => $headId,
            'can_approve_requests' => $canApprove,
        ]);
    }

    private function task(User $designer, array $overrides = []): DesignTask
    {
        return DesignTask::create(array_merge([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Approval task',
            'vertical' => 'roadshow',
            'task_nature' => 'new_creative_design',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3),
            'designer_id' => $designer->id,
            'total_creatives' => 4,
            'status' => 'in_progress',
            'requirements' => [],
        ], $overrides));
    }

    private function request(DesignTask $task, User $by, string $type = 'swap', array $extra = []): DesignTaskRequest
    {
        return DesignTaskRequest::create(array_merge([
            'design_task_id' => $task->id, 'request_type' => $type, 'requested_by' => $by->id,
            'overall_status' => 'pending_designer_head', 'designer_head_status' => 'pending', 'admin_status' => 'pending',
            'reason' => 'Need help',
            'split_details' => $type === 'split' ? ['creative_count' => 2, 'details' => 'x'] : null,
        ], $extra));
    }

    public function test_me_flags_a_delegate_approver_only_when_the_admin_allowed_it_like_the_web_menu(): void
    {
        $this->actingAs($this->approver, 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.is_request_approver', true);
        $this->actingAs($this->plain, 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.is_request_approver', false);

        // Allowed but not on a team yet: the web still lists Approvals, so the app does too (the list itself stays closed).
        $noTeam = $this->makeUser('designer', null, true);
        $this->actingAs($noTeam, 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.is_request_approver', true);
        $this->actingAs($noTeam, 'sanctum')->getJson('/api/v1/designer/approvals')->assertForbidden();
    }

    public function test_only_allowed_designers_can_open_approvals(): void
    {
        $this->actingAs($this->plain, 'sanctum')->getJson('/api/v1/designer/approvals')->assertForbidden();
        $this->actingAs($this->approver, 'sanctum')->getJson('/api/v1/designer/approvals')->assertOk();
        $this->actingAs($this->head, 'sanctum')->getJson('/api/v1/designer/approvals')->assertForbidden(); // role middleware
    }

    public function test_list_shows_teammates_swap_and_split_only_never_own_or_other_teams_or_decline(): void
    {
        $swap = $this->request($this->task($this->teammate), $this->teammate, 'swap');
        $split = $this->request($this->task($this->third), $this->third, 'split');
        $own = $this->request($this->task($this->approver), $this->approver, 'swap');
        $decline = $this->request($this->task($this->teammate), $this->teammate, 'decline');
        $foreign = $this->request($this->task($this->outsider), $this->outsider, 'swap');
        $onMyTask = $this->request($this->task($this->approver), $this->teammate, 'swap'); // someone's request on my own task

        $ids = collect($this->actingAs($this->approver, 'sanctum')->getJson('/api/v1/designer/approvals')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($swap->id));
        $this->assertTrue($ids->contains($split->id));
        foreach ([$own, $decline, $foreign, $onMyTask] as $r) {
            $this->assertFalse($ids->contains($r->id), "request {$r->id} must not be listed");
        }
    }

    public function test_detail_carries_task_context_split_limits_and_assignable_designers(): void
    {
        $req = $this->request($this->task($this->teammate), $this->teammate, 'split');

        $res = $this->actingAs($this->approver, 'sanctum')->getJson("/api/v1/designer/approvals/{$req->id}")->assertOk();

        $res->assertJsonPath('request.id', $req->id)->assertJsonPath('is_pending', true)->assertJsonPath('task.assigned_designer', $this->teammate->name);
        $this->assertNotNull($res->json('split'));
        $names = collect($res->json('designers'))->pluck('id');
        $this->assertFalse($names->contains($this->teammate->id)); // the current assignee can't receive it
        $this->assertFalse($names->contains($this->outsider->id)); // other team
    }

    public function test_detail_is_forbidden_for_foreign_decline_or_own_requests(): void
    {
        $this->actingAs($this->approver, 'sanctum');

        $this->getJson('/api/v1/designer/approvals/'.$this->request($this->task($this->outsider), $this->outsider)->id)->assertForbidden();
        $this->getJson('/api/v1/designer/approvals/'.$this->request($this->task($this->teammate), $this->teammate, 'decline')->id)->assertForbidden();
        $this->getJson('/api/v1/designer/approvals/'.$this->request($this->task($this->approver), $this->approver)->id)->assertForbidden();
    }

    public function test_approving_a_swap_hands_a_new_task_to_the_approved_designer(): void
    {
        $task = $this->task($this->teammate);
        $req = $this->request($task, $this->teammate, 'swap');
        $this->actingAs($this->approver, 'sanctum');

        $this->postJson("/api/v1/designer/approvals/{$req->id}/approve", [])->assertStatus(422)->assertJsonValidationErrors('approved_designer_id');

        $this->postJson("/api/v1/designer/approvals/{$req->id}/approve", ['approved_designer_id' => $this->third->id])
            ->assertOk()->assertJsonPath('request.overall_status', 'approved');

        // A swap keeps the original as the requester's read-only "Swap Tasks" record and hands a new task to the approved designer.
        $this->assertSame('swap_tasks', $task->fresh()->status);
        $handed = DesignTask::query()->where('designer_id', $this->third->id)->where('status', 'assigned_tasks')
            ->where('requirements->_swap_request_id', $req->id)->first();
        $this->assertNotNull($handed);
        $this->assertSame($task->task_id, data_get($handed->requirements, '_swapped_from_task_id'));
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $req = $this->request($this->task($this->teammate), $this->teammate, 'swap');
        $this->actingAs($this->approver, 'sanctum');

        $this->postJson("/api/v1/designer/approvals/{$req->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/designer/approvals/{$req->id}/reject", ['decision_reason' => 'Not now'])
            ->assertOk()->assertJsonPath('request.overall_status', 'rejected');
    }

    public function test_deciding_is_blocked_for_own_foreign_decline_and_unallowed_designers(): void
    {
        $mine = $this->request($this->task($this->approver), $this->approver);
        $foreign = $this->request($this->task($this->outsider), $this->outsider);
        $decline = $this->request($this->task($this->teammate), $this->teammate, 'decline');

        $this->actingAs($this->approver, 'sanctum');
        foreach ([$mine, $foreign, $decline] as $r) {
            $this->postJson("/api/v1/designer/approvals/{$r->id}/approve", ['approved_designer_id' => $this->third->id])->assertForbidden();
            $this->postJson("/api/v1/designer/approvals/{$r->id}/reject", ['decision_reason' => 'no'])->assertForbidden();
        }

        $ok = $this->request($this->task($this->teammate), $this->teammate);
        $this->actingAs($this->plain, 'sanctum')
            ->postJson("/api/v1/designer/approvals/{$ok->id}/approve", ['approved_designer_id' => $this->third->id])->assertForbidden();
    }
}
