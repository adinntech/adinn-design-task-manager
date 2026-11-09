<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the Designer dashboard shows, in one place. Extracted verbatim from
 * Designer\DashboardController::index() so the web page and the mobile API compute the
 * same numbers from the same code (no second copy to drift).
 */
class DesignerDashboardService
{
    /**
     * What the dashboard is scoped to. One of:
     *  - a month: `$month` = "YYYY-MM" (missing, malformed or in the future means the current month);
     *  - overall (all time): `$month` = "overall";
     *  - a date range: `$from` and `$to` = "YYYY-MM-DD" (swapped if reversed, `$to` capped at today).
     *
     * @return array{start: ?Carbon, end: ?Carbon, mode: string, key: string, label: string, is_current: bool}
     */
    public function period(?string $month = null, ?string $from = null, ?string $to = null): array
    {
        $today = now();

        if ($month === 'overall') {
            return ['start' => null, 'end' => null, 'mode' => 'overall', 'key' => 'overall', 'label' => 'Overall', 'is_current' => false];
        }

        $f = $this->parseDate($from);
        $t = $this->parseDate($to);
        if ($f && $t) {
            if ($f->gt($t)) {
                [$f, $t] = [$t, $f];
            }
            if ($t->gt($today)) {
                $t = $today->copy();
            }
            if ($f->gt($t)) {
                $f = $t->copy();
            }
            $f = $f->startOfDay();
            $t = $t->endOfDay();

            return [
                'start' => $f,
                'end' => $t,
                'mode' => 'range',
                'key' => 'range',
                'label' => $f->format('d M Y').' – '.$t->format('d M Y'),
                'is_current' => false,
            ];
        }

        $current = $today->copy()->startOfMonth();
        $start = $current->copy();

        if ($month !== null && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m)) {
            $picked = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
            if ($picked->lte($current)) {
                $start = $picked;
            }
        }

        return [
            'start' => $start,
            'end' => $start->copy()->endOfMonth(),
            'mode' => 'month',
            'key' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'is_current' => $start->equalTo($current),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /** Whether a moment falls in the period (everything does, for "overall"). */
    private function within(?Carbon $at, array $period): bool
    {
        if ($at === null) {
            return false;
        }

        return $period['start'] === null || $at->betweenIncluded($period['start'], $period['end']);
    }

    /** A task belongs to the period it was assigned in (a Designer-created ticket not yet assigned uses its creation date). */
    private function inPeriod(DesignTask $task, array $period): bool
    {
        return $period['start'] === null || $this->within($task->assigned_at ?? $task->created_at, $period);
    }

    /** @return array<string, mixed> the exact variables the `designer.dashboard` view receives */
    public function build(User $designer, ?string $month = null, ?string $from = null, ?string $to = null): array
    {
        $designerId = (int) $designer->id;
        $period = $this->period($month, $from, $to);

        $allTasks = DesignTask::query()
            ->with(['assigner:id,name'])
            ->where('designer_id', $designerId)
            ->latest('assigned_at')
            ->get();

        // Everything except the Performance Trend is the selected period's tickets; the trend itself looks at the
        // six months ending with it (or with this month, for "overall"), so it uses $allTasks.
        $tasks = $allTasks->filter(fn (DesignTask $task) => $this->inPeriod($task, $period))->values();

        $taskIds = $tasks->pluck('id');

        $allRequests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,designer_id,status',
                'task.designer:id,name',
                'approvedDesigner:id,name',
                'targetDesigner:id,name',
            ])
            ->where('requested_by', $designerId)
            ->latest()
            ->get();

        // Requests raised in the selected period.
        $ownRequests = $allRequests
            ->filter(fn (DesignTaskRequest $r) => $this->within($r->created_at, $period))
            ->values();

        // The six-month Performance Trend ends with the period's last month (this month for "overall").
        $anchor = ($period['end'] ?? now())->copy()->startOfMonth();

        $pendingStatuses = ['pending_approval', 'pending_designer_head', 'pending_admin'];
        $pendingRequests = $ownRequests->whereIn('overall_status', $pendingStatuses)->values();

        $now = now();

        $stats = [
            'total' => $tasks->count(),
            'assigned' => $tasks->where('status', 'assigned_tasks')->count(),
            'ready_to_start' => $tasks->where('status', 'yet_to_start')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'pending_approval' => $pendingRequests->count(),
            // Tasks THIS designer created themselves, still awaiting the
            // selected BD's confirmation decision — separate from
            // 'pending_approval' above (which is decline/split/swap/status_change
            // requests awaiting Designer Head/Admin).
            'waiting_bd_confirmation' => $tasks->where('status', 'pending_bd_approval')->count(),
            // "Waiting for BD Review" = designer's own work is done and sitting in
            // waiting_confirmation; every task only reaches 'completed' once BD rates
            // it (same DB transaction in Bd\AssignedTaskController::completeWithRating),
            // so there is no "completed but unrated" state to detect separately.
            'waiting_bd_review' => $tasks->where('status', 'waiting_confirmation')->count(),
            'prepare_printing_file' => $tasks->where('status', 'prepare_printing_file')->count(),
            'overdue' => $tasks
                ->filter(fn (DesignTask $task) => $task->status !== 'completed' && $task->due_at && $task->due_at->lt($now))
                ->count(),
        ];

        // Rework counters reuse DesignTaskProgressService — the same source that
        // already drives the per-task rework tab — instead of a fresh calculation,
        // so "current" vs "overall" semantics stay identical everywhere in the app.
        $progressService = app(DesignTaskProgressService::class);
        $reworkTasks = $tasks->where('status', 'rework');

        $stats['rework'] = $reworkTasks->count();
        $stats['rework_creatives'] = $reworkTasks->sum(fn (DesignTask $task) => $progressService->currentReworkPending($task));

        // Cumulative, all-time — never decreases when a task leaves Rework.
        $overallRework = [
            'cycles' => $tasks->sum(fn (DesignTask $task) => $progressService->reworkCount($task)),
            'creatives' => $taskIds->isEmpty() ? 0 : (int) DesignTaskBdReview::query()
                ->where('action', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->sum('number_of_creatives'),
        ];

        $statusLabels = DesignTaskStatusService::STATUSES;
        unset($statusLabels['swap_tasks']);
        $statusLabels['swap_tasks'] = 'Swapped Tasks';

        $statusSummary = collect($statusLabels)
            ->map(fn (string $label, string $status) => [
                'key' => $status,
                'label' => $label,
                'count' => $tasks->where('status', $status)->count(),
            ])
            ->values();

        $requestSummary = [
            'swap' => $pendingRequests->where('request_type', 'swap')->count(),
            'split' => $pendingRequests->where('request_type', 'split')->count(),
            'decline' => $pendingRequests->where('request_type', 'decline')->count(),
        ];

        $requestTypeCounts = [
            'swap' => $ownRequests->where('request_type', 'swap')->count(),
            'decline' => $ownRequests->where('request_type', 'decline')->count(),
            'split' => $ownRequests->where('request_type', 'split')->count(),
            'approved' => $ownRequests->where('overall_status', 'approved')->count(),
        ];

        $completionRate = $stats['total'] > 0 ? (int) round(($stats['completed'] / $stats['total']) * 100) : 0;

        // Monthly completed-task trend for the last 6 months, driven by the task's own
        // "-> completed" history event (not who clicked it — the final confirmation is
        // often recorded as the BD via bd_completion_rating, not the designer).
        $completions = $allTasks->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $allTasks->pluck('id'))
                ->get(['design_task_id', 'created_at']);

        $monthlyTrend = collect(range(5, 0))
            ->map(function (int $offset) use ($completions, $anchor) {
                $month = $anchor->copy()->subMonths($offset);
                $monthEnd = $month->copy()->endOfMonth();

                return [
                    'label' => $month->format('M'),
                    'count' => $completions
                        ->filter(fn ($row) => $row->created_at->betweenIncluded($month, $monthEnd))
                        ->pluck('design_task_id')
                        ->unique()
                        ->count(),
                ];
            })
            ->values();

        // Only 'completed'-action rows carry a rating; 'rework' rows are BD's earlier
        // send-backs on the same task and are excluded so they never skew the average.
        $completedReviews = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->with('submitter:id,name')
                ->where('action', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->latest()
                ->get();

        $ratedCount = $completedReviews->count();

        // A task can only ever be completed-with-rating once (guarded in
        // AssignedTaskController::completeWithRating), so there's exactly one
        // to_status='completed' history row per task — safe to key by task id.
        $completedAtByTask = $completions->keyBy('design_task_id')->map(fn ($row) => $row->created_at);

        $overallRating = [
            'average' => $ratedCount > 0 ? DesignTaskBdReview::roundToHalfStar($completedReviews->avg('overall_rating')) : null,
            'rated' => $ratedCount,
            'total' => $stats['completed'],
        ];

        // Per-task rework creative totals (grouped in one query — avoids N+1 per task row).
        $reworkCreativesByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->where('action', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->selectRaw('design_task_id, SUM(number_of_creatives) as total')
                ->groupBy('design_task_id')
                ->pluck('total', 'design_task_id')
                ->map(fn ($value) => (int) $value);

        $reviewByTask = $completedReviews->keyBy('design_task_id');

        // Your own Task Details table — same design/data rules as the Designer Head
        // dashboard's Designer Task Details table, scoped to this Designer's tasks only.
        $taskRows = $tasks
            ->map(fn (DesignTask $task) => [
                'task' => $task,
                'done' => $progressService->completed($task),
                'remaining' => $progressService->remaining($task),
                'percentage' => $progressService->percentage($task),
                'rework_count' => $progressService->reworkCount($task),
                'rework_creatives' => (int) ($reworkCreativesByTask[$task->id] ?? 0),
                'completed_at' => $completedAtByTask->get($task->id),
                'overdue' => $task->status !== 'completed' && $task->due_at && $task->due_at->lt($now),
                'completion' => $this->completionInfo($task, $completedAtByTask->get($task->id), $now),
                'rating' => $reviewByTask->get($task->id)?->overall_rating,
            ])
            ->values();

        $reviewCards = $completedReviews
            ->filter(fn (DesignTaskBdReview $review) => filled($review->comment))
            ->map(function (DesignTaskBdReview $review) use ($tasks, $completedAtByTask) {
                $task = $tasks->firstWhere('id', $review->design_task_id);

                return [
                    'rating' => DesignTaskBdReview::roundToHalfStar($review->overall_rating),
                    'comment' => $review->comment,
                    'task' => $task,
                    'task_id' => $task?->task_id,
                    'task_name' => $task?->display_task_name ?? $task?->task_name,
                    'reviewer' => $review->submitter?->name ?? 'BD',
                    'reviewed_at' => $review->created_at,
                    'completed_at' => $completedAtByTask->get($review->design_task_id),
                ];
            })
            ->values();

        $requestOutcomes = collect(['swap' => 'Swap', 'decline' => 'Decline', 'split' => 'Split'])
            ->map(function (string $label, string $type) use ($ownRequests, $pendingStatuses) {
                $group = $ownRequests->where('request_type', $type);

                return [
                    'type' => $type,
                    'label' => $label,
                    'approved' => $group->where('overall_status', 'approved')->count(),
                    'rejected' => $group->where('overall_status', 'rejected')->count(),
                    'pending' => $group->whereIn('overall_status', $pendingStatuses)->count(),
                ];
            })
            ->values();

        // Latest activity first: a request that was just approved/rejected should
        // surface above an older one that's still merely pending, even if the
        // pending one was technically created more recently.
        $myRequests = $ownRequests
            ->sortByDesc(fn (DesignTaskRequest $request) => ($request->responded_at ?? $request->created_at)?->timestamp ?? 0)
            ->values()
            ->take(100);

        /* ---- Performance Trend (shared shape): last 6 months, self-scoped ---- */
        $line = collect(range(5, 0))->map(function (int $offset) use ($anchor, $allTasks, $allRequests, $completedAtByTask) {
            $start = $anchor->copy()->subMonths($offset);
            $end = $start->copy()->endOfMonth();
            $monthTasks = $allTasks->filter(fn (DesignTask $task) => $task->assigned_at && $task->assigned_at->betweenIncluded($start, $end));

            $completedIds = collect();
            foreach ($completedAtByTask as $tid => $ts) {
                if ($ts->betweenIncluded($start, $end)) {
                    $completedIds->push((int) $tid);
                }
            }

            $overdueCompleted = 0;
            foreach ($completedIds as $tid) {
                $task = $allTasks->firstWhere('id', $tid);
                if ($task && $task->due_at && $task->due_at->lt($completedAtByTask[$tid])) {
                    $overdueCompleted++;
                }
            }

            $declined = $allRequests
                ->filter(fn ($r) => $r->request_type === 'decline'
                    && $r->overall_status === 'approved'
                    && $r->responded_at !== null
                    && $r->responded_at->betweenIncluded($start, $end))
                ->count();

            return [
                'label' => $start->format('M y'),
                'assigned' => $monthTasks->count(),
                'in_progress' => $monthTasks->where('status', 'in_progress')->count(),
                'completed' => $completedIds->count(),
                'overdue_completed' => $overdueCompleted,
                'declined' => $declined,
            ];
        })->values();

        // The trend cards are the selected month's tickets (the same ones as the rest of the page).
        $currentMonthTasks = $tasks;
        $overdueCompletedCount = $currentMonthTasks
            ->filter(fn (DesignTask $task) => $task->status === 'completed'
                && $task->due_at
                && ($completedAtByTask->get($task->id) ?? null)
                && $task->due_at->lt($completedAtByTask->get($task->id)))
            ->count();
        $approvedDeclines = $allRequests
            ->filter(fn ($r) => $r->request_type === 'decline'
                && $r->overall_status === 'approved'
                && $this->within($r->responded_at, $period))
            ->count();
        $trendCards = [
            ['label' => 'Assigned Tasks', 'value' => $currentMonthTasks->count(), 'color' => '#2970ff'],
            ['label' => 'In Progress', 'value' => $currentMonthTasks->where('status', 'in_progress')->count(), 'color' => '#7c3aed'],
            ['label' => 'Completed', 'value' => $currentMonthTasks->where('status', 'completed')->count(), 'color' => '#027a48'],
            ['label' => 'Overdue & Completed', 'value' => $overdueCompletedCount, 'color' => '#f79009'],
            ['label' => 'Declined', 'value' => $approvedDeclines, 'color' => '#c01048'],
        ];
        $trendContext = trim('My Tasks'.' • '.($period['mode'] === 'month' ? $period['start']->format('M Y') : $period['label']));


        return [
            'stats' => $stats,
            'recentTasks' => $tasks->take(12),
            'taskRows' => $taskRows,
            'myRequests' => $myRequests,
            'requestSummary' => $requestSummary,
            'requestTypeCounts' => $requestTypeCounts,
            'statusSummary' => $statusSummary,
            'completionRate' => $completionRate,
            'monthlyTrend' => $monthlyTrend,
            'requestOutcomes' => $requestOutcomes,
            'overallRating' => $overallRating,
            'reviewCards' => $reviewCards,
            'overallRework' => $overallRework,
            'line' => $line,
            'trendCards' => $trendCards,
            'trendContext' => $trendContext,
            'period' => [
                'mode' => $period['mode'],
                'key' => $period['key'],
                'label' => $period['label'],
                'is_current' => $period['is_current'],
                // For the month picker; an overall / range view opens it on the current month.
                'year' => (int) ($period['mode'] === 'month' ? $period['start'] : now())->format('Y'),
                'month' => (int) ($period['mode'] === 'month' ? $period['start'] : now())->format('n'),
                'from' => $period['mode'] === 'range' ? $period['start']->toDateString() : null,
                'to' => $period['mode'] === 'range' ? $period['end']->toDateString() : null,
                'first_date' => $this->firstDate($allTasks),
                // Years a Designer can pick: from the first ticket they were ever given up to this year.
                'years' => $this->yearOptions($allTasks),
            ],
        ];
    }

    /** The date of the first ticket this designer was given (the earliest a custom range can start). */
    private function firstDate(Collection $allTasks): string
    {
        $first = $allTasks->map(fn (DesignTask $t) => $t->assigned_at ?? $t->created_at)->filter()->min();

        return ($first ?? now())->toDateString();
    }

    /** @return list<int> newest first */
    private function yearOptions(Collection $allTasks): array
    {
        $thisYear = (int) now()->format('Y');
        $first = $allTasks->map(fn (DesignTask $t) => ($t->assigned_at ?? $t->created_at)?->year)->filter()->min() ?? $thisYear;

        return range($thisYear, min($first, $thisYear));
    }

    /**
     * Timeliness against the ORIGINAL due date, using the actual completion timestamp —
     * never derived from rework/status text. A task completed after its due date is
     * "late" (with days-late), not "overdue" (which only applies while still incomplete).
     * Mirrors DesignerHead\DashboardController::completionInfo() for identical semantics.
     */
    public function completionInfo(DesignTask $task, ?Carbon $completedAt, Carbon $now): array
    {
        if ($completedAt) {
            $isLate = (bool) ($task->due_at && $completedAt->gt($task->due_at));
            $daysLate = $isLate ? (int) $task->due_at->diffInDays($completedAt) : 0;

            return ['status' => $isLate ? 'late' : 'on_time', 'days' => $daysLate];
        }

        if ($task->status !== 'completed' && $task->due_at && $task->due_at->lt($now)) {
            return ['status' => 'overdue', 'days' => (int) $task->due_at->diffInDays($now)];
        }

        return ['status' => 'in_progress', 'days' => 0];
    }

    /** The "Your Task Details" rows for this designer's tickets of the month, optionally narrowed by a text search. */
    public function taskRows(int $designerId, string $search = '', ?string $month = null, ?string $from = null, ?string $to = null): Collection
    {
        $period = $this->period($month, $from, $to);

        $tasks = DesignTask::query()
            ->with(['assigner:id,name'])
            ->where('designer_id', $designerId)
            ->latest('assigned_at')
            ->get()
            ->filter(fn (DesignTask $task) => $this->inPeriod($task, $period))
            ->values();

        if ($search !== '') {
            $tasks = $tasks->filter(fn (DesignTask $task) => $this->taskMatchesSearch($task, $search))->values();
        }

        $taskIds = $tasks->pluck('id');
        $now = now();
        $progressService = app(DesignTaskProgressService::class);

        $completedAtByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->pluck('created_at', 'design_task_id');

        $reworkCreativesByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->where('action', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->selectRaw('design_task_id, SUM(number_of_creatives) as total')
                ->groupBy('design_task_id')
                ->pluck('total', 'design_task_id')
                ->map(fn ($value) => (int) $value);

        $reviewByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->where('action', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->latest()
                ->get()
                ->keyBy('design_task_id');

        return $tasks->map(fn (DesignTask $task) => [
            'task' => $task,
            'done' => $progressService->completed($task),
            'remaining' => $progressService->remaining($task),
            'percentage' => $progressService->percentage($task),
            'rework_count' => $progressService->reworkCount($task),
            'rework_creatives' => (int) ($reworkCreativesByTask[$task->id] ?? 0),
            'completed_at' => $completedAtByTask->get($task->id),
            'overdue' => $task->status !== 'completed' && $task->due_at && $task->due_at->lt($now),
            'completion' => $this->completionInfo($task, $completedAtByTask->get($task->id), $now),
            'rating' => $reviewByTask->get($task->id)?->overall_rating,
        ])->values();
    }

    /** Same field set as the BD/Designer Head dashboard search. */
    public function taskMatchesSearch(DesignTask $task, string $term): bool
    {
        $haystacks = [
            $task->task_id, $task->zoho_project_number, $task->task_name, $task->party_name,
            $task->contact_person, $task->mobile_number, $task->vertical, $task->task_nature,
            $task->priority, $task->status, $task->assigner?->name,
        ];

        foreach ($haystacks as $value) {
            if ($value !== null && stripos((string) $value, $term) !== false) {
                return true;
            }
        }

        return false;
    }
}
