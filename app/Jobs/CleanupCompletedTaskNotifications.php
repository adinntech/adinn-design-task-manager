<?php

namespace App\Jobs;

use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes notification rows for tasks that have been in the "completed"
 * status for more than 30 days. Only touches the `notifications` table —
 * the DesignTask, status history, comments, ratings, etc. are never
 * modified. "Completed" time comes from DesignTaskStatusHistory (the
 * immutable log), never from notification/task created_at or updated_at —
 * same source DesignerHeadTaskBoardService already treats as authoritative.
 */
class CleanupCompletedTaskNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $completedTaskIds = DesignTask::query()
            ->where('status', 'completed')
            ->pluck('id');

        if ($completedTaskIds->isEmpty()) {
            return;
        }

        $cutoff = now()->subDays(30)->toDateTimeString();

        $eligibleTaskIds = DesignTaskStatusHistory::query()
            ->where('to_status', 'completed')
            ->whereIn('design_task_id', $completedTaskIds)
            ->selectRaw('design_task_id, MAX(created_at) as last_completed_at')
            ->groupBy('design_task_id')
            ->havingRaw('MAX(created_at) < ?', [$cutoff])
            ->pluck('design_task_id');

        if ($eligibleTaskIds->isEmpty()) {
            return;
        }

        $eligibleTaskIds->chunk(500)->each(function ($chunk) {
            DatabaseNotification::query()
                ->whereIn('data->task_id', $chunk->all())
                ->delete();
        });
    }
}
