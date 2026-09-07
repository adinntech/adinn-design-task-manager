<?php

namespace App\Notifications;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Notifications\Concerns\BroadcastsSynchronously;
use Illuminate\Notifications\Notification;

class TaskRatedNotification extends Notification
{
    use BroadcastsSynchronously;

    public function __construct(private DesignTask $task, private DesignTaskBdReview $review) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $rating = max(0, min(5, DesignTaskBdReview::roundToHalfStar($this->review->overall_rating)));
        $stars = str_repeat('★', (int) floor($rating)).($rating - floor($rating) >= 0.5 ? '½' : '');
        $formatted = DesignTaskBdReview::formatRating($rating);

        $message = "{$this->task->task_id} • {$stars} {$formatted} / 5 • by {$this->review->submitter?->name}";

        if (! empty($this->review->comment)) {
            $message .= "\n{$this->review->comment}";
        }

        return [
            'title' => 'Task Rated',
            'message' => $message,
            'task_id' => $this->task->id,
            'task_ref' => $this->task->task_id,
            'task_name' => $this->task->display_task_name ?? $this->task->task_name,
            'category' => 'rating',
        ];
    }
}
