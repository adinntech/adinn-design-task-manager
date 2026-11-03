<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskEditedNotification extends Notification
{
    use BroadcastsSynchronously;

    /** @param  array<int, string>  $fields */
    public function __construct(private DesignTask $task, private User $editor, private array $fields) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Task Updated',
            'message' => "{$this->task->task_id} was updated by {$this->editor->name}: ".Str::limit(implode(', ', $this->fields), 120).'.',
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'task_edit',
        ];
    }
}
