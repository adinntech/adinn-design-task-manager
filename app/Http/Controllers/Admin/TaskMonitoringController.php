<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term);
                });
            })
            ->when($request->filled('vertical'), fn ($query) => $query->where('vertical', $request->input('vertical')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('designer_id'), fn ($query) => $query->where('designer_id', $request->input('designer_id')))
            ->latest('assigned_at')
            ->paginate(20)
            ->withQueryString();

        $designers = User::where('role', 'designer')->orderBy('name')->get(['id', 'name']);
        $statuses = DesignTaskStatusService::STATUSES;

        return view('admin.tasks.index', compact('tasks', 'designers', 'statuses'));
    }

    public function show(DesignTask $task): View
    {
        $task->load(['designer:id,name,email', 'assigner:id,name,email']);

        $history = DesignTaskStatusHistory::query()
            ->with('changedBy:id,name')
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $comments = DesignTaskComment::query()
            ->with(['user:id,name', 'attachments'])
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $statuses = DesignTaskStatusService::STATUSES;

        return view('admin.tasks.show', compact('task', 'history', 'comments', 'statuses'));
    }
    public function destroy(DesignTask $task): RedirectResponse
    {
        $taskId = $task->task_id;
        $taskName = $task->task_name;

        $task->delete();

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', "Task {$taskId} - {$taskName} deleted successfully.");
    }

}
