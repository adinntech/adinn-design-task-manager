<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for "given a notified user and a task id, which
 * role-specific task-show route does this open?" — shared by every place
 * that needs to link to a task from a notification.
 */
class TaskNotificationUrlResolver
{
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
}
