<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use App\Notifications\Concerns\TruncatesText;
use Illuminate\Notifications\Notification;

class TaskCommentNotification extends Notification
{
    use BroadcastsSynchronously, TruncatesText;

    public function __construct(
        private DesignTask $task,
        private User $author,
        private string $comment,
        private bool $isClarification = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $preview = $this->truncateWords($this->comment, 30);

        return [
            'title' => $this->isClarification ? 'Clarification Requested' : 'New Comment',
            'message' => "{$this->task->task_id} • {$this->author->name}: {$preview}",
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'comment',
            'is_clarification' => $this->isClarification,
        ];
    }
}
