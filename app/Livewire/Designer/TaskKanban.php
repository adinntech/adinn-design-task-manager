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
            ->where('designer_id', Auth::id())
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

        $taskIds = $tasks->pluck('id');

        $requests = DesignTaskRequest::query()
            ->whereIn('design_task_id', $taskIds)
            ->whereIn('request_type', ['split', 'swap', 'decline'])
            ->latest('created_at')
            ->get()
            ->groupBy('design_task_id');

        return $tasks->mapWithKeys(function (DesignTask $task) use ($requests) {
            $tags = collect();
            $taskRequests = $requests->get($task->id, collect());

            // Child tasks produced by an approved split always carry the SPLIT tag.
            if (! empty(data_get($task->requirements, '_split_request_id')) ||
                ! empty(data_get($task->requirements, '_split_from_task_id'))) {
                $tags->push([
                    'key' => 'split',
                    'label' => 'Split',
                    'class' => 'task-tag-split',
                ]);
            }

            foreach (['split', 'swap', 'decline'] as $type) {
                $request = $taskRequests->firstWhere('request_type', $type);

                if (! $request) {
                    continue;
                }

                if ($request->overall_status === 'approved') {
                    $tags->push(match ($type) {
                        'split' => ['key' => 'split', 'label' => 'Split', 'class' => 'task-tag-split'],
                        'swap' => ['key' => 'swap', 'label' => 'Swapped', 'class' => 'task-tag-swap'],
                        default => ['key' => 'decline', 'label' => 'Declined', 'class' => 'task-tag-decline'],
                    });

                    continue;
                }

                if (in_array($request->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true)) {
                    $tags->push(match ($type) {
                        'split' => ['key' => 'split-pending', 'label' => 'Split Requested', 'class' => 'task-tag-pending'],
                        'swap' => ['key' => 'swap-pending', 'label' => 'Swap Requested', 'class' => 'task-tag-pending'],
                        default => ['key' => 'decline-pending', 'label' => 'Decline Requested', 'class' => 'task-tag-pending'],
                    });
                }
            }

            return [$task->id => $tags->unique('key')->values()->all()];
        });
    }

    public function render()
    {
        $tasks = $this->tasks;

        return view('livewire.designer.task-kanban', [
            'statuses' => DesignTaskStatusService::STATUSES,
            'tasks' => $tasks,
            'taskTags' => $this->buildTaskTags($tasks),
        ]);
    }
}
