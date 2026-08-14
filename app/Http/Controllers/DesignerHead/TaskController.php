<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;

class TaskController extends Controller
{
    public function show(DesignTask $task)
    {
        $task->load([
            'designer:id,name,email',
            'assigner:id,name,email',
        ]);

        $requests = DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->with([
                'requester:id,name',
                'targetDesigner:id,name',
                'approvedDesigner:id,name',
                'designerHeadActor:id,name',
                'adminActor:id,name',
            ])
            ->latest()
            ->get();

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('designer-head.tasks.show', [
            'task' => $task,
            'requests' => $requests,
            'designers' => $designers,
        ]);
    }
}
