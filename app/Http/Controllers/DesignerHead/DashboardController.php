<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $requests = DesignTaskRequest::query()
            ->with(['task:id,task_id,task_name,vertical,status', 'requester:id,name', 'targetDesigner:id,name'])
            ->latest()
            ->get();

        $splitTaskIds = $requests
            ->where('request_type', 'split')
            ->pluck('split_details.created_task_id')
            ->filter()
            ->values();

        $splitTasks = DesignTask::query()
            ->whereIn('id', $splitTaskIds)
            ->get(['id', 'task_id'])
            ->keyBy('id');

        $stats = [
            'total' => $requests->count(),
            'pending_designer_head' => $requests->where('overall_status', 'pending_designer_head')->count(),
            'pending_admin' => $requests->where('overall_status', 'pending_admin')->count(),
            'approved' => $requests->where('overall_status', 'approved')->count(),
            'rejected' => $requests->where('overall_status', 'rejected')->count(),
        ];

        $byType = [
            'decline' => $requests->where('request_type', 'decline')->count(),
            'split' => $requests->where('request_type', 'split')->count(),
            'swap' => $requests->where('request_type', 'swap')->count(),
        ];

        return view('designer-head.dashboard', [
            'requests' => $requests,
            'stats' => $stats,
            'byType' => $byType,
            'splitTasks' => $splitTasks,
        ]);
    }
}
