<?php

namespace Tests\Feature\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Notifications\ApprovalAccessNotification;
use App\Notifications\TaskRequestNotification;
use App\Services\DesignTaskRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DelegateApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $otherHead;

    private User $delegate;

    private User $requester;

    private User $teammate;

    private User $outsider;

    private User $bd;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $this->head = $this->makeUser('designer_head');
        $this->otherHead = $this->makeUser('designer_head');
        $this->delegate = $this->makeUser('designer', $this->head->id, true);
        $this->requester = $this->makeUser('designer', $this->head->id);
        $this->teammate = $this->makeUser('designer', $this->head->id);
        $this->outsider = $this->makeUser('designer', $this->otherHead->id, true);
        $this->bd = $this->makeUser('bd');
        $this->admin = $this->makeUser('admin');
    }

    private function makeUser(string $role, ?int $headId = null, bool $canApprove = false): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "deleg{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
            'designer_head_id' => $headId,
            'can_approve_requests' => $canApprove,
        ]);
    }

    private function makeTask(User $designer, int $total = 6, string $status = 'in_progress'): DesignTask
    {
        return DesignTask::create([
            'task_id' => 'DT-TEST-'.uniqid(),
            'assigned_at' => now()->subDay(),
            'assigned_by' => $this->bd->id,
            'task_name' => 'Delegate task',
            'vertical' => 'roadshow',
            'task_nature' => 'creative_adaptation_requirements',
            'party_type' => 'client',
            'party_name' => 'Acme',
            'contact_person' => 'Jo',
            'mobile_number' => '9898989898',
            'priority' => 'medium',
            'due_at' => now()->addDays(3)->startOfMinute(),
            'designer_id' => $designer->id,
            'total_creatives' => $total,
            'status' => $status,
            'requirements' => [],
        ]);
    }

    private function splitRequest(User $by): DesignTaskRequest
    {
        return app(DesignTaskRequestService::class)->create($this->makeTask($by), $by, 'split', 'Need help', [
            'split_details' => ['creative_count' => 2],
        ]);
    }

    private function swapRequest(User $by): DesignTaskRequest
    {
        return app(DesignTaskRequestService::class)->create($this->makeTask($by), $by, 'swap', 'Swap please', [
            'target_designer_id' => $this->teammate->id,
        ]);
    }

    public function test_admin_can_grant_permission_when_creating_and_editing_a_designer(): void
    {
        $payload = [
            'name' => 'New Designer', 'username' => 'newdes'.uniqid(), 'employee_code' => 'E'.uniqid(),
            'email' => 'newdes'.uniqid().'@example.test', 'role' => 'designer', 'role_name' => 'Designer',
            'phone_number' => '9'.random_int(100000000, 999999999), 'password' => 'Password@123',
            'password_confirmation' => 'Password@123', 'is_active' => 1, 'designer_head_id' => $this->head->id,
            'can_approve_requests' => '1',
        ];

        $this->actingAs($this->admin)->post(route('admin.users.store'), $payload)->assertRedirect(route('admin.users.index'));

        $created = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue($created->can_approve_requests);

        $this->actingAs($this->admin)->put(route('admin.users.update', $created), array_merge($payload, ['password' => '', 'password_confirmation' => '', 'can_approve_requests' => '0']))
            ->assertRedirect();
        $this->assertFalse($created->fresh()->can_approve_requests);
    }

    public function test_approvals_tab_and_page_are_only_for_enabled_designers(): void
    {
        $this->actingAs($this->delegate)->get(route('designer.dashboard'))->assertOk()->assertSee(route('designer.approvals.index'), false);
        $this->actingAs($this->requester)->get(route('designer.dashboard'))->assertOk()->assertDontSee(route('designer.approvals.index'), false);

        $this->actingAs($this->delegate)->get(route('designer.approvals.index'))->assertOk();
        $this->actingAs($this->requester)->get(route('designer.approvals.index'))->assertForbidden();
    }

    public function test_delegate_approves_teammate_split_and_head_is_notified(): void
    {
        $request = $this->splitRequest($this->requester);

        $this->actingAs($this->delegate)->get(route('designer.approvals.index'))->assertOk()->assertSee($request->task->task_id);
        $this->actingAs($this->delegate)->get(route('designer.approvals.show', $request))->assertOk();

        $this->actingAs($this->delegate)->post(route('designer.approvals.approve', $request), [
            'approved_designer_id' => $this->teammate->id,
            'approved_creative_count' => 2,
        ])->assertRedirect(route('designer.approvals.index'));

        $this->assertSame('approved', $request->fresh()->overall_status);
        $this->assertSame($this->delegate->id, (int) $request->fresh()->designer_head_action_by);
        $this->assertSame(4, $request->task->fresh()->total_creatives);

        Notification::assertSentTo($this->head, TaskRequestNotification::class);
        Notification::assertSentTo($this->requester, TaskRequestNotification::class, function (TaskRequestNotification $n) {
            $data = $n->toArray($this->requester);

            // The requester is told it was approved, by whom, and which Designer the split went to.
            return str_contains($data['message'], 'approved by '.$this->delegate->name)
                && str_contains($data['message'], 'assigned to '.$this->teammate->name);
        });
        // ...and the Designer who received the split gets the "new task assigned" alert.
        Notification::assertSentTo($this->teammate, \App\Notifications\TaskAssignedNotification::class);

        // Decided requests leave the delegate's list and the head's pending list.
        $this->actingAs($this->delegate)->get(route('designer.approvals.index'))->assertDontSee($request->task->task_id);
        $this->assertFalse(DesignTaskRequest::query()->pending()->whereKey($request->id)->exists());
    }

    public function test_delegate_can_decline_a_swap_and_head_is_notified(): void
    {
        $request = $this->swapRequest($this->requester);

        $this->actingAs($this->delegate)->post(route('designer.approvals.reject', $request), ['decision_reason' => 'Not now'])
            ->assertRedirect(route('designer.approvals.index'));

        $this->assertSame('rejected', $request->fresh()->overall_status);
        Notification::assertSentTo($this->head, TaskRequestNotification::class);
    }

    public function test_delegate_cannot_decide_own_request_but_head_can(): void
    {
        $request = $this->splitRequest($this->delegate);

        $this->actingAs($this->delegate)->get(route('designer.approvals.index'))->assertDontSee($request->task->task_id);
        $this->actingAs($this->delegate)->get(route('designer.approvals.show', $request))->assertForbidden();

        $this->expectException(AuthorizationException::class);
        try {
            app(DesignTaskRequestService::class)->approve($request, $this->delegate, $this->teammate->id, 2);
        } finally {
            $this->assertSame('pending_approval', $request->fresh()->overall_status);
        }
    }

    public function test_head_can_still_approve_a_delegates_own_request(): void
    {
        $request = $this->splitRequest($this->delegate);

        app(DesignTaskRequestService::class)->approve($request, $this->head, $this->teammate->id, 2);

        $this->assertSame('approved', $request->fresh()->overall_status);
    }

    public function test_head_decision_first_blocks_the_delegate(): void
    {
        $request = $this->splitRequest($this->requester);
        app(DesignTaskRequestService::class)->approve($request, $this->head, $this->teammate->id, 2);

        $this->actingAs($this->delegate)->get(route('designer.approvals.index'))->assertDontSee($request->task->task_id);
        $this->actingAs($this->delegate)->post(route('designer.approvals.approve', $request), [
            'approved_designer_id' => $this->teammate->id, 'approved_creative_count' => 1,
        ])->assertSessionHas('error');

        // Still only one split child.
        $this->assertSame(4, $request->task->fresh()->total_creatives);
    }

    public function test_delegate_first_blocks_the_head(): void
    {
        $request = $this->splitRequest($this->requester);
        app(DesignTaskRequestService::class)->approve($request, $this->delegate, $this->teammate->id, 2);

        $this->actingAs($this->head);
        $this->actingAs($this->head)->post(route('designer-head.requests.approve', $request), [
            'approved_designer_id' => $this->teammate->id, 'approved_creative_count' => 1,
        ])->assertSessionHas('error');
    }

    public function test_delegate_cannot_touch_other_teams_unpermitted_or_non_split_swap_requests(): void
    {
        $foreign = $this->splitRequest($this->makeUser('designer', $this->otherHead->id));
        $this->actingAs($this->delegate)->get(route('designer.approvals.show', $foreign))->assertForbidden();
        $this->actingAs($this->delegate)->post(route('designer.approvals.approve', $foreign), [
            'approved_designer_id' => $this->teammate->id, 'approved_creative_count' => 1,
        ])->assertSessionHas('error');
        $this->assertSame('pending_approval', $foreign->fresh()->overall_status);

        $decline = app(DesignTaskRequestService::class)->create($this->makeTask($this->requester, 6, 'assigned_tasks'), $this->requester, 'decline', 'No time', []);
        $this->actingAs($this->delegate)->get(route('designer.approvals.show', $decline))->assertForbidden();
        $this->actingAs($this->delegate)->post(route('designer.approvals.approve', $decline), [
            'approved_designer_id' => $this->teammate->id,
        ])->assertSessionHas('error');
        $this->assertSame('pending_approval', $decline->fresh()->overall_status);

        // A designer without the permission is refused outright.
        $request = $this->splitRequest($this->requester);
        $this->actingAs($this->teammate)->post(route('designer.approvals.approve', $request), [
            'approved_designer_id' => $this->delegate->id, 'approved_creative_count' => 1,
        ])->assertForbidden();
    }

    public function test_designer_is_notified_only_when_access_is_newly_granted(): void
    {
        $payload = [
            'name' => $this->requester->name, 'username' => 'u'.uniqid(), 'employee_code' => 'E'.uniqid(),
            'email' => 'n'.uniqid().'@example.test', 'role' => 'designer', 'role_name' => 'Designer',
            'phone_number' => '9'.random_int(100000000, 999999999), 'password' => '', 'password_confirmation' => '',
            'is_active' => 1, 'designer_head_id' => $this->head->id, 'can_approve_requests' => '1',
        ];

        $this->actingAs($this->admin)->put(route('admin.users.update', $this->requester), $payload)->assertRedirect();
        Notification::assertSentToTimes($this->requester, ApprovalAccessNotification::class, 1);

        // Saving again with the box still ticked does not notify again.
        $payload['username'] = 'u'.uniqid();
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->requester), $payload)->assertRedirect();
        Notification::assertSentToTimes($this->requester, ApprovalAccessNotification::class, 1);

        // The bell link opens the Approvals page.
        $url = \App\Services\TaskNotificationUrlResolver::forNotificationData($this->requester->fresh(), ['category' => 'approval_access']);
        $this->assertSame(route('designer.approvals.index'), $url);
    }

    public function test_availability_meter_feeds_and_is_wired_into_the_pickers(): void
    {
        $this->makeTask($this->teammate, 10);

        foreach ([[$this->requester, 'designer.designers.availability'], [$this->head, 'designer-head.designers.availability']] as [$viewer, $route]) {
            $this->actingAs($viewer)->get(route($route, $this->teammate))
                ->assertOk()
                ->assertJsonPath('designer_id', $this->teammate->id)
                ->assertJsonPath('active_tasks', 1)
                ->assertJsonPath('active_creatives', 10)
                ->assertJsonStructure(['availability_percent', 'level', 'label', 'checked_in']);
        }

        // Only designers can be measured; BD's own endpoint keeps working.
        $this->actingAs($this->requester)->get(route('designer.designers.availability', $this->bd))->assertNotFound();
        $this->actingAs($this->bd)->get(route('bd.designers.availability', $this->teammate))->assertOk()->assertJsonPath('active_tasks', 1);
        $this->actingAs($this->bd)->get(route('designer.designers.availability', $this->teammate))->assertForbidden();

        // The meter is present where a designer is chosen.
        $request = $this->swapRequest($this->requester);
        $this->actingAs($this->delegate)->get(route('designer.approvals.show', $request))
            ->assertOk()->assertSee('data-availability-box', false)->assertSee('designer-availability', false);
        $this->actingAs($this->head)->get(route('designer-head.tasks.show', $request->task))
            ->assertOk()->assertSee('data-availability-box', false);
    }
}
