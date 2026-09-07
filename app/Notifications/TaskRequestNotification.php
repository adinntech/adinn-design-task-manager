<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Notifications\Notification;

class TaskRequestNotification extends Notification
{
    /**
     * @param  string  $requestType  'split'|'swap'|'decline'
     * @param  string  $event  'submitted'|'approved'|'rejected'
     */
    public function __construct(
        private DesignTask $task,
        private string $requestType,
        private string $event,
        private User $actor
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabel = ucfirst($this->requestType);

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
        ];
    }
}
