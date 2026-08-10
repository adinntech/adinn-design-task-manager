<?php

namespace App\Livewire\Designer;

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
        abort_unless(Auth::user()?->role === 'designer', 403);
    }

    public function moveTask(int $taskId, string $targetStatus): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('designer_id', Auth::id())
            ->firstOrFail();

        app(DesignTaskStatusService::class)
            ->moveAsDesigner($task, Auth::user(), $targetStatus, 'kanban_drag');

        $this->dispatch('kanban-updated');
        $this->dispatch('task-status-changed', message: 'Task status updated successfully.');
    }

    public function getTasksProperty(): Collection
    {
        return DesignTask::query()
            ->with(['assigner:id,name'])
            ->where(function ($query) {
                $query->where('designer_id', Auth::id())
                    ->orWhereHas('requests', function ($requestQuery) {
                        $requestQuery->where('request_type', 'swap')
                            ->where('requested_by', Auth::id())
                            ->where('overall_status', 'approved');
                    });
            })
            ->when($this->search !== '', function ($query) {
                $term = '%'.trim($this->search).'%';

                $query->where(function ($query) use ($term) {
                    $query->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        ->orWhere('vertical', 'like', $term)
                        ->orWhere('task_nature', 'like', $term);
                });
            })
            ->when($this->vertical !== '', fn ($query) => $query->where('vertical', $this->vertical))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Build compact request/history tags for the visible cards.
     *
     * The original task keeps its SPLIT tag after an approved split request,
     * while any child task created from that request also receives the same tag.
     */
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
                $tags->push([
                    'key' => 'swap',
                    'label' => 'Swapped',
                    'class' => 'task-tag-swap',
                ]);
            }

            if (! empty(data_get($task->requirements, '_split_request_id')) ||
                ! empty(data_get($task->requirements, '_split_from_task_id'))) {
                $tags->push([
                    'key' => 'split',
                    'label' => 'Split Approved',
                    'class' => 'task-tag-split',
                ]);
            }

            foreach (['split', 'swap'] as $type) {
                $approved = $taskRequests->first(fn ($request) =>
                    $request->request_type === $type && $request->overall_status === 'approved'
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
                        'label' => $type === 'swap' ? 'Waiting for Approval' : ucfirst($type).' Pending',
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

        // Swap Tasks is a special holding stage and should always be the final Kanban column.
        if (array_key_exists('swap_tasks', $statuses)) {
            $swapLabel = $statuses['swap_tasks'];
            unset($statuses['swap_tasks']);
            $statuses['swap_tasks'] = $swapLabel;
        }

        return view('livewire.designer.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'taskTags' => $this->buildTaskTags($tasks),
        ]);
    }
}
