<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsSynchronously;
use App\Notifications\Concerns\TruncatesText;
use Illuminate\Notifications\Notification;

class ReworkRequestedNotification extends Notification
{
    use BroadcastsSynchronously, TruncatesText;

    public function __construct(
        private DesignTask $task,
        private int $reworkCount,
        private int $reworkCreatives,
        private string $comment,
        private User $bd
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $preview = $this->truncateWords($this->comment, 30);

        return [
            'title' => 'Rework Requested',
            'message' => "{$this->task->task_id} • Rework #{$this->reworkCount} • {$this->reworkCreatives} creative(s) • by {$this->bd->name}\n{$preview}",
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'rework',
        ];
    }
}
