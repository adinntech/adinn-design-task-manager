<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use App\Services\DesignTaskStatusService;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification
{
    use BroadcastsSynchronously;

    public function __construct(private DesignTask $task, private string $toStatus, private User $changedBy) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->toStatus === 'waiting_confirmation') {
            $designerName = $this->task->designer->name ?? $this->changedBy->name;

            $message = match ($notifiable->role ?? null) {
                'bd' => "{$this->task->task_id} is waiting for your review.\nSubmitted: ".now()->format('d M Y \a\t h:i A'),
                default => "{$this->task->task_id} — {$designerName} submitted the task and it is waiting for BD review.",
            };

            return [
                'title' => 'Task Waiting for BD Review',
                'message' => $message,
                'task_id' => $this->task->id,
                'task_ref' => $this->task->task_id,
                'task_name' => $this->task->display_task_name ?? $this->task->task_name,
                'category' => 'status',
            ];
        }

        $label = DesignTaskStatusService::STATUSES[$this->toStatus] ?? ucwords(str_replace('_', ' ', $this->toStatus));

        return [
            'title' => 'Task Status Updated',
            'message' => "{$this->task->task_id} was moved to \"{$label}\" by {$this->changedBy->name}.",
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'status',
        ];
    }
}
