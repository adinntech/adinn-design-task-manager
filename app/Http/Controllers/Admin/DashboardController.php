<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Global dashboard search — Admin has no scope restriction (sees every
     * task, same as index()'s unscoped stats), so this is a plain DB-side
     * query rather than an in-memory filter (nothing is preloaded here).
     */
    public function fragment(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $recentTasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('task_id', 'like', $term)
                        ->orWhere('zoho_project_number', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term)
                        ->orWhere('mobile_number', 'like', $term)
                        ->orWhere('vertical', 'like', $term)
                        ->orWhere('task_nature', 'like', $term)
                        ->orWhere('priority', 'like', $term)
                        ->orWhere('status', 'like', $term)
                        ->orWhereHas('designer', fn ($dq) => $dq->where('name', 'like', $term))
                        ->orWhereHas('assigner', fn ($aq) => $aq->where('name', 'like', $term));
                });
            })
            ->latest('assigned_at')
            ->limit($search !== '' ? 50 : 8)
            ->get();

        return view('admin.dashboard-recent-tasks', [
            'recentTasks' => $recentTasks,
            'search' => $search,
        ]);
    }

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

        $pendingRequests = DesignTaskRequest::query()
            ->pending()
            ->where('request_type', '!=', 'decline')
            ->with([
                'task:id,task_id,task_name,vertical,status,total_creatives,designer_id',
                'requester:id,name',
                'targetDesigner:id,name',
                'approvedDesigner:id,name',
            ])
            ->latest()
            ->limit(10)
            ->get();

        $approvalDesigners = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestStats = [
            'pending' => DesignTaskRequest::query()->pending()->count(),
            'decline' => DesignTaskRequest::query()->pending()->where('request_type', 'decline')->count(),
            'split' => DesignTaskRequest::query()->pending()->where('request_type', 'split')->count(),
            'swap' => DesignTaskRequest::query()->pending()->where('request_type', 'swap')->count(),
        ];

        return view('admin.dashboard', compact(
            'stats',
            'pipeline',
            'recentTasks',
            'designerWorkload',
            'recentActivity',
            'pendingRequests',
            'requestStats',
            'approvalDesigners'
        ));
    }
}
