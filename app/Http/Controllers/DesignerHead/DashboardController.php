<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer_head', 403);

        $now = now();

        $tasks = DesignTask::query()
            ->with(['designer:id,name'])
            ->get()
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();

        $pendingStatuses = ['pending_approval', 'pending_designer_head', 'pending_admin'];

        $requests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,designer_id,status,priority',
                'task.designer:id,name',
                'requester:id,name',
                'targetDesigner:id,name',
                'approvedDesigner:id,name',
            ])
            ->latest()
            ->get();

        $pendingRequests = $requests
            ->whereIn('overall_status', $pendingStatuses)
            ->values();

        $submittedToday = DesignTaskEodRecord::query()
            ->whereDate('submitted_at', $now->toDateString())
            ->distinct('design_task_id')
            ->count('design_task_id');

        $stats = [
            'total' => $tasks->count(),
            'new_assignments' => $tasks->where('status', 'assigned_tasks')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'submitted_today' => $submittedToday,
            'pending_review' => $tasks->where('status', 'waiting_confirmation')->count(),
            'overdue' => $tasks
                ->filter(fn (DesignTask $task) =>
                    $task->status !== 'completed'
                    && $task->due_at
                    && $task->due_at->lt($now)
                )
                ->count(),
            'approval_pending' => $pendingRequests->count(),
        ];

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $workload = $designers->map(function (User $designer) use ($tasks, $now) {
            $designerTasks = $tasks->where('designer_id', $designer->id);
            $active = $designerTasks->whereNotIn('status', ['completed'])->count();
            $completed = $designerTasks->where('status', 'completed')->count();
            $overdue = $designerTasks->filter(fn (DesignTask $task) =>
                $task->status !== 'completed'
                && $task->due_at
                && $task->due_at->lt($now)
            )->count();

            // Compact visual indicator, capped at 100%.
            $loadPercent = min(100, $active * 10);

            return [
                'designer' => $designer,
                'active' => $active,
                'completed' => $completed,
                'overdue' => $overdue,
                'load_percent' => $loadPercent,
                'status' => match (true) {
                    $loadPercent >= 80 => 'Busy',
                    $loadPercent >= 50 => 'Working',
                    default => 'Available',
                },
            ];
        })->sortByDesc('active')->values();

        return view('designer-head.dashboard', [
            'stats' => $stats,
            'workload' => $workload,
            'swapRequests' => $requests->where('request_type', 'swap')->take(6)->values(),
            'splitRequests' => $requests->where('request_type', 'split')->take(6)->values(),
        ]);
    }
}
