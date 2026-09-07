<?php

namespace App\Http\Controllers;

use App\Models\UserActivityFlag;
use App\Services\TaskNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActivityFlagController extends Controller
{
    /**
     * Clear the current user's "needs refresh" flag, called only from an
     * explicit Refresh-button click — never automatically. The shared
     * task-list Refresh button always posts scope "tasks", which expands to
     * every list-relevant category (assignment/status/rework/rating/split/
     * swap/decline) — a comment never moves a task between list/board
     * columns, so it is never included here and never cleared by this.
     *
     * Clicking Refresh also marks the matching category's unread
     * notifications as read (bell count drops by exactly that category's
     * count), so a later "Mark all as read" or opening a task's Comments tab
     * never double-counts what Refresh already accounted for.
     */
    public function ack(Request $request, string $scope = 'tasks'): Response
    {
        $categories = $scope === 'tasks' ? TaskNotificationService::LIST_CATEGORIES : [$scope];
        $userId = $request->user()->id;

        UserActivityFlag::query()
            ->where('user_id', $userId)
            ->whereIn('scope', $categories)
            ->update(['flagged_at' => null]);

        $request->user()->unreadNotifications()
            ->where(function ($query) use ($categories) {
                foreach ($categories as $category) {
                    $query->orWhere('data->category', $category);
                }
            })
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
