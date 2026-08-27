<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'designer', 403);

        $designerId = (int) $request->user()->id;

        $tasks = DesignTask::query()
            ->with(['assigner:id,name'])
            ->where('designer_id', $designerId)
            ->latest('assigned_at')
            ->get();

        $taskIds = $tasks->pluck('id');

        $ownRequests = DesignTaskRequest::query()
            ->with([
                'task:id,task_id,task_name,designer_id',
                'task.designer:id,name',
                'approvedDesigner:id,name',
                'targetDesigner:id,name',
            ])
            ->where('requested_by', $designerId)
            ->latest()
            ->get();

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
            // "Waiting for BD Review" = designer's own work is done and sitting in
            // waiting_confirmation; every task only reaches 'completed' once BD rates
            // it (same DB transaction in Bd\AssignedTaskController::completeWithRating),
            // so there is no "completed but unrated" state to detect separately.
            'waiting_bd_review' => $tasks->where('status', 'waiting_confirmation')->count(),
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
        $completions = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->get(['design_task_id', 'created_at']);

        $monthlyTrend = collect(range(5, 0))
            ->map(function (int $offset) use ($completions) {
                $month = now()->startOfMonth()->subMonths($offset);
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

        $reviewCards = $completedReviews
            ->filter(fn (DesignTaskBdReview $review) => filled($review->comment))
            ->map(function (DesignTaskBdReview $review) use ($tasks, $completedAtByTask) {
                $task = $tasks->firstWhere('id', $review->design_task_id);

                return [
                    'rating' => DesignTaskBdReview::roundToHalfStar($review->overall_rating),
                    'comment' => $review->comment,
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

        return view('designer.dashboard', [
            'stats' => $stats,
            'recentTasks' => $tasks->take(12),
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
        ]);
    }
}
