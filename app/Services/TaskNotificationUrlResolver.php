<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for "given a notified user and a task id, which
 * role-specific task-show route does this open?" — shared by every place
 * that needs to link to a task from a notification.
 *
 * The task pages all read ?tab=<key> (overview, comments, eod, ratings,
 * history, printing-file / printing, <request>-details). A tab is only used
 * when the page actually renders it for the task's current status; otherwise
 * the link falls back to the default Overview tab.
 */
class TaskNotificationUrlResolver
{
    private const PROGRESS_STATUSES = ['in_progress', 'waiting_confirmation', 'rework', 'completed', 'prepare_printing_file'];

    private const FINISHED_STATUSES = ['completed', 'prepare_printing_file'];

    private const REQUEST_TABS = [
        'split' => 'split-details',
        'swap' => 'swap-details',
        'decline' => 'decline-details',
        'status_change' => 'status-change-request',
    ];

    public static function forTask(User $user, int $taskId, ?string $tab = null): ?string
    {
        $routeName = match ($user->role) {
            'bd' => 'bd.tasks.show',
            'designer' => 'designer.tasks.show',
            'designer_head' => 'designer-head.tasks.show',
            'admin' => 'admin.tasks.show',
            default => null,
        };

        if ($routeName === null || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName, array_filter(['task' => $taskId, 'tab' => $tab]));
    }

    /**
     * @param  string  $key  comment | rework | rating | review | printing | edited | split | swap | decline | status_change
     */
    public static function forEvent(User $user, int $taskId, string $key): ?string
    {
        return self::forTask($user, $taskId, self::tabFor($user, DesignTask::query()->find($taskId, ['id', 'status']), $key));
    }

    /** Maps a stored database notification to the tab that shows what it is about. */
    public static function forNotificationData(User $user, array $data): ?string
    {
        $taskId = (int) ($data['task_id'] ?? 0);

        if ($taskId < 1) {
            return null;
        }

        $category = (string) ($data['category'] ?? '');

        $key = match (true) {
            $category === 'comment' && ! ($data['is_clarification'] ?? false) => 'comment',
            $category === 'rework' => 'rework',
            $category === 'rating' => 'rating',
            $category === 'task_edit' => 'edited',
            $category === 'status' && ($data['to_status'] ?? null) === 'waiting_confirmation' => 'review',
            $category === 'status' && ($data['to_status'] ?? null) === 'prepare_printing_file' => 'printing',
            isset(self::REQUEST_TABS[$category]) => $category,
            default => null,
        };

        return $key === null ? self::forTask($user, $taskId) : self::forEvent($user, $taskId, $key);
    }

    public static function tabFor(User $user, ?DesignTask $task, string $key): ?string
    {
        $status = $task?->status;

        return match ($key) {
            'comment' => 'comments',
            'edited' => 'history',
            'rework', 'review' => in_array($status, self::PROGRESS_STATUSES, true) ? 'eod' : null,
            'rating' => in_array($status, self::FINISHED_STATUSES, true) ? 'ratings' : (in_array($status, self::PROGRESS_STATUSES, true) ? 'eod' : null),
            'printing' => match (true) {
                $status !== 'prepare_printing_file' => null,
                $user->role === 'designer' => 'printing-file',
                $user->role === 'admin' => 'printing',
                default => 'eod',
            },
            'status_change' => $user->role === 'admin' ? null : self::REQUEST_TABS['status_change'],
            'split', 'swap', 'decline' => self::REQUEST_TABS[$key],
            default => null,
        };
    }
}
