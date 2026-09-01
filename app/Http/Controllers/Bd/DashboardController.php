<?php

namespace App\Http\Controllers\Bd;

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
        abort_unless($request->user()?->role === 'bd', 403);

        return view('bd.dashboard', $this->analytics($request));
    }

    public function fragment(Request $request): View
    {
        abort_unless($request->user()?->role === 'bd', 403);

        return view('bd.dashboard-partial', $this->analytics($request));
    }

    public function ratings(Request $request): View
    {
        abort_unless($request->user()?->role === 'bd', 403);

        $bdId = (int) $request->user()->id;

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->whereHas('assignedTasks', fn ($q) => $q->where('assigned_by', $bdId)->whereNull('deleted_at'))
            ->orderBy('name')
            ->get(['id', 'name']);

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

        $data = $this->completedRatings($bdId, $designerId, $page, 10, $month, $monthEnd);
        $data['designerName'] = $designerId ? $designers->firstWhere('id', $designerId)?->name : null;
        $data['monthLabel'] = $month->format('F Y');

        return view('bd.ratings-rows', $data);
    }

    private function analytics(Request $request): array
    {
        $now = now();
        $bdId = (int) $request->user()->id;

        /* ---- All tasks created by this BD (swap shadows removed) ---- */
        $tasks = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name,role'])
            ->where('assigned_by', $bdId)
            ->get()
            ->reject(fn (DesignTask $task) => (bool) data_get($task->requirements, '_swap_shadow', false))
            ->values();
        $taskIds = $tasks->pluck('id');
        $taskKeyById = $tasks->keyBy('id');

        /* ---- Designers who have worked on this BD's tickets ---- */
        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->whereHas('assignedTasks', fn ($q) => $q->where('assigned_by', $bdId)->whereNull('deleted_at'))
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

        $filteredTasks = $designerId
            ? $tasks->where('designer_id', $designerId)->values()
            : $tasks;

        /* ---- Scoped cohort: tasks ASSIGNED within the selected month ---- */
        $scopedTasks = $filteredTasks
            ->filter(fn (DesignTask $task) => $task->assigned_at && $task->assigned_at->betweenIncluded($month, $monthEnd))
            ->values();
        $scopedTaskIds = $scopedTasks->pluck('id');

        /* ---- Batched record aggregates ---- */
        $allTaskIds = $filteredTasks->pluck('id');
        $eodProgress = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'progress'), $allTaskIds, 'SUM(completed_count)');
        $eodRework = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'rework'), $allTaskIds, 'SUM(completed_count)');
        $reworkSentBack = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $allTaskIds, 'SUM(number_of_creatives)');
        $reworkReviewCount = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $allTaskIds, 'COUNT(*)');
        $reworkHistoryCount = $this->reporting->mapByTask(
            DesignTaskStatusHistory::query()->where('to_status', 'rework')->where('change_source', 'bd_rework'),
            $allTaskIds,
            'COUNT(*)'
        );

        /* ---- Completion timestamps from status-history ---- */
        $completions = $allTaskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $allTaskIds)
                ->get(['design_task_id', 'created_at']);
        $completedAtByTask = collect();
        foreach ($completions as $row) {
            $completedAtByTask[(int) $row->design_task_id] = $row->created_at;
        }

        /* ---- Completed-task ratings ---- */
        $reviews = DesignTaskBdReview::query()
            ->with('submitter:id,name,role')
            ->where('action', 'completed')
            ->orderByDesc('created_at')
            ->get();
        $reviewByTaskId = $reviews->keyBy(fn ($review) => (int) $review->design_task_id);

        /* ---- Rework reviews/submissions ---- */
        $reworkReviews = $allTaskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->with('submitter:id,name')
                ->where('action', 'rework')
                ->whereIn('design_task_id', $allTaskIds)
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

        $reworkEod = $allTaskIds->isEmpty()
            ? collect()
            : DesignTaskEodRecord::query()
                ->where('update_type', 'rework')
                ->whereIn('design_task_id', $allTaskIds)
                ->get(['design_task_id', 'submitted_at']);
        foreach ($reworkEod as $row) {
            $tid = (int) $row->design_task_id;
            if (! isset($lastReworkAtByTask[$tid]) || $row->submitted_at->gt($lastReworkAtByTask[$tid])) {
                $lastReworkAtByTask[$tid] = $row->submitted_at;
            }
        }

        $reworkCyclesByTask = $reworkReviews->groupBy('design_task_id');

        /* ---- BD-review cycles per task ---- */
        $reviewHistory = $allTaskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->whereIn('design_task_id', $allTaskIds)
                ->whereIn('to_status', ['waiting_confirmation', 'rework', 'completed'])
                ->orderBy('design_task_id')
                ->orderBy('created_at')
                ->get(['design_task_id', 'to_status', 'created_at']);

        $reviewCyclesByTask = $reviewHistory
            ->groupBy('design_task_id')
            ->map(fn (Collection $historyRows, $taskId) => $taskKeyById->has((int) $taskId)
                ? $this->reporting->reviewCyclesFor($taskKeyById->get((int) $taskId), $historyRows, $reworkCyclesByTask, $reworkReviewCount, $reworkHistoryCount)
                : []);

        /* ---- Requests ---- */
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
            ->whereIn('design_task_id', $taskIds)
            ->orderByDesc('id')
            ->get();

        $pendingRequests = $allRequests->whereIn('overall_status', self::REQUEST_PENDING)->values();

        $recentDecisions = $allRequests
            ->reject(fn ($r) => in_array($r->overall_status, self::REQUEST_PENDING, true))
            ->filter(fn ($r) => $r->responded_at !== null)
            ->sortByDesc(fn ($r) => $r->responded_at->timestamp)
            ->take(12)
            ->values();

        $approvedInMonthFor = function (string $type) use ($allRequests, $designerId, $month, $monthEnd): int {
            return $allRequests
                ->filter(fn ($r) => $r->request_type === $type
                    && $r->overall_status === 'approved'
                    && ($designerId === null || (int) $r->requested_by === $designerId)
                    && $r->responded_at !== null
                    && $r->responded_at->betweenIncluded($month, $monthEnd))
                ->count();
        };

        $isOverdue = fn (DesignTask $task) => $task->status !== 'completed'
            && $task->due_at
            && $task->due_at->lt(now());

        /* ---- All-time unique designers (not affected by filters) ---- */
        $totalDesignersAssigned = $tasks->pluck('designer_id')->filter()->unique()->count();

        /* ---- Monthly bar chart ---- */
        $bar = [
            ['key' => 'assigned', 'label' => 'Assigned Tasks', 'value' => $scopedTasks->count(), 'color' => '#2970ff'],
            ['key' => 'completed', 'label' => 'Completed', 'value' => $scopedTasks->where('status', 'completed')->count(), 'color' => '#027a48'],
            ['key' => 'rework', 'label' => 'Rework Tasks', 'value' => $reworkMonthIds->count(), 'color' => '#f79009'],
            ['key' => 'split', 'label' => 'Split Tasks', 'value' => $approvedInMonthFor('split'), 'color' => '#7c3aed'],
            ['key' => 'swapped', 'label' => 'Swapped Tasks', 'value' => $approvedInMonthFor('swap'), 'color' => '#12b76a'],
            ['key' => 'declined', 'label' => 'Declined Tasks', 'value' => $approvedInMonthFor('decline'), 'color' => '#c01048'],
        ];

        /* ---- Six-month performance trend (shared shape across all roles) ---- */
        $line = collect(range(5, 0))->map(function (int $offset) use ($month, $filteredTasks, $allRequests, $completedAtByTask, $designerId) {
            $start = $month->copy()->subMonths($offset)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $monthTasks = $filteredTasks->filter(fn (DesignTask $task) => $task->assigned_at && $task->assigned_at->betweenIncluded($start, $end));

            $completedIds = collect();
            foreach ($completedAtByTask as $tid => $ts) {
                if ($ts->betweenIncluded($start, $end)) {
                    $completedIds->push((int) $tid);
                }
            }

            $overdueCompleted = 0;
            foreach ($completedIds as $tid) {
                $task = $filteredTasks->firstWhere('id', $tid);
                if ($task && $task->due_at && $task->due_at->lt($completedAtByTask[$tid])) {
                    $overdueCompleted++;
                }
            }

            $declined = $allRequests
                ->filter(fn ($r) => $r->request_type === 'decline'
                    && $r->overall_status === 'approved'
                    && ($designerId === null || (int) $r->requested_by === $designerId)
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

        /* ---- Per-designer workload ---- */
        $workload = $designers->map(function (User $designer) use ($scopedTasks, $allRequests, $reviewByTaskId, $reworkSentBack, $reworkReviewCount, $reworkHistoryCount, $isOverdue, $month, $monthEnd) {
            $designerTasks = $scopedTasks->where('designer_id', $designer->id);
            $ratings = $designerTasks
                ->map(fn (DesignTask $task) => $reviewByTaskId->get((int) $task->id)?->overall_rating)
                ->filter(fn ($value) => $value !== null);
            $countRequest = fn (string $type) => $allRequests
                ->filter(fn ($r) => $r->request_type === $type
                    && $r->overall_status === 'approved'
                    && (int) $r->requested_by === (int) $designer->id
                    && $r->responded_at !== null
                    && $r->responded_at->betweenIncluded($month, $monthEnd))
                ->count();

            return [
                'designer' => $designer,
                'assigned' => $designerTasks->count(),
                'in_progress' => $designerTasks->where('status', 'in_progress')->count(),
                'pending' => $designerTasks->whereIn('status', self::PENDING_STATUSES)->count(),
                'ready_to_start' => $designerTasks->where('status', 'yet_to_start')->count(),
                'waiting' => $designerTasks->where('status', 'waiting_confirmation')->count(),
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

        /* ---- Task details table ---- */
        $taskRows = $scopedTasks
            ->sortByDesc('assigned_at')
            ->values()
            ->map(function (DesignTask $task) use ($eodProgress, $eodRework, $reworkSentBack, $isOverdue, $completedAtByTask, $reworkReviewCount, $reworkHistoryCount, $reviewByTaskId, $reviewCyclesByTask) {
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

        /* ---- Rework analytics ---- */
        $reworkRows = $scopedTasks
            ->filter(fn (DesignTask $task) => $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount) > 0)
            ->flatMap(function (DesignTask $task) use ($reworkCyclesByTask) {
                $cycles = $reworkCyclesByTask->get($task->id, collect());

                if ($cycles->isEmpty()) {
                    return [[
                        'task' => $task,
                        'rework_number' => 1,
                        'rework_assigned_at' => null,
                        'rework_creatives' => null,
                        'bd' => null,
                        'rework_spent_text' => null,
                    ]];
                }

                return $cycles->values()->map(fn ($review, $index) => [
                    'task' => $task,
                    'rework_number' => $index + 1,
                    'rework_assigned_at' => $review->created_at,
                    'rework_creatives' => (int) $review->number_of_creatives,
                    'bd' => $review->submitter?->name,
                    'rework_spent_text' => $review->rework ? $review->rework['duration_text'] : null,
                ])->all();
            })
            ->sortByDesc(fn (array $row) => $row['rework_assigned_at']?->timestamp ?? 0)
            ->values();

        /* ---- BD review turnaround ---- */
        $bdReviewRows = $reviewCyclesByTask
            ->only($scopedTaskIds->all())
            ->flatMap(fn (array $rows) => $rows)
            ->sortByDesc(fn (array $row) => $row['submitted_at']->timestamp)
            ->values()
            ->take(200);

        /* ---- Completed Task Ratings ---- */
        $completedRatings = $this->completedRatings($bdId, $designerId, 1, 10, $month, $monthEnd);
        $completedRatings['designerName'] = $designerId ? $designers->firstWhere('id', $designerId)?->name : null;
        $completedRatings['monthLabel'] = $month->format('F Y');

        /* ---- Recent completion activity ---- */
        $completionsList = $completedAtByTask
            ->filter(fn (Carbon $ts, $taskId) => $taskKeyById->has((int) $taskId))
            ->sortByDesc(fn (Carbon $ts) => $ts->timestamp)
            ->map(function (Carbon $ts, $taskId) use ($taskKeyById, $reviewByTaskId, $reworkReviewCount, $reworkHistoryCount) {
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

        /* ---- Overdue tracking ---- */
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

        /* ---- KPI stats ---- */
        $stats = [
            'total_designers' => $totalDesignersAssigned,
            'total_tasks' => $scopedTasks->count(),
            'in_progress' => $scopedTasks->where('status', 'in_progress')->count(),
            'pending' => $scopedTasks->whereIn('status', self::PENDING_STATUSES)->count(),
            'ready_to_start' => $scopedTasks->where('status', 'yet_to_start')->count(),
            'waiting' => $scopedTasks->where('status', 'waiting_confirmation')->count(),
            'clarification_needed' => $scopedTasks->where('status', 'need_clarification')->count(),
            'completed' => $scopedTasks->where('status', 'completed')->count(),
            'overdue' => $scopedTasks->filter($isOverdue)->count(),
            'declined' => $approvedInMonthFor('decline'),
            'split' => $approvedInMonthFor('split'),
            'swapped' => $approvedInMonthFor('swap'),
            'rework_tasks' => $scopedTasks->where('status', 'rework')->count(),
            'pending_reviews' => $scopedTasks->where('status', 'waiting_confirmation')->count(),
            'clarification_tickets' => $scopedTasks->where('status', 'need_clarification')->count(),
        ];

        $months = collect(range(11, 0))->map(fn (int $offset) => [
            'value' => $now->copy()->subMonths($offset)->format('Y-m'),
            'label' => $now->copy()->subMonths($offset)->format('F Y'),
        ])->values();

        /* ---- Performance Trend metric cards (selected-month cohort) ---- */
        $overdueCompletedCount = $scopedTasks
            ->filter(fn (DesignTask $task) => $task->status === 'completed'
                && $task->due_at
                && ($completedAtByTask->get($task->id) ?? null)
                && $task->due_at->lt($completedAtByTask->get($task->id)))
            ->count();
        $trendCards = [
            ['label' => 'Assigned Tasks', 'value' => $scopedTasks->count(), 'color' => '#2970ff'],
            ['label' => 'In Progress', 'value' => $scopedTasks->where('status', 'in_progress')->count(), 'color' => '#7c3aed'],
            ['label' => 'Completed', 'value' => $scopedTasks->where('status', 'completed')->count(), 'color' => '#027a48'],
            ['label' => 'Overdue & Completed', 'value' => $overdueCompletedCount, 'color' => '#f79009'],
            ['label' => 'Declined', 'value' => $approvedInMonthFor('decline'), 'color' => '#c01048'],
        ];
        $trendContext = trim(($designerId ? $designers->firstWhere('id', $designerId)?->name : 'All Designers').' • '.$month->format('M Y'));

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
            'trendCards' => $trendCards,
            'trendContext' => $trendContext,
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

    private function completedRatings(?int $bdId, ?int $designerId, int $page, int $perPage, ?Carbon $monthStart = null, ?Carbon $monthEnd = null): array
    {
        $reviews = DesignTaskBdReview::query()
            ->with(['submitter:id,name', 'task:id,task_id,task_name,designer_id,status,requirements', 'task.designer:id,name'])
            ->where('action', 'completed')
            ->whereHas('task', fn ($q) => $q->where('assigned_by', $bdId))
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
