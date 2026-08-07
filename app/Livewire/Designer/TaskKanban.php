<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Services\DesignTaskStatusService;
use Illuminate\Database\Eloquent\Collection;
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
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('due_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.designer.task-kanban', [
            'statuses' => DesignTaskStatusService::STATUSES,
            'tasks' => $this->tasks,
        ]);
    }
}
