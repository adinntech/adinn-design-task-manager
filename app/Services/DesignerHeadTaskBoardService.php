<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Filtering + period-scoping for the Designer Head / Designer / BD Kanban
 * boards, shared by all three Kanban Livewire components and the CSV export
 * so they always agree on exactly which tasks are in scope for a given
 * filter combination.
 *
 * Period is origin-based: a task belongs to the period it was
 * assigned/created in, and stays there (with its CURRENT status) no matter
 * when it later completes/swaps/declines. A task whose terminal event lands
 * in a different period than its origin gets a "Continued to <month>" label;
 * the later period additionally surfaces it as a read-only "Continued from
 * <month>" record (never counted in that period's totals, never duplicated).
 */
class DesignerHeadTaskBoardService
{
    /**
     * Statuses eligible to carry forward into the current month when a task
     * originated earlier but hasn't reached a terminal state yet. Deliberately
     * excludes completed/swap_tasks (terminal) — decline is excluded further
     * down via the approved-decline-request check, matching how tasksFor()
     * already treats declined tasks as a terminal/historical event.
     */
    private const CARRY_FORWARD_STATUSES = [
        'assigned_tasks', 'review_analysis', 'need_clarification',
        'yet_to_start', 'in_progress', 'waiting_confirmation', 'rework',
    ];

    /**
     * @param  array{search:string,vertical:string,priority:string,designerId:string,bdId:string,period:string,dateFrom:string,dateTo:string,overdue?:bool}  $filters
     */
    public function build(array $filters): array
    {
        if (! empty($filters['overdue'])) {
            return $this->buildOverdue($filters);
        }

        [$periodStart, $periodEnd] = $this->dateRangeBounds($filters['period'], $filters['dateFrom'], $filters['dateTo']);

        $tasks = $this->tasksFor($filters, $periodStart, $periodEnd);
        $taskIds = $tasks->pluck('id');
        $swapShadowTasks = $this->swapShadowTasks($filters, $periodStart, $periodEnd);
        $swapShadowIds = $swapShadowTasks->pluck('id');

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

        // Tasks that originated in-period but finished (completed/swapped/declined)
        // outside it get a "Continued to <month>" label — still shown here, under
        // their current status, never hidden for finishing late.
        $this->annotateContinuationTo($tasks, $completedAtByTask, collect(), $declineRespondedAtByTask, $periodStart, $periodEnd);
        $this->annotateContinuationTo($swapShadowTasks, collect(), $swapRespondedAtByTask, collect(), $periodStart, $periodEnd);

        // The reverse: tasks that originated in an EARLIER period but completed/
        // swapped inside this one — surfaced as extra "Continued from <month>"
        // read-only records so the event stays traceable from this period too,
        // without duplicating (or being counted as) an origin-period task.
        $continuationFromTasks = $this->continuationFromTasks($filters, $periodStart, $periodEnd, $taskIds->merge($swapShadowIds));

        $visibleTasks = $tasks->concat($swapShadowTasks)->concat($continuationFromTasks)->values();

        /* ---- Split has no terminal task-state of its own (the original keeps
         * working with fewer creatives; the new task starts its own normal
         * lifecycle) — surfaced instead as a read-only log of approvals in the
         * period, same idea as the Requests column. Search/Vertical/Priority
         * apply automatically since only tasks already present in $tasks
         * (filtered above) are kept. ---- */
        $eligibleTaskIds = $taskIds->all();
        // designerId is either a Designer's own forced filter or a Designer
        // Head/BD "filter by designer" dropdown — reused here so a Designer
        // still sees a split they requested even if the approved child task
        // went to a different designer.
        $requesterMatchId = $filters['designerId'] !== '' ? (int) $filters['designerId'] : null;

        $splitLog = DesignTaskRequest::query()
            ->where('request_type', 'split')
            ->where('overall_status', 'approved')
            ->with(['task:id,task_id,task_name,designer_id,assigned_by,vertical,priority,task_nature,party_name', 'task.designer:id,name', 'adminActor:id', 'designerHeadActor:id'])
            ->latest('designer_head_action_at')
            ->get()
            ->filter(fn (DesignTaskRequest $request) => $inPeriod($request->responded_at))
            ->map(function (DesignTaskRequest $request) {
                $childId = (int) data_get($request->split_details, 'created_task_id');

                return $childId ? array_merge(['request' => $request], ['child_id' => $childId]) : null;
            })
            ->filter()
            ->filter(function (array $row) use ($eligibleTaskIds, $requesterMatchId, $filters) {
                if (in_array($row['child_id'], $eligibleTaskIds, true)) {
                    return true;
                }

                $task = $row['request']->task;

                if ($requesterMatchId === null || (int) $row['request']->requested_by !== $requesterMatchId || ! $task) {
                    return false;
                }

                // Requester-only match (child now owned by a different designer):
                // the ORIGINAL task still has to satisfy Search/Vertical/Priority/BD
                // so this stays compatible with the other filters.
                return ($filters['vertical'] === '' || $task->vertical === $filters['vertical'])
                    && ($filters['priority'] === '' || $task->priority === $filters['priority'])
                    && ($filters['bdId'] === '' || (string) $task->assigned_by === $filters['bdId'])
                    && ($filters['search'] === '' || collect([$task->task_id, $task->task_name, $task->party_name, $task->vertical, $task->task_nature])
                        ->filter()->contains(fn ($v) => stripos((string) $v, trim($filters['search'])) !== false));
            });

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
         * (a task cannot be double-counted even if it had more than one event).
         * This stays event-based (unlike the origin-based totals below) — it
         * answers "what happened this period", not "what originated in it". ---- */
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

        // Active Tasks card breakdown: every non-completed status, counted against
        // the origin-period task set only (never the continuation-from extras).
        $activeBreakdown = collect($statuses)
            ->reject(fn ($label, $key) => $key === 'completed')
            ->map(fn ($label, $key) => ['label' => $label, 'count' => $tasks->where('status', $key)->count()])
            ->values();

        return [
            'tasks' => $tasks,
            'visibleTasks' => $visibleTasks,
            'swapShadowTasks' => $swapShadowTasks,
            'splitLogRows' => $splitLogRows,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodStats' => $periodStats,
            'statuses' => $statuses,
            'activeBreakdown' => $activeBreakdown,
        ];
    }

    /**
     * The "Overdue" option in the Priority dropdown deliberately bypasses the
     * origin-period scoping every other view of this board uses — an overdue
     * task can have originated in an earlier month than the one selected, and
     * it must still surface here regardless. Reuses the exact same overdue
     * rule as DesignTaskReportingService::completionInfo() (not completed +
     * due date passed): status != completed, due_at in the past.
     */
    private function buildOverdue(array $filters): array
    {
        $tasks = $this->overdueTasks($filters);

        $statuses = DesignTaskStatusService::STATUSES;
        $statuses['swap_tasks'] = 'Swapped Tasks';
        $statuses['decline_tasks'] = 'Decline Tasks';

        $activeBreakdown = collect($statuses)
            ->reject(fn ($label, $key) => $key === 'completed')
            ->map(fn ($label, $key) => ['label' => $label, 'count' => $tasks->where('status', $key)->count()])
            ->values();

        $now = now();

        return [
            'tasks' => $tasks,
            'visibleTasks' => $tasks,
            'swapShadowTasks' => collect(),
            'splitLogRows' => collect(),
            'periodStart' => $now->copy()->startOfMonth(),
            'periodEnd' => $now->copy()->endOfMonth(),
            'periodStats' => ['completed' => 0, 'swapped' => 0, 'declined' => 0, 'split' => 0, 'total' => $tasks->count()],
            'statuses' => $statuses,
            'activeBreakdown' => $activeBreakdown,
        ];
    }

    private function overdueTasks(array $filters): Collection
    {
        $declinedTaskIds = DesignTaskRequest::query()
            ->where('request_type', 'decline')
            ->where('overall_status', 'approved')
            ->whereNotNull('approved_designer_id')
            ->pluck('design_task_id')
            ->all();

        $tasks = $this->applyFilters(
            DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name']),
            array_merge($filters, ['priority' => ''])
        )
            ->where('status', '!=', 'completed')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();

        $tasks = $tasks
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();

        return $tasks->each(function (DesignTask $task) use ($declinedTaskIds) {
            if (in_array($task->id, $declinedTaskIds, true)) {
                $task->status = 'decline_tasks';
            }
        });
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
                        ->orWhereHas('designer', fn ($designerQuery) => $designerQuery->where('name', 'like', $term))
                        ->orWhereHas('assigner', fn ($assignerQuery) => $assignerQuery->where('name', 'like', $term));
                });
            })
            ->when($filters['vertical'] !== '', fn ($query) => $query->where('vertical', $filters['vertical']))
            ->when($filters['priority'] !== '', fn ($query) => $query->where('priority', $filters['priority']))
            ->when($filters['designerId'] !== '', fn ($query) => $query->where('designer_id', $filters['designerId']))
            ->when($filters['bdId'] !== '', fn ($query) => $query->where('assigned_by', $filters['bdId']));
    }

    /**
     * Every task's "origin" is its created_at — deliberately NOT assigned_at,
     * even though that field is set at the same instant a BD creates+assigns
     * a task (see Bd\TaskController::store()): the design_tasks.assigned_at
     * column carries a legacy MySQL/MariaDB "DEFAULT CURRENT_TIMESTAMP ON
     * UPDATE CURRENT_TIMESTAMP" clause (it's the table's first TIMESTAMP
     * column under explicit_defaults_for_timestamp=0), so it silently resets
     * to "now" on every future row update — every status move, decline/swap/
     * split reassignment, the create-then-rename task_id fixup, etc. created_at
     * is a plain nullable timestamp with no such clause and Eloquent only ever
     * writes it once, on insert, so it's the reliable "origin" timestamp.
     */
    private function originBetween(Builder $query, Carbon $periodStart, Carbon $periodEnd): Builder
    {
        return $query->whereBetween('created_at', [$periodStart, $periodEnd]);
    }

    private function tasksFor(array $filters, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $tasks = $this->originBetween(
            $this->applyFilters(
                DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name']),
                $filters
            ),
            $periodStart,
            $periodEnd
        )
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get();

        $tasks = $tasks
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();

        if ($filters['period'] === 'current_month') {
            $tasks = $tasks->concat($this->carryForwardTasks($filters, $periodStart))->values();
        }

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
     * Still-open tasks that originated before the current month but haven't
     * reached a terminal state — carried into the current-month board (under
     * their real status/column) so open work never silently disappears just
     * because the calendar rolled over. Excludes any task with an approved
     * decline request (declines are a terminal/historical event, same as
     * tasksFor()'s 'decline_tasks' override — see CARRY_FORWARD_STATUSES).
     */
    private function carryForwardTasks(array $filters, Carbon $periodStart): Collection
    {
        $declinedTaskIds = DesignTaskRequest::query()
            ->where('request_type', 'decline')
            ->where('overall_status', 'approved')
            ->whereNotNull('approved_designer_id')
            ->pluck('design_task_id');

        return $this->applyFilters(
            DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name']),
            $filters
        )
            ->whereIn('status', self::CARRY_FORWARD_STATUSES)
            ->where('created_at', '<', $periodStart)
            ->whereNotIn('id', $declinedTaskIds)
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('due_at')
            ->get()
            ->each(function (DesignTask $task) {
                $task->setAttribute('is_previous_month_task', true);
                $task->setAttribute('previous_month_label', 'Previous Month Task • '.$task->created_at->format('M Y'));
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
     * Search/Vertical/Priority/Designer/BD filters (+ origin-period), shadow
     * reject skipped.
     */
    private function swapShadowTasks(array $filters, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return $this->originBetween(
            $this->applyFilters(
                DesignTask::query()->with(['designer:id,name', 'assigner:id,name']),
                $filters
            )->where('status', 'swap_tasks'),
            $periodStart,
            $periodEnd
        )->get();
    }

    /**
     * Stamps a "Continued to <month>" label (+ the event's own date) onto any
     * task whose terminal event lands outside the selected period — it can
     * only be LATER than the period, since a task's origin (already inside
     * the period, by construction of $tasks/$swapShadowTasks) can never be
     * later than its own terminal event.
     */
    private function annotateContinuationTo(
        Collection $tasks,
        SupportCollection $completedAt,
        SupportCollection $swapAt,
        SupportCollection $declineAt,
        Carbon $periodStart,
        Carbon $periodEnd
    ): void {
        foreach ($tasks as $task) {
            $terminalAt = match ($task->status) {
                'completed' => $completedAt->get($task->id),
                'swap_tasks' => $swapAt->get($task->id),
                'decline_tasks' => $declineAt->get($task->id),
                default => null,
            };

            if ($terminalAt === null || $terminalAt->betweenIncluded($periodStart, $periodEnd)) {
                continue;
            }

            $task->setAttribute('continuation_label', 'Continued to '.$terminalAt->format('M Y'));
            $task->setAttribute('continuation_event_label', $this->eventLabel($task->status, $terminalAt));
        }
    }

    /**
     * Read-only "Continued from <month>" records for THIS period: tasks that
     * originated earlier but completed/swapped inside the selected period.
     * Scoped to completed + swap (the two terminal states with a single,
     * unambiguous owner); excludes anything already present in the
     * origin-period sets so nothing is ever shown twice.
     */
    private function continuationFromTasks(array $filters, Carbon $periodStart, Carbon $periodEnd, SupportCollection $excludeIds): Collection
    {
        $completed = $this->applyFilters(
            DesignTask::query()->with(['bdReview', 'designer:id,name', 'assigner:id,name']),
            $filters
        )
            ->where('status', 'completed')
            ->where('created_at', '<', $periodStart)
            ->whereNotIn('id', $excludeIds)
            ->get();

        if ($completed->isNotEmpty()) {
            $completedAtByTask = DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $completed->pluck('id'))
                ->pluck('created_at', 'design_task_id');

            $completed = $completed
                ->filter(fn (DesignTask $task) => ($at = $completedAtByTask->get($task->id)) && $at->betweenIncluded($periodStart, $periodEnd))
                ->each(fn (DesignTask $task) => $this->stampContinuationFrom($task, $completedAtByTask->get($task->id)));
        }

        $swapped = $this->applyFilters(
            DesignTask::query()->with(['designer:id,name', 'assigner:id,name']),
            $filters
        )
            ->where('status', 'swap_tasks')
            ->where('created_at', '<', $periodStart)
            ->whereNotIn('id', $excludeIds)
            ->get();

        if ($swapped->isNotEmpty()) {
            $swapAtByTask = $this->respondedAtByTask($swapped->pluck('id'), 'swap');

            $swapped = $swapped
                ->filter(fn (DesignTask $task) => ($at = $swapAtByTask->get($task->id)) && $at->betweenIncluded($periodStart, $periodEnd))
                ->each(fn (DesignTask $task) => $this->stampContinuationFrom($task, $swapAtByTask->get($task->id)));
        }

        return $completed->concat($swapped)->values();
    }

    private function stampContinuationFrom(DesignTask $task, Carbon $eventAt): void
    {
        $origin = $task->created_at;

        $task->setAttribute('continuation_label', $origin ? 'Continued from '.$origin->format('M Y') : null);
        $task->setAttribute('continuation_event_label', $this->eventLabel($task->status, $eventAt));
        $task->setAttribute('is_continuation_only', true);
    }

    private function eventLabel(string $status, Carbon $at): ?string
    {
        return match ($status) {
            'completed' => 'Completed '.$at->format('d M Y'),
            'swap_tasks' => 'Swapped '.$at->format('d M Y'),
            'decline_tasks' => 'Declined '.$at->format('d M Y'),
            default => null,
        };
    }

    /**
     * Start/end of the currently selected period — the origin (assigned/
     * created date) window every task list above is scoped to.
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
