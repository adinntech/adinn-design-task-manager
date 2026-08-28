<?php

namespace App\Livewire\DesignerHead;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';

    public string $vertical = '';

    public string $priority = '';

    public string $designerId = '';

    public string $bdId = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer_head', 403);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    private function filterArray(): array
    {
        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $this->priority,
            'designerId' => $this->designerId,
            'bdId' => $this->bdId,
            'period' => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ];
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

        /* ---- Active Tasks breakdown: same "not completed" definition the
         * Active card total already uses, just broken down per status. ---- */
        $activeBreakdown = collect($statuses)
            ->reject(fn ($label, $key) => $key === 'completed')
            ->map(fn ($label, $key) => ['label' => $label, 'count' => $tasks->where('status', $key)->count()])
            ->values();

        return view('livewire.designer-head.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $visibleTasks,
            'pendingRequests' => $this->pendingRequests,
            'splitLogRows' => $splitLogRows,
            'taskTags' => $this->buildTaskTags($visibleTasks),
            'periodStats' => $periodStats,
            'periodLabel' => $periodLabel,
            'activeBreakdown' => $activeBreakdown,
            'designers' => User::query()->where('role', 'designer')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bds' => User::query()->where('role', 'bd')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => $tasks->count(),
                'active' => $tasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $tasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
        ]);
    }
}
