<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskReportingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const PENDING_STATUSES = ['assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start'];

    private const REQUEST_PENDING = ['pending_approval', 'pending_designer_head', 'pending_admin'];

    public function __construct(private DesignTaskReportingService $reporting) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer_head', 403);

        return view('designer-head.dashboard', $this->analytics($request));
    }

    public function fragment(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer_head', 403);

        return view('designer-head.dashboard-partial', $this->analytics($request));
    }

    /**
     * Completed Task Ratings section has its own Designer filter on top of whatever
     * Month is currently active on the page-wide filter above it, so switching
     * Designer here never drops back out of the period the Designer Head is viewing.
     */
    public function ratings(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer_head', 403);

        $designers = User::query()->where('role', 'designer')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $activeIds = $designers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $selectedDesigner = $request->query('designer', 'all');
        if ($selectedDesigner !== 'all' && ! in_array((int) $selectedDesigner, $activeIds, true)) {
            $selectedDesigner = 'all';
        }
        $designerId = $selectedDesigner === 'all' ? null : (int) $selectedDesigner;
        $page = max(1, (int) $request->query('page', 1));

        $selectedMonth = $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }
        $month = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $data = $this->completedRatings($designerId, $page, 10, $month, $monthEnd);
        $data['designerName'] = $designerId ? $designers->firstWhere('id', $designerId)?->name : null;
        $data['monthLabel'] = $month->format('F Y');

        return view('designer-head.ratings-rows', $data);
    }

    private function analytics(Request $request): array
    {
        $now = now();

        $totalDesigners = (int) User::query()->where('role', 'designer')->count();
        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $activeIds = $designers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $selectedDesigner = $request->query('designer', 'all');
        if ($selectedDesigner !== 'all' && ! in_array((int) $selectedDesigner, $activeIds, true)) {
            $selectedDesigner = 'all';
        }
        $designerId = $selectedDesigner === 'all' ? null : (int) $selectedDesigner;

        $selectedMonth = $request->query('month', $now->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $selectedMonth)) {
            $selectedMonth = $now->format('Y-m');
        }
        $month = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        /* ---- Tasks scoped to current assignee (split/swap shadow rows removed) ---- */
        $tasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name,role'])
            ->when($designerId, fn ($query) => $query->where('designer_id', $designerId))
            ->get()
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();
        $taskIds = $tasks->pluck('id');
        $taskKeyById = $tasks->keyBy('id');

        /* ---- Cohort for the selected period: tasks ASSIGNED within the selected month,
         * on top of the Designer filter above. KPIs, Designer Analytics, Task Details,
         * Overdue, Rework Analytics and BD Review Turnaround all read from this cohort so
         * "sk_designer + Jul 2026" never mixes in another Designer or period. The 6-month
         * trend/"Tasks Worked" bar keep reading from the wider $tasks/$taskIds on purpose —
         * they are inherently cross-month activity metrics, not an assignment cohort. ---- */
        $scopedTasks = $tasks
            ->filter(fn (DesignTask $task) => $task->assigned_at && $task->assigned_at->betweenIncluded($month, $monthEnd))
            ->values();
        $scopedTaskIds = $scopedTasks->pluck('id');

        /* ---- Batched record aggregates (same arithmetic as DesignTaskProgressService) ---- */
        $eodProgress = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'progress'), $taskIds, 'SUM(completed_count)');
        $eodRework = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'rework'), $taskIds, 'SUM(completed_count)');
        $reworkSentBack = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'SUM(number_of_creatives)');
        $reworkReviewCount = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'COUNT(*)');
        $reworkHistoryCount = $this->reporting->mapByTask(
            DesignTaskStatusHistory::query()->where('to_status', 'rework')->where('change_source', 'bd_rework'),
            $taskIds,
            'COUNT(*)'
        );

        /* ---- Completion timestamps come from the immutable status-history log, not UI text ---- */
        $completions = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->get(['design_task_id', 'created_at']);
        $completedAtByTask = collect();
        foreach ($completions as $row) {
            $completedAtByTask[(int) $row->design_task_id] = $row->created_at;
        }
        $completedInMonthIds = $completedAtByTask
            ->filter(fn (Carbon $ts) => $ts->betweenIncluded($month, $monthEnd))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();

        /* ---- Completed-task ratings (one per task, guarded at creation) ---- */
        $reviews = DesignTaskBdReview::query()
            ->with('submitter:id,name,role')
            ->where('action', 'completed')
            ->orderByDesc('created_at')
            ->get();
        $reviewByTaskId = $reviews->keyBy(fn ($review) => (int) $review->design_task_id);

        /* ---- Rework reviews/submissions: counts, month scope and last-event date ---- */
        $reworkReviews = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->with('submitter:id,name')
                ->where('action', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->orderBy('created_at')
                ->get(['id', 'design_task_id', 'created_at', 'number_of_creatives', 'submitted_by']);
        $reworkMonthIds = collect();
        $lastReworkAtByTask = collect();
        foreach ($reworkReviews as $row) {
            $tid = (int) $row->design_task_id;
            if (! isset($lastReworkAtByTask[$tid]) || $row->created_at->gt($lastReworkAtByTask[$tid])) {
                $lastReworkAtByTask[$tid] = $row->created_at;
            }
            if ($row->created_at->betweenIncluded($month, $monthEnd)) {
                $reworkMonthIds[] = $tid;
            }
        }
        $reworkMonthIds = $reworkMonthIds->unique()->values();

        $reworkEod = $taskIds->isEmpty()
            ? collect()
            : DesignTaskEodRecord::query()
                ->where('update_type', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->get(['design_task_id', 'submitted_at']);
        foreach ($reworkEod as $row) {
            $tid = (int) $row->design_task_id;
            if (! isset($lastReworkAtByTask[$tid]) || $row->submitted_at->gt($lastReworkAtByTask[$tid])) {
                $lastReworkAtByTask[$tid] = $row->submitted_at;
            }
        }

        $reworkCyclesByTask = $reworkReviews->groupBy('design_task_id');

        /* ---- BD-review cycles per task (submission -> decision pairs), built once from
         * the immutable status-history log and shared by Task Details' "rework spent time"
         * and the BD Review Turnaround table below, so both agree on timing/counts. ---- */
        $reviewHistory = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->whereIn('design_task_id', $taskIds)
                ->whereIn('to_status', ['waiting_confirmation', 'rework', 'completed'])
                ->orderBy('design_task_id')
                ->orderBy('created_at')
                ->get(['design_task_id', 'to_status', 'created_at']);

        $reviewCyclesByTask = $reviewHistory
            ->groupBy('design_task_id')
            ->map(fn (Collection $historyRows, $taskId) => $taskKeyById->has((int) $taskId)
                ? $this->reporting->reviewCyclesFor($taskKeyById->get((int) $taskId), $historyRows, $reworkCyclesByTask, $reworkReviewCount, $reworkHistoryCount)
                : []);

        /* ---- All requests in one pass (approvals stay global, not filter-scoped) ---- */
        $allRequests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,designer_id,status,priority,due_at,total_creatives',
                'task.designer:id,name',
                'requester:id,name,role',
                'targetDesigner:id,name',
                'approvedDesigner:id,name',
                'designerHeadActor:id,name,role',
                'adminActor:id,name,role',
            ])
            ->orderByDesc('id')
            ->get();

        $pendingRequests = $allRequests->whereIn('overall_status', self::REQUEST_PENDING)->values();

        $recentDecisions = $allRequests
            ->reject(fn ($request) => in_array($request->overall_status, self::REQUEST_PENDING, true))
            ->filter(fn ($request) => $request->responded_at !== null)
            ->sortByDesc(fn ($request) => $request->responded_at->timestamp)
            ->take(12)
            ->values();

        $approvedInMonthFor = function (string $type) use ($allRequests, $designerId, $month, $monthEnd): int {
            return $allRequests
                ->filter(fn ($request) => $request->request_type === $type
                    && $request->overall_status === 'approved'
                    && ($designerId === null || (int) $request->requested_by === $designerId)
                    && $request->responded_at !== null
                    && $request->responded_at->betweenIncluded($month, $monthEnd))
                ->count();
        };

        $isOverdue = fn (DesignTask $task) => $task->status !== 'completed'
            && $task->due_at
            && $task->due_at->lt(now());

        /* ---- "Tasks Worked" in the selected month = any recorded activity ---- */
        $workedIds = $completedInMonthIds->concat(
            DesignTaskEodRecord::query()
                ->whereBetween('submitted_at', [$month, $monthEnd])
                ->when($designerId, fn ($query) => $query->where('designer_id', $designerId))
                ->pluck('design_task_id')
                ->map(fn ($id) => (int) $id)
        );
        if ($taskIds->isNotEmpty()) {
            $workedIds = $workedIds->concat(
                DesignTaskStatusHistory::query()
                    ->whereIn('design_task_id', $taskIds)
                    ->whereBetween('created_at', [$month, $monthEnd])
                    ->pluck('design_task_id')
                    ->map(fn ($id) => (int) $id)
            );
        }
        $workedIds = $workedIds->concat($reworkMonthIds)->unique()->values();

        /* ---- Monthly bar chart (selected designer + month) ---- */
        $bar = [
            ['key' => 'worked', 'label' => 'Tasks Worked', 'value' => $workedIds->count(), 'color' => '#2970ff'],
            ['key' => 'completed', 'label' => 'Tasks Completed', 'value' => $completedInMonthIds->count(), 'color' => '#027a48'],
            ['key' => 'rework', 'label' => 'Rework Tasks', 'value' => $reworkMonthIds->count(), 'color' => '#f79009'],
            ['key' => 'split', 'label' => 'Split Tasks', 'value' => $approvedInMonthFor('split'), 'color' => '#7c3aed'],
            ['key' => 'swapped', 'label' => 'Swapped Tasks', 'value' => $approvedInMonthFor('swap'), 'color' => '#12b76a'],
            ['key' => 'declined', 'label' => 'Declined Tasks', 'value' => $approvedInMonthFor('decline'), 'color' => '#c01048'],
        ];

        /* ---- Six-month trend ending at the selected month ---- */
        $line = collect(range(5, 0))->map(function (int $offset) use ($month, $completedAtByTask, $reviewByTaskId) {
            $start = $month->copy()->subMonths($offset)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $ids = $completedAtByTask
                ->filter(fn (Carbon $ts) => $ts->betweenIncluded($start, $end))
                ->keys();
            $ratings = $ids
                ->map(fn ($id) => $reviewByTaskId->get((int) $id)?->overall_rating)
                ->filter(fn ($value) => $value !== null)
                ->values();

            return [
                'label' => $start->format('M y'),
                'completed' => $ids->count(),
                'rating' => $ratings->count() ? round((float) $ratings->avg(), 1) : null,
            ];
        })->values();

        /* ---- Per-designer workload (always all active designers, scoped to the selected period) ---- */
        $workload = $designers->map(function (User $designer) use ($scopedTasks, $allRequests, $reviewByTaskId, $reworkSentBack, $reworkReviewCount, $reworkHistoryCount, $isOverdue, $month, $monthEnd) {
            $designerTasks = $scopedTasks->where('designer_id', $designer->id);
            $ratings = $designerTasks
                ->map(fn (DesignTask $task) => $reviewByTaskId->get((int) $task->id)?->overall_rating)
                ->filter(fn ($value) => $value !== null);
            $countRequest = fn (string $type) => $allRequests
                ->filter(fn ($request) => $request->request_type === $type
                    && $request->overall_status === 'approved'
                    && (int) $request->requested_by === (int) $designer->id
                    && $request->responded_at !== null
                    && $request->responded_at->betweenIncluded($month, $monthEnd))
                ->count();

            return [
                'designer' => $designer,
                'assigned' => $designerTasks->count(),
                'in_progress' => $designerTasks->where('status', 'in_progress')->count(),
                'pending' => $designerTasks->whereIn('status', self::PENDING_STATUSES)->count(),
                'ready_to_start' => $designerTasks->where('status', 'yet_to_start')->count(),
                'overdue' => $designerTasks->filter($isOverdue)->count(),
                'completed' => $designerTasks->where('status', 'completed')->count(),
                'rework_count' => $designerTasks->sum(fn (DesignTask $task) => $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount)),
                'rework_creatives' => $designerTasks->sum(fn (DesignTask $task) => (int) ($reworkSentBack[$task->id] ?? 0)),
                'split' => $countRequest('split'),
                'swap' => $countRequest('swap'),
                'decline' => $countRequest('decline'),
                'rating' => $ratings->count() ? $ratings->avg() : null,
            ];
        })->sortByDesc('assigned')->values();

        /* ---- Task details table committed to the current assignee, scoped to the period ---- */
        $taskRows = $scopedTasks
            ->sortByDesc('assigned_at')
            ->values()
            ->map(function (DesignTask $task) use ($eodProgress, $eodRework, $reworkSentBack, $isOverdue, $completedAtByTask, $reworkReviewCount, $reworkHistoryCount, $reviewByTaskId, $reviewCyclesByTask) {
                // Sum of every rework cycle's own spent-time (status-history based, built
                // once above) — never a single generic updated_at-derived figure.
                $reworkMinutes = collect($reviewCyclesByTask->get($task->id, []))
                    ->pluck('rework.duration_minutes')
                    ->filter(fn ($value) => $value !== null)
                    ->sum();

                return [
                    'task' => $task,
                    'done' => $this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack),
                    'remaining' => max(0, (int) $task->total_creatives - $this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack)),
                    'percentage' => min(100, (int) round(($this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack) / max(1, (int) $task->total_creatives)) * 100)),
                    'overdue' => $isOverdue($task),
                    'days_overdue' => $isOverdue($task) && $task->due_at ? (int) $task->due_at->diffInDays(now()) : 0,
                    'completion' => $this->reporting->completionInfo($task, $completedAtByTask->get($task->id)),
                    'rework_count' => $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount),
                    'rework_creatives' => (int) ($reworkSentBack[$task->id] ?? 0),
                    'rework_spent_text' => $reworkMinutes > 0 ? $this->reporting->minutesToText((int) $reworkMinutes) : null,
                    'completed_at' => $completedAtByTask->get($task->id),
                    'rating' => $reviewByTaskId->get($task->id)?->overall_rating,
                ];
            })
            ->take(200);

        /* ---- Rework analytics: one row per rework cycle (Rework 1, Rework 2, ...), each
         * with its own date/creative-count/BD — never collapsed into a single aggregate,
         * so Designer Head can see exactly how many times and when a task was reworked. ---- */
        $reworkRows = $scopedTasks
            ->filter(fn (DesignTask $task) => $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount) > 0)
            ->flatMap(function (DesignTask $task) use ($reworkCyclesByTask) {
                $cycles = $reworkCyclesByTask->get($task->id, collect());

                if ($cycles->isEmpty()) {
                    // Legacy tasks reworked before DesignTaskBdReview existed — no per-cycle
                    // creative-count/BD data available, still surface one summary row.
                    return [[
                        'task' => $task,
                        'rework_number' => 1,
                        'rework_assigned_at' => null,
                        'rework_creatives' => null,
                        'bd' => null,
                    ]];
                }

                return $cycles->values()->map(fn ($review, $index) => [
                    'task' => $task,
                    'rework_number' => $index + 1,
                    'rework_assigned_at' => $review->created_at,
                    'rework_creatives' => (int) $review->number_of_creatives,
                    'bd' => $review->submitter?->name,
                ])->all();
            })
            ->sortByDesc(fn (array $row) => $row['rework_assigned_at']?->timestamp ?? 0)
            ->values();

        /* ---- BD review turnaround: how long BD takes to act once Designer submits.
         * One row per review cycle, reusing the same $reviewCyclesByTask built above,
         * scoped to tasks assigned within the selected period. ---- */
        $bdReviewRows = $reviewCyclesByTask
            ->only($scopedTaskIds->all())
            ->flatMap(fn (array $rows) => $rows)
            ->sortByDesc(fn (array $row) => $row['submitted_at']->timestamp)
            ->values()
            ->take(200);

        /* ---- Completed Task Ratings section: own Designer filter/pagination on top of
         * the page-wide Designer + Month, so it starts in the same context as the rest
         * of the dashboard while still letting the user refine it independently. ---- */
        $completedRatings = $this->completedRatings($designerId, 1, 10, $month, $monthEnd);
        $completedRatings['designerName'] = $designerId ? $designers->firstWhere('id', $designerId)?->name : null;
        $completedRatings['monthLabel'] = $month->format('F Y');

        /* ---- Recent completion activity (latest first) ---- */
        $completionsList = $completedAtByTask
            ->filter(fn (Carbon $ts, $taskId) => $taskKeyById->has((int) $taskId))
            ->sortByDesc(fn (Carbon $ts) => $ts->timestamp)
            ->map(function (Carbon $ts, $taskId) use ($taskKeyById, $reviewByTaskId, $reworkReviewCount, $reworkHistoryCount) {
                /** @var DesignTask $task */
                $task = $taskKeyById->get((int) $taskId);

                return [
                    'task' => $task,
                    'completed_at' => $ts,
                    'rating' => $reviewByTaskId->get((int) $taskId)?->overall_rating,
                    'rework_count' => $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount),
                    'duration_text' => $this->reporting->durationText($task->assigned_at, $ts),
                ];
            })
            ->values()
            ->take(12);

        /* ---- Overdue tracking, scoped to the selected period ---- */
        $overdue = $scopedTasks
            ->filter($isOverdue)
            ->map(fn (DesignTask $task) => [
                'task' => $task,
                'days' => (int) $task->due_at->diffInDays(now()),
                'done' => $this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack),
                'total' => (int) $task->total_creatives,
                'percentage' => min(100, (int) round(($this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack) / max(1, (int) $task->total_creatives)) * 100)),
            ])
            ->sortByDesc('days')
            ->values()
            ->take(20);

        $stats = [
            'total_designers' => $totalDesigners,
            'active_designers' => $designerId ? 1 : $designers->count(),
            'total_tasks' => $scopedTasks->count(),
            'in_progress' => $scopedTasks->where('status', 'in_progress')->count(),
            'pending' => $scopedTasks->whereIn('status', self::PENDING_STATUSES)->count(),
            'ready_to_start' => $scopedTasks->where('status', 'yet_to_start')->count(),
            'waiting' => $scopedTasks->where('status', 'waiting_confirmation')->count(),
            'completed' => $scopedTasks->where('status', 'completed')->count(),
            'overdue' => $scopedTasks->filter($isOverdue)->count(),
            'declined' => $approvedInMonthFor('decline'),
            'split' => $approvedInMonthFor('split'),
            'swapped' => $approvedInMonthFor('swap'),
            'rework_tasks' => $scopedTasks->where('status', 'rework')->count(),
            'approval_pending' => $pendingRequests->count(),
        ];

        $months = collect(range(11, 0))->map(fn (int $offset) => [
            'value' => $now->copy()->subMonths($offset)->format('Y-m'),
            'label' => $now->copy()->subMonths($offset)->format('F Y'),
        ])->values();

        return [
            'stats' => $stats,
            'designers' => $designers,
            'months' => $months,
            'selectedDesigner' => $designerId,
            'selectedMonth' => $selectedMonth,
            'selectedDesignerName' => $designerId ? $designers->firstWhere('id', $designerId)?->name : null,
            'selectedMonthLabel' => $month->format('F Y'),
            'workload' => $workload,
            'bar' => $bar,
            'line' => $line,
            'taskRows' => $taskRows,
            'reworkRows' => $reworkRows,
            'bdReviewRows' => $bdReviewRows,
            'completedRatings' => $completedRatings,
            'completions' => $completionsList,
            'overdue' => $overdue,
            'pendingRequests' => $pendingRequests,
            'recentDecisions' => $recentDecisions,
        ];
    }

    /**
     * Completed-task ratings, optionally scoped to one Designer and paginated —
     * shared by the initial dashboard render and the AJAX ratings endpoint so
     * both agree on data/ordering/exclusions.
     */
    private function completedRatings(?int $designerId, int $page, int $perPage, ?Carbon $monthStart = null, ?Carbon $monthEnd = null): array
    {
        $reviews = DesignTaskBdReview::query()
            ->with(['submitter:id,name', 'task:id,task_id,task_name,designer_id,status,requirements', 'task.designer:id,name'])
            ->where('action', 'completed')
            ->when($designerId, fn ($query) => $query->whereHas('task', fn ($q) => $q->where('designer_id', $designerId)))
            ->when($monthStart && $monthEnd, fn ($query) => $query->whereBetween('created_at', [$monthStart, $monthEnd]))
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (DesignTaskBdReview $review) => $review->task !== null
                && ! (bool) data_get($review->task->requirements, '_swap_shadow', false))
            ->values();

        $total = $reviews->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $rows = $reviews->slice(($page - 1) * $perPage, $perPage)->values();

        $taskIds = $rows->pluck('task.id')->filter()->unique()->values();
        $completedAtByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->pluck('created_at', 'design_task_id');

        return [
            'rows' => $rows,
            'completedAtByTask' => $completedAtByTask,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}
