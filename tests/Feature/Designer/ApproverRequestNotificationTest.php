<?php

namespace Tests\Feature\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Notifications\TaskRequestNotification;
use App\Services\TaskNotificationService;
use App\Services\TaskNotificationUrlResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** A Designer allowed to approve Swap / Split is told (bell + push) when a teammate asks. Scratch DB only. */
class ApproverRequestNotificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;

    private User $bd;

    private User $approver;

    private User $requester;

    private User $plain;

    private User $outsiderApprover;

    protected function setUp(): void
    {
        parent::setUp();

        $db = (string) config('database.connections.'.config('database.default').'.database');
        $this->assertTrue(str_contains($db, 'test') || str_contains($db, 'scratch'), "Refusing to run against '{$db}'.");

        Notification::fake();

        $otherHead = $this->makeUser('designer_head');
        $this->head = $this->makeUser('designer_head');
        $this->bd = $this->makeUser('bd');
        $this->approver = $this->makeUser('designer', $this->head->id, true);
        $this->requester = $this->makeUser('designer', $this->head->id, true); // also allowed, but it is their own request
        $this->plain = $this->makeUser('designer', $this->head->id);
        $this->outsiderApprover = $this->makeUser('designer', $otherHead->id, true);
    }

    private function makeUser(string $role, ?int $headId = null, bool $canApprove = false): User
    {
        static $n = 0;
        $n++;

        return User::create([
            'name' => ucfirst($role).' '.$n,
            'email' => "arn{$role}{$n}@example.test",
            'password' => 'Password@123',
            'role' => $role,
            'is_active' => true,
            'designer_head_id' => $headId,
            'can_approve_requests' => $canApprove,
        ]);
    }

    private function submit(string $type): DesignTaskRequest
    {
        $task = DesignTask::create([
            'task_id' => 'DT-TEST-'.uniqid(), 'assigned_at' => now(), 'assigned_by' => $this->bd->id, 'task_name' => 'Approval task',
            'vertical' => 'roadshow', 'task_nature' => 'new_creative_design', 'party_type' => 'client', 'party_name' => 'Acme',
            'contact_person' => 'Jo', 'mobile_number' => '9898989898', 'priority' => 'medium', 'due_at' => now()->addDays(3),
            'designer_id' => $this->requester->id, 'total_creatives' => 4, 'status' => 'in_progress', 'requirements' => [],
        ]);

        $request = DesignTaskRequest::create([
            'design_task_id' => $task->id, 'request_type' => $type, 'requested_by' => $this->requester->id,
            'overall_status' => 'pending_designer_head', 'designer_head_status' => 'pending', 'admin_status' => 'pending',
            'reason' => 'Need help', 'split_details' => $type === 'split' ? ['creative_count' => 2, 'details' => 'x'] : null,
        ]);

        app(TaskNotificationService::class)->requestSubmitted($request->load('requester', 'task'));

        return $request;
    }

    public function test_a_designer_with_approval_access_is_notified_of_a_teammates_swap_and_split(): void
    {
        foreach (['swap', 'split'] as $type) {
            $this->submit($type);
        }

        Notification::assertSentToTimes($this->approver, TaskRequestNotification::class, 2);

        Notification::assertSentTo($this->approver, TaskRequestNotification::class, function (TaskRequestNotification $n) {
            $data = $n->toArray($this->approver);

            return $data['category'] === 'approval_request'
                && str_contains($data['title'], 'Approval needed')
                && str_contains($data['message'], 'Approvals tab');
        });
    }

    public function test_nobody_else_gets_the_approver_alert(): void
    {
        $this->submit('swap');

        // not the requester (their own request is decided by the Head), not a designer without access, not another team's approver
        foreach ([$this->requester, $this->plain, $this->outsiderApprover, $this->bd] as $user) {
            Notification::assertNotSentTo($user, TaskRequestNotification::class);
        }
    }

    public function test_decline_requests_stay_with_the_designer_head_only(): void
    {
        $this->submit('decline');

        Notification::assertNotSentTo($this->approver, TaskRequestNotification::class);
        Notification::assertSentTo($this->head, TaskRequestNotification::class);
    }

    public function test_the_bell_link_opens_the_approvals_page(): void
    {
        $url = TaskNotificationUrlResolver::forNotificationData($this->approver, ['category' => 'approval_request', 'task_id' => 1]);

        $this->assertSame(route('designer.approvals.index'), $url);
        $this->assertNull(TaskNotificationUrlResolver::forNotificationData($this->plain, ['category' => 'approval_request', 'task_id' => 1]));
    }
}
