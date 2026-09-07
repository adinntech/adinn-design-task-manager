<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification
{
    public function __construct(private DesignTask $task, private string $toStatus, private User $changedBy)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->toStatus === 'waiting_confirmation') {
            return [
                'title' => 'Task Waiting for BD Review',
                'message' => "{$this->task->task_id} is waiting for your review.\nSubmitted: ".now()->format('d M Y \a\t h:i A'),
                'task_id' => $this->task->id,
                'task_ref' => $this->task->task_id,
                'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            ];
        }

        $label = DesignTaskStatusService::STATUSES[$this->toStatus] ?? ucwords(str_replace('_', ' ', $this->toStatus));

        return [
            'title' => 'Task Status Updated',
            'message' => "{$this->task->task_id} was moved to \"{$label}\" by {$this->changedBy->name}.",
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
        ];
    }
}
