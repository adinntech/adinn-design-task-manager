<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();

        $stats = [
            'total_tasks' => DesignTask::count(),
            'active_tasks' => DesignTask::where('status', '!=', 'completed')->count(),
            'waiting_confirmation' => DesignTask::where('status', 'waiting_confirmation')->count(),
            'rework' => DesignTask::where('status', 'rework')->count(),
            'overdue' => DesignTask::where('status', '!=', 'completed')->where('due_at', '<', $now)->count(),
            'completed' => DesignTask::where('status', 'completed')->count(),
            'active_designers' => User::where('role', 'designer')->where('is_active', true)->count(),
            'active_bd' => User::where('role', 'bd')->where('is_active', true)->count(),
        ];

        $pipeline = collect(DesignTaskStatusService::STATUSES)->mapWithKeys(
            fn ($label, $key) => [$key => [
                'label' => $label,
                'count' => DesignTask::where('status', $key)->count(),
            ]]
        );

        $recentTasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name'])
            ->latest('assigned_at')
            ->limit(8)
            ->get();

        $designerWorkload = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->withCount([
                'assignedTasks as active_tasks_count' => fn ($query) => $query->where('status', '!=', 'completed'),
                'assignedTasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
            ])
            ->orderByDesc('active_tasks_count')
            ->limit(8)
            ->get();

        $recentActivity = DesignTaskStatusHistory::query()
            ->with(['task:id,task_id,task_name', 'changedBy:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'pipeline',
            'recentTasks',
            'designerWorkload',
            'recentActivity'
        ));
    }
}
