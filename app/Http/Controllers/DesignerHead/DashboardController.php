<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;

class DashboardController extends Controller
{
    private const COLUMNS = [
        'assigned_tasks' => 'Assigned Tasks',
        'review_analysis' => 'Review & Analysis',
        'need_clarification' => 'Need Clarification',
        'yet_to_start' => 'Yet to Start',
        'in_progress' => 'In Progress',
        'waiting_confirmation' => 'Waiting Confirmation',
        'rework' => 'Rework',
        'completed' => 'Completed',
        'swap_tasks' => 'Swap Tasks',
    ];

    public function index()
    {
        $pendingRequests = DesignTaskRequest::query()
            ->pending()
            ->with([
                'task:id,task_id,task_name,vertical,status,priority,due_at,designer_id,total_creatives',
                'task.designer:id,name',
                'requester:id,name',
                'targetDesigner:id,name',
            ])
            ->latest()
            ->get();

        $tasks = DesignTask::query()
            ->with(['designer:id,name'])
            ->latest('assigned_at')
            ->get([
                'id',
                'task_id',
                'task_name',
                'vertical',
                'task_nature',
                'priority',
                'due_at',
                'designer_id',
                'total_creatives',
                'status',
                'assigned_at',
                'requirements',
            ]);

        $tasksByStatus = [];
        foreach (self::COLUMNS as $status => $label) {
            $tasksByStatus[$status] = $tasks->where('status', $status)->values();
        }

        return view('designer-head.dashboard', [
            'pendingRequests' => $pendingRequests,
            'tasksByStatus' => $tasksByStatus,
            'columns' => self::COLUMNS,
            'totalTasks' => $tasks->count(),
            'pendingRequestCount' => $pendingRequests->count(),
        ]);
    }
}
