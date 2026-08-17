<?php

namespace App\Livewire\DesignerHead;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskStatusService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';
    public string $vertical = '';
    public string $priority = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer_head', 403);
    }

    public function getTasksProperty(): Collection
    {
        $tasks = DesignTask::query()
            ->with([
                'designer:id,name',
                'assigner:id,name',
            ])
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(function ($query) use ($term) {
                    $query->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        ->orWhere('vertical', 'like', $term)
                        ->orWhere('task_nature', 'like', $term)
                        ->orWhereHas('designer', fn ($designerQuery) => $designerQuery->where('name', 'like', $term));
                });
            })
            ->when($this->vertical !== '', fn ($query) => $query->where('vertical', $this->vertical))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();

        return $tasks
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();
    }

    public function getPendingRequestsProperty()
    {
        return DesignTaskRequest::query()
            ->pending()
            ->whereIn('request_type', ['decline', 'split', 'swap'])
            ->with([
                'task:id,task_id,task_name,status,priority,due_at,designer_id,party_name,vertical',
                'task.designer:id,name',
                'requester:id,name',
                'targetDesigner:id,name',
            ])
            ->latest()
            ->get();
    }

    private function buildTaskTags(Collection $tasks): SupportCollection
    {
        if ($tasks->isEmpty()) {
            return collect();
        }

        $requests = DesignTaskRequest::query()
            ->whereIn('design_task_id', $tasks->pluck('id'))
            ->whereIn('request_type', ['split', 'swap'])
            ->latest('created_at')
            ->get()
            ->groupBy('design_task_id');

        return $tasks->mapWithKeys(function (DesignTask $task) use ($requests) {
            $tags = collect();
            $taskRequests = $requests->get($task->id, collect());

            if (! empty(data_get($task->requirements, '_swapped_from_task_id'))) {
                $tags->push(['key' => 'swap', 'label' => 'Swapped', 'class' => 'task-tag-swap']);
            }

            if (
                ! empty(data_get($task->requirements, '_split_request_id'))
                || ! empty(data_get($task->requirements, '_split_from_task_id'))
            ) {
                $tags->push(['key' => 'split', 'label' => 'Split Approved', 'class' => 'task-tag-split']);
            }

            foreach (['split', 'swap'] as $type) {
                $approved = $taskRequests->first(
                    fn ($request) => $request->request_type === $type
                        && $request->overall_status === 'approved'
                );

                if ($approved) {
                    $tags->push([
                        'key' => $type,
                        'label' => $type === 'split' ? 'Split Approved' : 'Swap Approved',
                        'class' => $type === 'split' ? 'task-tag-split' : 'task-tag-swap',
                    ]);
                    continue;
                }

                $latest = $taskRequests->firstWhere('request_type', $type);
                if (! $latest) {
                    continue;
                }

                if (in_array($latest->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true)) {
                    $tags->push([
                        'key' => $type,
                        'label' => $type === 'swap' ? 'Waiting for Approval' : 'Split Pending',
                        'class' => 'task-tag-pending',
                    ]);
                } elseif ($latest->overall_status === 'rejected') {
                    $tags->push([
                        'key' => $type,
                        'label' => ucfirst($type).' Declined',
                        'class' => 'task-tag-decline',
                    ]);
                }
            }

            return [$task->id => $tags->unique('key')->values()->all()];
        });
    }

    public function render()
    {
        $tasks = $this->tasks;
        $statuses = DesignTaskStatusService::STATUSES;

        if (! array_key_exists('swap_tasks', $statuses)) {
            $statuses['swap_tasks'] = 'Swapped Tasks';
        } else {
            unset($statuses['swap_tasks']);
            $statuses['swap_tasks'] = 'Swapped Tasks';
        }

        return view('livewire.designer-head.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'pendingRequests' => $this->pendingRequests,
            'taskTags' => $this->buildTaskTags($tasks),
            'stats' => [
                'total' => $tasks->count(),
                'active' => $tasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $tasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
        ]);
    }
}
