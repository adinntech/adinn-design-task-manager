<?php

namespace App\Livewire\DesignerHead;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';

    public string $vertical = '';

    public string $priority = '';

    public string $designerId = '';

    public string $bdId = '';

    public string $projectNumber = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $needsRefresh = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer_head', 403);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');

        // The shake state is a temporary in-page attention cue, not restored
        // history — a full page load (including a browser reload) always
        // starts with the Refresh button un-shaken; only a live WebSocket
        // event received while this page stays open shakes it again.
        $this->needsRefresh = false;
    }

    /** Fired only by an explicit Refresh-button click (see refresh-button component). */
    #[On('refresh-tasks')]
    public function refreshBoard(): void
    {
        $this->needsRefresh = false;
    }

    private function filterArray(): array
    {
        $isOverdue = $this->priority === 'overdue';

        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $isOverdue ? '' : $this->priority,
            'designerId' => $this->designerId,
            'bdId' => $this->bdId,
            'projectNumber' => $this->projectNumber,
            'period' => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'overdue' => $isOverdue,
        ];
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->vertical = '';
        $this->priority = '';
        $this->designerId = '';
        $this->bdId = '';
        $this->projectNumber = '';
        $this->period = 'current_month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Human-readable "Filter: value" chips for whichever filters are
     * currently non-default, so the Designer Head always sees what's
     * narrowing the board without having to re-open every dropdown.
     */
    private function appliedFilters(SupportCollection $designers, SupportCollection $bds, string $periodLabel): SupportCollection
    {
        $chips = collect();

        if ($this->search !== '') {
            $chips->push(['label' => 'Search', 'value' => $this->search]);
        }
        if ($this->designerId !== '') {
            $chips->push(['label' => 'Designer', 'value' => $designers->firstWhere('id', (int) $this->designerId)?->name ?? '—']);
        }
        if ($this->bdId !== '') {
            $chips->push(['label' => 'BD', 'value' => $bds->firstWhere('id', (int) $this->bdId)?->name ?? '—']);
        }
        if ($this->projectNumber !== '') {
            $chips->push(['label' => 'Zoho Project Number', 'value' => $this->projectNumber]);
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

    public function getPendingRequestsProperty()
    {
        return DesignTaskRequest::query()
            ->pending()
            ->whereIn('request_type', ['decline', 'split', 'swap', 'status_change'])
            ->with([
                'task:id,task_id,task_name,status,priority,due_at,designer_id,party_name,vertical',
                'task.designer:id,name',
                'requester:id,name',
                'targetDesigner:id,name',
            ])
            ->latest()
            ->get();
    }

    /**
     * Tasks with a pending backward status_change request must appear only
     * under the Requests column, never simultaneously under their current
     * (unchanged) status column — unlike decline/split/swap, whose existing
     * dual-visibility behavior stays untouched.
     */
    private function pendingStatusChangeTaskIds(SupportCollection $pendingRequests): array
    {
        return $pendingRequests
            ->where('request_type', 'status_change')
            ->pluck('design_task_id')
            ->all();
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
            ->whereIn('request_type', ['decline', 'split', 'swap', 'status_change'])
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

            $isPending = in_array(
                $latestRequest->overall_status,
                ['pending_approval', 'pending_designer_head', 'pending_admin'],
                true
            );

            if ($latestRequest->request_type === 'status_change' && $isPending) {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => '⏳ Approval Pending',
                    'title' => 'Waiting for Status Change Approval',
                    'class' => 'task-request-status task-request-pending',
                ]]];
            }

            $typeLabel = match ($latestRequest->request_type) {
                'split' => 'Split',
                'swap' => 'Swap',
                'decline' => 'Decline',
                'status_change' => 'Status Change',
                default => 'Request',
            };

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

        $tasks = $board['tasks'];
        $visibleTasks = $board['visibleTasks'];
        $splitLogRows = $board['splitLogRows'];
        $periodStats = $board['periodStats'];
        $periodStart = $board['periodStart'];
        $periodEnd = $board['periodEnd'];
        $statuses = $board['statuses'];

        $periodLabel = $this->period === 'custom'
            ? $periodStart->format('d M Y').' – '.$periodEnd->format('d M Y')
            : $periodStart->format('M Y');

        $designers = User::query()->where('role', 'designer')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $bds = User::query()->where('role', 'bd')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $pendingRequests = $this->pendingRequests;

        return view('livewire.designer-head.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $visibleTasks,
            'pendingRequests' => $pendingRequests,
            'pendingStatusChangeTaskIds' => $this->pendingStatusChangeTaskIds($pendingRequests),
            'splitLogRows' => $splitLogRows,
            'taskTags' => $this->buildTaskTags($visibleTasks),
            'periodStats' => $periodStats,
            'periodLabel' => $periodLabel,
            'activeBreakdown' => $board['activeBreakdown'],
            'designers' => $designers,
            'bds' => $bds,
            'appliedFilters' => $this->appliedFilters($designers, $bds, $periodLabel),
            'stats' => [
                'total' => $tasks->count(),
                'active' => $tasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $tasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
        ]);
    }
}
