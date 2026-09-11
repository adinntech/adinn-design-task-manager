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
     */
    public function __construct(
        private DesignTask $task,
        private string $requestType,
        private string $event,
        private User $actor,
        private ?string $comment = null
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

        $title = match ($this->event) {
            'approved' => "{$typeLabel} Request Approved",
            'rejected' => "{$typeLabel} Request Rejected",
            default => "{$typeLabel} Request Submitted",
        };

        $message = match ($this->event) {
            'approved' => "{$this->task->task_id}: your {$this->requestType} request was approved by {$this->actor->name}.",
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
