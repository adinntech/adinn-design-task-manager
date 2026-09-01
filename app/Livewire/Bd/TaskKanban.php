<?php

namespace App\Livewire\Bd;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';
    public string $vertical = '';
    public string $priority = '';
    public string $designerId = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'bd', 403);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function markRework(int $taskId): void
    {
        $this->moveBdOwnedStatus($taskId, 'rework');
    }

    public function markCompleted(int $taskId): void
    {
        $this->moveBdOwnedStatus($taskId, 'completed');
    }

    private function moveBdOwnedStatus(int $taskId, string $targetStatus): void
    {
        abort_unless(in_array($targetStatus, ['rework', 'completed'], true), 403);

        DB::transaction(function () use ($taskId, $targetStatus) {
            $task = DesignTask::query()
                ->lockForUpdate()
                ->whereKey($taskId)
                ->where('assigned_by', Auth::id())
                ->firstOrFail();

            if ($task->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages([
                    'status' => 'BD can select Rework or Completed only from Waiting for Confirmation.',
                ]);
            }

            $fromStatus = $task->status;

            $task->update([
                'status' => $targetStatus,
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by' => Auth::id(),
                'change_source' => 'bd_action',
                'note' => $targetStatus === 'completed'
                    ? 'Task marked completed by BD.'
                    : 'Task sent for rework by BD.',
            ]);
        });

        $this->dispatch(
            'bd-task-updated',
            message: $targetStatus === 'completed'
                ? 'Task marked as Completed.'
                : 'Task moved to Rework.'
        );
    }

    private function filterArray(): array
    {
        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $this->priority,
            'designerId' => $this->designerId,
            'bdId' => (string) Auth::id(),
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
        $this->designerId = '';
        $this->period = 'current_month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Human-readable "Filter: value" chips for whichever filters are
     * currently non-default, same idea as the Designer Head board.
     */
    private function appliedFilters(SupportCollection $designers, string $periodLabel): SupportCollection
    {
        $chips = collect();

        if ($this->search !== '') {
            $chips->push(['label' => 'Search', 'value' => $this->search]);
        }
        if ($this->designerId !== '') {
            $chips->push(['label' => 'Designer', 'value' => $designers->firstWhere('id', (int) $this->designerId)?->name ?? '—']);
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

            if ($latestRequest->request_type === 'decline' && $latestRequest->overall_status === 'approved') {
                return [
                    $task->id => [[
                        'key' => 'latest-request',
                        'label' => 'Task Transferred',
                        'class' => 'task-request-status task-request-approved',
                    ]],
                ];
            }

            if ($latestRequest->request_type === 'split' && $latestRequest->overall_status === 'approved') {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
            }

            $statusLabel = $isPending
                ? 'Pending'
                : ($latestRequest->overall_status === 'approved' ? 'Approved' : 'Declined');

            $statusClass = $isPending
                ? 'task-request-pending'
                : ($latestRequest->overall_status === 'approved'
                    ? 'task-request-approved'
                    : 'task-request-declined');

            return [
                $task->id => [[
                    'key' => 'latest-request',
                    'label' => $typeLabel.' · '.$statusLabel,
                    'class' => 'task-request-status '.$statusClass,
                ]],
            ];
        });
    }

    public function render()
    {
        $board = app(DesignerHeadTaskBoardService::class)->build($this->filterArray());

        $ownTasks = $board['tasks'];
        $tasks = $board['visibleTasks'];
        $periodStart = $board['periodStart'];

        $periodLabel = $this->period === 'custom'
            ? $periodStart->format('d M Y').' – '.$board['periodEnd']->format('d M Y')
            : $periodStart->format('M Y');

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->whereHas('assignedTasks', fn ($query) => $query->where('assigned_by', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.bd.task-kanban', [
            'statuses' => $board['statuses'],
            'tasks' => $tasks,
            'splitLogRows' => $board['splitLogRows'],
            'taskTags' => $this->buildTaskTags($tasks),
            'designers' => $designers,
            'periodLabel' => $periodLabel,
            'appliedFilters' => $this->appliedFilters($designers, $periodLabel),
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
