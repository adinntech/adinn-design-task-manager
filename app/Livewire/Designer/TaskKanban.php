<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskStatusService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

        try {
            app(DesignTaskStatusService::class)
                ->moveAsDesigner($task, Auth::user(), $targetStatus, 'kanban_drag');
        } catch (ValidationException $e) {
            $progressService = app(DesignTaskProgressService::class);

            if (
                $task->status === 'in_progress'
                && $targetStatus === 'waiting_confirmation'
                && $progressService->percentage($task) < 100
            ) {
                $completed = $progressService->completed($task);
                $remaining = $progressService->remaining($task);

                $this->dispatch('task-move-blocked', message: 'Creative Work Incomplete: Complete all creatives before BD Review. '
                    .$completed.' of '.$task->total_creatives.' completed • '.$remaining.' remaining.');

                return;
            }

            throw $e;
        }

        $this->dispatch('kanban-updated');
        $this->dispatch('task-status-changed', message: 'Task status updated successfully.');
    }

    public function getTasksProperty(): Collection
    {
        return DesignTask::query()
            ->with([
                'bdReview','assigner:id,name'])
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
     * Tasks this Designer declined that Designer Head approved for reassignment.
     * Shown as a read-only "Self Declined" column; the real designer_id no
     * longer points at this Designer, so status is overridden in-memory only.
     */
    public function getSelfDeclinedTasksProperty(): Collection
    {
        return DesignTask::query()
            ->with(['designer:id,name'])
            ->where('designer_id', '!=', Auth::id())
            ->whereHas('requests', function ($query) {
                $query->where('request_type', 'decline')
                    ->where('requested_by', Auth::id())
                    ->where('overall_status', 'approved');
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
            ->orderBy('due_at')
            ->get()
            ->each(fn (DesignTask $task) => $task->status = 'self_declined');
    }

    /**
     * Build compact request/history tags for the visible cards.
     *
     * The original task keeps its SPLIT tag after an approved split request,
     * while any child task created from that request also receives the same tag.
     */
    /**
     * Build one compact request-status badge for each visible Kanban card.
     *
     * Only the latest request is shown so the card stays clean while still
     * exposing the current state of Decline, Task Split and Task Transfer requests.
     */
    private function buildTaskTags(Collection $tasks): SupportCollection
    {
        if ($tasks->isEmpty()) {
            return collect();
        }

        $requests = DesignTaskRequest::query()
            ->whereIn('design_task_id', $tasks->pluck('id'))
            ->whereIn('request_type', ['decline', 'split', 'swap'])
            ->latest('created_at')
            ->get()
            ->groupBy('design_task_id');

        return $tasks->mapWithKeys(function (DesignTask $task) use ($requests) {
            $latestRequest = $requests->get($task->id, collect())->first();

            if (! $latestRequest) {
                return [$task->id => []];
            }

            $typeLabel = match ($latestRequest->request_type) {
                'split' => 'Split',
                'swap' => 'Swap',
                'decline' => 'Decline',
                default => 'Request',
            };

            $isPending = in_array(
                $latestRequest->overall_status,
                ['pending_approval', 'pending_designer_head', 'pending_admin'],
                true
            );

            $isDecline = $latestRequest->request_type === 'decline';

            if ($isDecline && $latestRequest->overall_status === 'approved' && (int) $latestRequest->requested_by !== (int) Auth::id()) {
                return [
                    $task->id => [[
                        'key' => 'latest-request',
                        'label' => 'Transferred',
                        'class' => 'task-request-status task-request-approved',
                    ]],
                ];
            }

            $statusLabel = $isPending
                ? 'Pending'
                : ($latestRequest->overall_status === 'approved' ? 'Approved' : ($isDecline ? 'Rejected' : 'Declined'));

            $statusClass = $isPending
                ? 'task-request-pending'
                : ($latestRequest->overall_status === 'approved'
                    ? 'task-request-approved'
                    : 'task-request-declined');

            $separator = ($isDecline && ! $isPending) ? ' ' : ' · ';

            return [
                $task->id => [[
                    'key' => 'latest-request',
                    'label' => $typeLabel.$separator.$statusLabel,
                    'class' => 'task-request-status '.$statusClass,
                ]],
            ];
        });
    }

    public function render()
    {
        $tasks = $this->tasks->concat($this->selfDeclinedTasks);
        $statuses = DesignTaskStatusService::STATUSES;

        unset($statuses['swap_tasks']);
        $statuses['swap_tasks'] = 'Swapped Tasks';
        $statuses['self_declined'] = 'Self Declined';

        return view('livewire.designer.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'taskTags' => $this->buildTaskTags($tasks),
        ]);
    }
}
