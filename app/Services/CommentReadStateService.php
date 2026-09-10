<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\TaskCommentNotification;

/**
 * Per-recipient unread state for task comments, reusing the existing
 * per-user `notifications` table rows created by TaskCommentNotification
 * (each row is already keyed by notifiable_id = the recipient). No separate
 * table, and no single global read flag: a Designer viewing their comments
 * only marks their own rows read — a Designer Head's independent rows for
 * the same task are untouched. unreadCountFor() (the in-page "N new
 * comments" banner) excludes clarification-flagged rows — they have their
 * own conversation (Overview → Clarification) — but markReadFor() clears
 * both, since opening the ticket should zero out this task's bell count.
 */
class CommentReadStateService
{
    public function unreadCountFor(User $user, DesignTask $task): int
    {
        return $this->normalCommentQuery($user, $task)->count();
    }

    /**
     * Marks ALL of this task's comment notifications read for this user —
     * including clarification-flagged ones. unreadCountFor() still excludes
     * clarification from the banner count (it has its own conversation UI),
     * but opening the ticket at all means the bell's unread count for this
     * task should drop to zero, not just the normal-comment portion of it.
     */
    public function markReadFor(User $user, DesignTask $task): void
    {
        $user->unreadNotifications()
            ->where('type', TaskCommentNotification::class)
            ->where('data->task_id', $task->id)
            ->update(['read_at' => now()]);
    }

    private function normalCommentQuery(User $user, DesignTask $task)
    {
        return $user->unreadNotifications()
            ->where('type', TaskCommentNotification::class)
            ->where('data->task_id', $task->id)
            ->where(function ($query) {
                // Missing key on older, pre-existing rows means "not
                // clarification" (they predate this distinction and were
                // always normal comments).
                $query->whereNull('data->is_clarification')
                    ->orWhere('data->is_clarification', false);
            });
    }
}
