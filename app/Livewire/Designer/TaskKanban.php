<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
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
    public string $bdId = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer', 403);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
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

    private function filterArray(): array
    {
        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $this->priority,
            'designerId' => (string) Auth::id(),
            'bdId' => $this->bdId,
            'period' => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ];
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->vertical = '';
        $this->priority = '';
        $this->bdId = '';
        $this->period = 'current_month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Human-readable "Filter: value" chips for whichever filters are
     * currently non-default, same idea as the Designer Head board.
     */
    private function appliedFilters(SupportCollection $bds, string $periodLabel): SupportCollection
    {
        $chips = collect();

        if ($this->search !== '') {
            $chips->push(['label' => 'Search', 'value' => $this->search]);
        }
        if ($this->bdId !== '') {
            $chips->push(['label' => 'BD', 'value' => $bds->firstWhere('id', (int) $this->bdId)?->name ?? '—']);
        }
        if ($this->vertical !== '') {
            $chips->push(['label' => 'Vertical', 'value' => ucwords(str_replace('_', ' ', $this->vertical))]);
        }
        if ($this->priority !== '') {
            $chips->push(['label' => 'Priority', 'value' => ucfirst($this->priority)]);
        }
        if ($this->period !== 'current_month') {
            $chips->push(['label' => 'Period', 'value' => $periodLabel]);
        }

        return $chips;
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
                        ->orWhere('task_nature', 'like', $term)
                        ->orWhereHas('designer', fn ($designerQuery) => $designerQuery->where('name', 'like', $term))
                        ->orWhereHas('assigner', fn ($assignerQuery) => $assignerQuery->where('name', 'like', $term));
                });
            })
            ->when($this->vertical !== '', fn ($query) => $query->where('vertical', $this->vertical))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->bdId !== '', fn ($query) => $query->where('assigned_by', $this->bdId))
            ->orderBy('due_at')
            ->get()
            ->each(fn (DesignTask $task) => $task->status = 'self_declined');
    }

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
            if (data_get($task->requirements, '_split_from_task_id')) {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
            }

            $latestRequest = $requests->get($task->id, collect())->first();

            if (! $latestRequest) {
                return [$task->id => []];
            }

            if ($latestRequest->request_type === 'split' && $latestRequest->overall_status === 'approved') {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
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

    /**
     * BD users who actually assigned/own a task related to this Designer —
     * currently assigned to them, or self-declined away from them — never
     * the full BD user list.
     */
    private function relatedBds(): SupportCollection
    {
        return User::query()
            ->where('role', 'bd')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('createdTasks', fn ($q) => $q->where('designer_id', Auth::id()))
                    ->orWhereHas('createdTasks', function ($q) {
                        $q->whereHas('requests', function ($requestQuery) {
                            $requestQuery->where('request_type', 'decline')
                                ->where('requested_by', Auth::id())
                                ->where('overall_status', 'approved');
                        });
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function render()
    {
        $bds = $this->relatedBds();
        $board = app(DesignerHeadTaskBoardService::class)->build($this->filterArray());

        $ownTasks = $board['tasks'];
        $visibleOwnTasks = $board['visibleTasks'];
        $periodStart = $board['periodStart'];

        $periodLabel = $this->period === 'custom'
            ? $periodStart->format('d M Y').' – '.$board['periodEnd']->format('d M Y')
            : $periodStart->format('M Y');

        $statuses = $board['statuses'];
        unset($statuses['decline_tasks']);
        $statuses['self_declined'] = 'Self Declined';

        $tasks = $visibleOwnTasks->concat($this->selfDeclinedTasks);

        return view('livewire.designer.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'taskTags' => $this->buildTaskTags($tasks),
            'bds' => $bds,
            'periodLabel' => $periodLabel,
            'appliedFilters' => $this->appliedFilters($bds, $periodLabel),
            'activeBreakdown' => $board['activeBreakdown'],
            'stats' => [
                'total' => $ownTasks->count(),
                'active' => $ownTasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $ownTasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $ownTasks->where('status', 'completed')->count(),
            ],
        ]);
    }
}
