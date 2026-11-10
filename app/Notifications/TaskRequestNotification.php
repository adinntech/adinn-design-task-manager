<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskRequestNotification extends Notification
{
    use BroadcastsSynchronously;

    /**
     * @param  string  $requestType  'split'|'swap'|'decline'|'status_change'|'bd_approval'
     * @param  string  $event  'submitted'|'approved'|'rejected'
     * @param  string|null  $comment  Only used by 'bd_approval' — the BD's approval/rejection comment.
     * @param  User|null  $requester  Set when a delegated Designer decided someone else's request, so the
     *                                Designer Head is told who asked and who decided on their behalf.
     * @param  bool  $asApprover  A 'submitted' alert for a Designer who may approve it on the Head's behalf
     *                            (Swap / Split of a teammate): worded as "needs your approval", opens Approvals.
     * @param  User|null  $assignedTo  On an approval sent to the requester: the Designer the Swap / Split went to.
     */
    public function __construct(
        private DesignTask $task,
        private string $requestType,
        private string $event,
        private User $actor,
        private ?string $comment = null,
        private ?User $requester = null,
        private bool $asApprover = false,
        private ?User $assignedTo = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->requestType === 'bd_approval') {
            return $this->bdApprovalArray();
        }

        $typeLabel = Str::headline($this->requestType);

        if ($this->asApprover && $this->event === 'submitted') {
            return [
                'title' => "Approval needed: {$typeLabel} request",
                'message' => "{$this->task->task_id}: {$this->actor->name} asked for approval of a {$this->requestType} request. You can approve or decline it on your Designer Head's behalf from the Approvals tab.",
                'task_id' => $this->task->id,
                'task_ref' => $this->task->task_id,
                'task_name' => $this->task->display_task_name ?? $this->task->task_name,
                'category' => 'approval_request',
            ];
        }

        if ($this->requester) {
            $verb = $this->event === 'rejected' ? 'rejected' : 'approved';

            return [
                'title' => "{$typeLabel} Request {$verb} by Designer",
                'message' => "{$this->task->task_id}: {$this->actor->name} {$verb} {$this->requester->name}'s {$this->requestType} request.",
                'task_id' => $this->task->id,
                'task_ref' => $this->task->task_id,
                'task_name' => $this->task->display_task_name ?? $this->task->task_name,
                'category' => $this->requestType,
            ];
        }

        $title = match ($this->event) {
            'approved' => "{$typeLabel} Request Approved",
            'rejected' => "{$typeLabel} Request Rejected",
            default => "{$typeLabel} Request Submitted",
        };

        $message = match ($this->event) {
            'approved' => "{$this->task->task_id}: your {$this->requestType} request was approved by {$this->actor->name}.".($this->assignedTo ? " It was assigned to {$this->assignedTo->name}." : ''),
            'rejected' => "{$this->task->task_id}: your {$this->requestType} request was rejected by {$this->actor->name}.",
            default => "{$this->task->task_id}: {$this->actor->name} submitted a {$this->requestType} request.",
        };

        return [
            'title' => $title,
            'message' => $message,
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => $this->requestType,
        ];
    }

    /**
     * Designer → BD task-confirmation copy — kept separate from the generic
     * split/swap/decline/status_change branch above since the wording in the
     * spec ("New task confirmation request" / "approved" / "rejected") doesn't
     * fit that branch's generic "{type} request" phrasing.
     */
    private function bdApprovalArray(): array
    {
        $title = match ($this->event) {
            'approved' => 'Your task request has been approved',
            'rejected' => 'Your task request has been rejected',
            default => 'New task confirmation request',
        };

        $message = match ($this->event) {
            'approved' => "{$this->task->task_id}: {$this->actor->name} approved your task request.",
            'rejected' => "{$this->task->task_id}: {$this->actor->name} rejected your task request.",
            default => "{$this->task->task_id}: {$this->actor->name} created \"{$this->task->display_task_name}\" and needs your confirmation.",
        };

        if ($this->comment) {
            $message .= " Comment: {$this->comment}";
        }

        return [
            'title' => $title,
            'message' => $message,
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'bd_approval',
        ];
    }
}
