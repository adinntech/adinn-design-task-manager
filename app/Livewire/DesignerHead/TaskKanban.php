<?php

namespace App\Livewire\DesignerHead;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';
    public string $vertical = '';
    public string $priority = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m', used only when period === 'custom'. */
    public string $customMonth = '';

    private const HISTORICAL_STATUSES = ['completed', 'swap_tasks', 'decline_tasks'];

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer_head', 403);
        $this->customMonth = now()->format('Y-m');
    }

    /**
     * Search/Vertical/Priority are shared by the main board query and the
     * swap-shadow / split-log lookups below, so all of them always agree on
     * which tasks are in scope.
     */
    private function applyFilters($query)
    {
        return $query
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
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority));
    }

    public function getTasksProperty(): Collection
    {
        $tasks = $this->applyFilters(
            DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name'])
        )
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();

        $tasks = $tasks
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();

        $declinedTaskIds = DesignTaskRequest::query()
            ->where('request_type', 'decline')
            ->where('overall_status', 'approved')
            ->whereNotNull('approved_designer_id')
            ->whereIn('design_task_id', $tasks->pluck('id'))
            ->pluck('design_task_id')
            ->all();

        return $tasks->each(function (DesignTask $task) use ($declinedTaskIds) {
            if (in_array($task->id, $declinedTaskIds, true)) {
                $task->status = 'decline_tasks';
            }
        });
    }

    /**
     * The "Swap Tasks" status is only ever held by the original task once a
     * swap is approved — and that exact record is deliberately marked
     * `_swap_shadow` and excluded from every task list app-wide (Designer's
     * own board, BD's board, this board's own getTasksProperty() above), so
     * it never appears twice for the Designer who no longer owns it. The
     * "Swapped Tasks" column's whole purpose is to surface that otherwise-
     * hidden record as a read-only historical entry, so it queries directly
     * instead — same Search/Vertical/Priority filters, shadow reject skipped.
     */
    private function swapShadowTasks(): Collection
    {
        return $this->applyFilters(
            DesignTask::query()->with(['designer:id,name', 'assigner:id,name'])
        )
            ->where('status', 'swap_tasks')
            ->get();
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
     * Start/end of the currently selected period. Only used to scope the
     * historical/final categories (Completed, Swapped, Declined, Split log) —
     * never the active/open workflow columns, which always show current state
     * regardless of when the task was assigned.
     */
    private function periodBounds(): array
    {
        $now = now();

        return match ($this->period) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'custom' => $this->customMonthBounds($now),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function customMonthBounds(Carbon $now): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->customMonth)) {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        $start = Carbon::createFromFormat('Y-m', $this->customMonth)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
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
        $tasks = $this->tasks;
        $taskIds = $tasks->pluck('id');
        $swapShadowTasks = $this->swapShadowTasks();
        $swapShadowIds = $swapShadowTasks->pluck('id');
        [$periodStart, $periodEnd] = $this->periodBounds();

        /* ---- Event dates for the three terminal statuses that already exist as
         * Kanban columns — from the immutable status-history / request-approval
         * log, never from updated_at or the assignment date. ---- */
        $completedAtByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->pluck('created_at', 'design_task_id');

        $swapRespondedAtByTask = $this->respondedAtByTask($swapShadowIds, 'swap');
        $declineRespondedAtByTask = $this->respondedAtByTask($taskIds, 'decline');

        $inPeriod = fn (?Carbon $at) => $at !== null && $at->betweenIncluded($periodStart, $periodEnd);

        /* ---- Hide out-of-period terminal tasks from the board; active/open
         * columns are untouched regardless of when the task was assigned. The
         * otherwise-hidden swap-shadow tasks are merged in here, period-filtered,
         * so the existing "$tasks->where('status','swap_tasks')" column loop in
         * the view picks them up with no extra view-layer special-casing. ---- */
        $visibleTasks = $tasks->filter(function (DesignTask $task) use ($inPeriod, $completedAtByTask, $declineRespondedAtByTask) {
            return match ($task->status) {
                'completed' => $inPeriod($completedAtByTask->get($task->id)),
                'decline_tasks' => $inPeriod($declineRespondedAtByTask->get($task->id)),
                default => true,
            };
        })->concat(
            $swapShadowTasks->filter(fn (DesignTask $task) => $inPeriod($swapRespondedAtByTask->get($task->id)))
        )->values();

        /* ---- Split has no terminal task-state of its own (the original keeps
         * working with fewer creatives; the new task starts its own normal
         * lifecycle) — surfaced instead as a read-only log of approvals in the
         * period, same idea as the Requests column. Search/Vertical/Priority
         * apply automatically since only tasks already present in $tasks
         * (filtered above) are kept. ---- */
        $eligibleTaskIds = $taskIds->all();
        $splitLog = DesignTaskRequest::query()
            ->where('request_type', 'split')
            ->where('overall_status', 'approved')
            ->with(['task:id,task_id,task_name,designer_id', 'task.designer:id,name', 'adminActor:id', 'designerHeadActor:id'])
            ->latest('designer_head_action_at')
            ->get()
            ->filter(fn (DesignTaskRequest $request) => $inPeriod($request->responded_at))
            ->map(function (DesignTaskRequest $request) {
                $childId = (int) data_get($request->split_details, 'created_task_id');

                return $childId ? array_merge(['request' => $request], ['child_id' => $childId]) : null;
            })
            ->filter()
            ->filter(fn (array $row) => in_array($row['child_id'], $eligibleTaskIds, true));

        $splitChildTasks = $splitLog->isEmpty()
            ? collect()
            : DesignTask::query()
                ->whereIn('id', $splitLog->pluck('child_id'))
                ->with('designer:id,name')
                ->get()
                ->keyBy('id');

        $splitLogRows = $splitLog->map(fn (array $row) => [
            'request' => $row['request'],
            'childTask' => $splitChildTasks->get($row['child_id']),
        ])->filter(fn (array $row) => $row['childTask'] !== null)->values();

        /* ---- Period-scoped summary strip: Total is the distinct union of tasks
         * touched by any of the four events in the period, never a plain sum
         * (a task cannot be double-counted even if it had more than one event). ---- */
        $completedIds = $tasks->filter(fn ($t) => $t->status === 'completed' && $inPeriod($completedAtByTask->get($t->id)))->pluck('id');
        $swappedIds = $swapShadowTasks->filter(fn ($t) => $inPeriod($swapRespondedAtByTask->get($t->id)))->pluck('id');
        $declinedIds = $tasks->filter(fn ($t) => $t->status === 'decline_tasks' && $inPeriod($declineRespondedAtByTask->get($t->id)))->pluck('id');
        $splitIds = $splitLogRows->pluck('childTask.id');

        $periodStats = [
            'completed' => $completedIds->count(),
            'swapped' => $swappedIds->count(),
            'declined' => $declinedIds->count(),
            'split' => $splitIds->count(),
            'total' => $completedIds->concat($swappedIds)->concat($declinedIds)->concat($splitIds)->unique()->count(),
        ];

        $statuses = DesignTaskStatusService::STATUSES;

        if (! array_key_exists('swap_tasks', $statuses)) {
            $statuses['swap_tasks'] = 'Swapped Tasks';
        } else {
            unset($statuses['swap_tasks']);
            $statuses['swap_tasks'] = 'Swapped Tasks';
        }
        $statuses['decline_tasks'] = 'Decline Tasks';

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
            'periodLabel' => $periodStart->format('M Y'),
            'activeBreakdown' => $activeBreakdown,
            'stats' => [
                'total' => $tasks->count(),
                'active' => $tasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $tasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
            ],
        ]);
    }

    /**
     * responded_at (designer_head_action_at, falling back to admin_action_at)
     * per task for one approved request type — the authoritative "effective
     * date" for Swap/Decline, set in the same transaction that moves the task.
     */
    private function respondedAtByTask(SupportCollection $taskIds, string $requestType): SupportCollection
    {
        if ($taskIds->isEmpty()) {
            return collect();
        }

        return DesignTaskRequest::query()
            ->where('request_type', $requestType)
            ->where('overall_status', 'approved')
            ->whereIn('design_task_id', $taskIds)
            ->with(['adminActor:id', 'designerHeadActor:id'])
            ->get(['id', 'design_task_id', 'designer_head_action_by', 'designer_head_action_at', 'admin_action_by', 'admin_action_at'])
            ->keyBy('design_task_id')
            ->map(fn (DesignTaskRequest $request) => $request->responded_at);
    }
}
