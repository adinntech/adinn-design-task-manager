<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    public function __construct(private DesignTask $task, private User $assignedBy)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Task Assigned',
            'message' => "{$this->task->task_id} has been assigned to you by {$this->assignedBy->name}.",
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
        ];
    }
}
