<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $requests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,vertical,status,total_creatives,designer_id',
                'requester:id,name',
                'targetDesigner:id,name',
                'approvedDesigner:id,name',
                'designerHeadActor:id,name',
                'adminActor:id,name',
            ])
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

        $pendingStatuses = ['pending_approval', 'pending_designer_head', 'pending_admin'];

        $stats = [
            'total' => $requests->count(),
            'pending' => $requests->whereIn('overall_status', $pendingStatuses)->count(),
            'approved' => $requests->where('overall_status', 'approved')->count(),
            'rejected' => $requests->where('overall_status', 'rejected')->count(),
        ];

        $byType = [
            'decline' => $requests->where('request_type', 'decline')->count(),
            'split' => $requests->where('request_type', 'split')->count(),
            'swap' => $requests->where('request_type', 'swap')->count(),
        ];

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('designer-head.dashboard', [
            'requests' => $requests,
            'stats' => $stats,
            'byType' => $byType,
            'splitTasks' => $splitTasks,
            'pendingStatuses' => $pendingStatuses,
            'designers' => $designers,
        ]);
    }
}
