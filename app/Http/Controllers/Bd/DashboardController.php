<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'bd', 403);

        $bdId = (int) $request->user()->id;

        $tasks = DesignTask::query()
            ->with(['designer:id,name'])
            ->where('assigned_by', $bdId)
            ->latest('assigned_at')
            ->get();

        // Hide the read-only original shadow created after an approved swap.
        $tasks = $tasks
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();

        $taskIds = $tasks->pluck('id');

        $requests = $taskIds->isEmpty()
            ? collect()
            : DesignTaskRequest::query()
                ->with([
                    'task:id,task_id,task_name',
                    'requester:id,name',
                    'targetDesigner:id,name',
                    'approvedDesigner:id,name',
                ])
                ->whereIn('design_task_id', $taskIds)
                ->latest()
                ->get();

        $pendingStatuses = ['pending_approval', 'pending_designer_head', 'pending_admin'];

        $pendingRequests = $requests
            ->whereIn('overall_status', $pendingStatuses)
            ->values();

        $now = now();

        $stats = [
            'total' => $tasks->count(),
            'assigned' => $tasks->where('status', 'assigned_tasks')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'pending_approval' => $pendingRequests->count(),
            'overdue' => $tasks
                ->filter(fn (DesignTask $task) =>
                    $task->status !== 'completed'
                    && $task->due_at
                    && $task->due_at->lt($now)
                )
                ->count(),
        ];

        $statusLabels = DesignTaskStatusService::STATUSES;
        unset($statusLabels['swap_tasks']);
        $statusLabels['swap_tasks'] = 'Swapped Tasks';

        $statusSummary = collect($statusLabels)
            ->map(function (string $label, string $status) use ($tasks) {
                return [
                    'key' => $status,
                    'label' => $label,
                    'count' => $tasks->where('status', $status)->count(),
                ];
            })
            ->values();

        $requestSummary = [
            'swap' => $pendingRequests->where('request_type', 'swap')->count(),
            'split' => $pendingRequests->where('request_type', 'split')->count(),
            'decline' => $pendingRequests->where('request_type', 'decline')->count(),
        ];

        $verticalSummary = $tasks
            ->groupBy('vertical')
            ->map(fn (Collection $items, string $vertical) => [
                'vertical' => $vertical,
                'label' => ucwords(str_replace('_', ' ', $vertical)),
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return view('bd.dashboard', [
            'stats' => $stats,
            'recentTasks' => $tasks->take(12),
            'recentRequests' => $requests->take(8),
            'requestSummary' => $requestSummary,
            'statusSummary' => $statusSummary,
            'verticalSummary' => $verticalSummary,
            'totalTasks' => max(1, $tasks->count()),
        ]);
    }
}
