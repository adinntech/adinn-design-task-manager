<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\User;
use App\Notifications\TaskCommentNotification;

/**
 * Per-recipient unread state for NORMAL comments only, reusing the existing
 * per-user `notifications` table rows created by TaskCommentNotification
 * (each row is already keyed by notifiable_id = the recipient). No separate
 * table, and no single global read flag: a Designer viewing their comments
 * only marks their own rows read — a Designer Head's independent rows for
 * the same task are untouched. Clarification-sourced comments carry
 * data->is_clarification = true and are excluded — they have their own
 * conversation (Overview → Clarification) and are not part of this count.
 */
class CommentReadStateService
{
    public function unreadCountFor(User $user, DesignTask $task): int
    {
        return $this->normalCommentQuery($user, $task)->count();
    }

    public function markReadFor(User $user, DesignTask $task): void
    {
        $this->normalCommentQuery($user, $task)->update(['read_at' => now()]);
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
