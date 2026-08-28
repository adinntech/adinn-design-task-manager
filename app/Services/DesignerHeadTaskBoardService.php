<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Filtering + period-scoping for the Designer Head Kanban board, shared by
 * the Kanban Livewire component and the CSV export so both always agree on
 * exactly which tasks are in scope for a given filter combination.
 */
class DesignerHeadTaskBoardService
{
    /**
     * @param  array{search:string,vertical:string,priority:string,designerId:string,bdId:string,period:string,dateFrom:string,dateTo:string}  $filters
     */
    public function build(array $filters): array
    {
        $tasks = $this->tasksFor($filters);
        $taskIds = $tasks->pluck('id');
        $swapShadowTasks = $this->swapShadowTasks($filters);
        $swapShadowIds = $swapShadowTasks->pluck('id');
        [$periodStart, $periodEnd] = $this->dateRangeBounds($filters['period'], $filters['dateFrom'], $filters['dateTo']);

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

        return [
            'tasks' => $tasks,
            'visibleTasks' => $visibleTasks,
            'swapShadowTasks' => $swapShadowTasks,
            'splitLogRows' => $splitLogRows,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodStats' => $periodStats,
            'statuses' => $statuses,
        ];
    }

    /**
     * Search/Vertical/Priority/Designer/BD are shared by the main board query
     * and the swap-shadow / split-log lookups below, so all of them always
     * agree on which tasks are in scope.
     */
    private function applyFilters($query, array $filters)
    {
        return $query
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $term = '%'.trim($filters['search']).'%';

                $query->where(function ($query) use ($term) {
                    $query->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        ->orWhere('vertical', 'like', $term)
                        ->orWhere('task_nature', 'like', $term)
                        ->orWhereHas('designer', fn ($designerQuery) => $designerQuery->where('name', 'like', $term));
                });
            })
            ->when($filters['vertical'] !== '', fn ($query) => $query->where('vertical', $filters['vertical']))
            ->when($filters['priority'] !== '', fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['designerId'] !== '', fn ($query) => $query->where('designer_id', $filters['designerId']))
            ->when($filters['bdId'] !== '', fn ($query) => $query->where('assigned_by', $filters['bdId']));
    }

    private function tasksFor(array $filters): Collection
    {
        $tasks = $this->applyFilters(
            DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name']),
            $filters
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
     * own board, BD's board, the main board query above), so it never appears
     * twice for the Designer who no longer owns it. The "Swapped Tasks"
     * column's whole purpose is to surface that otherwise-hidden record as a
     * read-only historical entry, so it queries directly instead — same
     * Search/Vertical/Priority/Designer/BD filters, shadow reject skipped.
     */
    private function swapShadowTasks(array $filters): Collection
    {
        return $this->applyFilters(
            DesignTask::query()->with(['designer:id,name', 'assigner:id,name']),
            $filters
        )
            ->where('status', 'swap_tasks')
            ->get();
    }

    /**
     * Start/end of the currently selected period. Only used to scope the
     * historical/final categories (Completed, Swapped, Declined, Split log) —
     * never the active/open workflow columns, which always show current state
     * regardless of when the task was assigned.
     */
    private function dateRangeBounds(string $period, string $dateFrom, string $dateTo): array
    {
        $now = now();

        return match ($period) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'custom' => $this->customDateRangeBounds($dateFrom, $dateTo, $now),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function customDateRangeBounds(string $dateFrom, string $dateTo, Carbon $now): array
    {
        $fallback = [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];

        if (! $this->isValidDate($dateFrom) || ! $this->isValidDate($dateTo)) {
            return $fallback;
        }

        $start = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay();

        if ($start->gt($end)) {
            return $fallback;
        }

        return [$start, $end];
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
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
