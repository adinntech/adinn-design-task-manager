<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer', 403);

        $designerId = (int) $request->user()->id;

        $tasks = DesignTask::query()
            ->with(['assigner:id,name'])
            ->where('designer_id', $designerId)
            ->latest('assigned_at')
            ->get();

        $ownRequests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,designer_id',
                'task.designer:id,name',
                'approvedDesigner:id,name',
                'targetDesigner:id,name',
            ])
            ->where('requested_by', $designerId)
            ->latest()
            ->get();

        $pendingStatuses = ['pending_approval', 'pending_designer_head', 'pending_admin'];
        $pendingRequests = $ownRequests->whereIn('overall_status', $pendingStatuses)->values();

        $now = now();

        $stats = [
            'total' => $tasks->count(),
            'assigned' => $tasks->where('status', 'assigned_tasks')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'pending_approval' => $pendingRequests->count(),
            'overdue' => $tasks
                ->filter(fn (DesignTask $task) => $task->status !== 'completed' && $task->due_at && $task->due_at->lt($now))
                ->count(),
        ];

        $statusLabels = DesignTaskStatusService::STATUSES;
        unset($statusLabels['swap_tasks']);
        $statusLabels['swap_tasks'] = 'Swapped Tasks';

        $statusSummary = collect($statusLabels)
            ->map(fn (string $label, string $status) => [
                'key' => $status,
                'label' => $label,
                'count' => $tasks->where('status', $status)->count(),
            ])
            ->values();

        $requestSummary = [
            'swap' => $pendingRequests->where('request_type', 'swap')->count(),
            'split' => $pendingRequests->where('request_type', 'split')->count(),
            'decline' => $pendingRequests->where('request_type', 'decline')->count(),
        ];

        $requestTypeCounts = [
            'swap' => $ownRequests->where('request_type', 'swap')->count(),
            'decline' => $ownRequests->where('request_type', 'decline')->count(),
            'split' => $ownRequests->where('request_type', 'split')->count(),
            'approved' => $ownRequests->where('overall_status', 'approved')->count(),
        ];

        return view('designer.dashboard', [
            'stats' => $stats,
            'recentTasks' => $tasks->take(12),
            'myRequests' => $ownRequests->take(20),
            'requestSummary' => $requestSummary,
            'requestTypeCounts' => $requestTypeCounts,
            'statusSummary' => $statusSummary,
        ]);
    }
}
