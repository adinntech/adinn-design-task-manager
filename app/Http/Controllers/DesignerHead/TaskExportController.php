<?php

namespace App\Http\Controllers\DesignerHead;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskExportController extends Controller
{
    private const HEADER = [
        'Task ID', 'Task Name', 'Task Created At', 'Vertical', 'Task Nature', 'Party Type', 'Party Name',
        'Contact Person', 'Mobile Number', 'Priority', 'Designer', 'BD', 'Assigned Date', 'Due Date',
        'Total Creatives', 'Status',
        'Completed Creative Count', 'Remaining Creative Count', 'Progress %',
        'Submission Label (Single/Multiple)', 'Progress Submission Breakdown',
        'Rework Count', 'Rework Creatives Total', 'Rework Cycle Breakdown', 'Rework Time Spent',
        'Completion Status', 'Completion Days',
        'Split Requests', 'Swap Requests', 'Decline Requests',
        'Designer Attitude', 'Design Satisfaction', 'Rework Iteration', 'Meeting Deadline',
        'Client Satisfaction', 'Overall Rating', 'BD Rating Comment', 'Rated By', 'Rated At',
        'Report Month', 'Cross-Month Task', 'Started Month', 'Completed Month',
    ];

    public function export(
        Request $request,
        DesignerHeadTaskBoardService $boardService,
        DesignTaskReportingService $reporting
    ): StreamedResponse {
        abort_unless($request->user()?->role === 'designer_head', 403);

        $filters = [
            'search' => (string) $request->query('search', ''),
            'vertical' => (string) $request->query('vertical', ''),
            'priority' => (string) $request->query('priority', ''),
            'designerId' => (string) $request->query('designer_id', ''),
            'bdId' => (string) $request->query('bd_id', ''),
            'period' => (string) $request->query('period', 'current_month'),
            'dateFrom' => (string) $request->query('date_from', ''),
            'dateTo' => (string) $request->query('date_to', ''),
        ];

        $board = $boardService->build($filters);
        $tasks = $board['visibleTasks'];
        $statuses = $board['statuses'];
        $taskIds = $tasks->pluck('id');

        $eodProgress = $reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'progress'), $taskIds, 'SUM(completed_count)');
        $eodRework = $reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'rework'), $taskIds, 'SUM(completed_count)');
        $reworkSentBack = $reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'SUM(number_of_creatives)');
        $reworkReviewCount = $reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'COUNT(*)');
        $reworkHistoryCount = $reporting->mapByTask(
            DesignTaskStatusHistory::query()->where('to_status', 'rework')->where('change_source', 'bd_rework'),
            $taskIds,
            'COUNT(*)'
        );

        $completedAtByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->where('to_status', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->pluck('created_at', 'design_task_id');

        $swapRespondedAtByTask = $this->respondedAtByTask($taskIds, 'swap');
        $declineRespondedAtByTask = $this->respondedAtByTask($taskIds, 'decline');

        $progressByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskEodRecord::query()
                ->where('update_type', 'progress')
                ->whereIn('design_task_id', $taskIds)
                ->orderBy('submitted_at')
                ->get(['design_task_id', 'completed_count', 'submitted_at'])
                ->groupBy('design_task_id');

        $reworkReviewsByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->where('action', 'rework')
                ->whereIn('design_task_id', $taskIds)
                ->orderBy('created_at')
                ->get(['design_task_id', 'created_at', 'number_of_creatives'])
                ->groupBy('design_task_id');

        $reviewHistory = $taskIds->isEmpty()
            ? collect()
            : DesignTaskStatusHistory::query()
                ->whereIn('design_task_id', $taskIds)
                ->whereIn('to_status', ['waiting_confirmation', 'rework', 'completed'])
                ->orderBy('design_task_id')
                ->orderBy('created_at')
                ->get(['design_task_id', 'to_status', 'created_at']);

        $taskKeyById = $tasks->keyBy('id');
        $reviewCyclesByTask = $reviewHistory
            ->groupBy('design_task_id')
            ->map(fn (Collection $historyRows, $taskId) => $taskKeyById->has((int) $taskId)
                ? $reporting->reviewCyclesFor($taskKeyById->get((int) $taskId), $historyRows, $reworkReviewsByTask, $reworkReviewCount, $reworkHistoryCount)
                : []);

        $completedReviewByTask = $taskIds->isEmpty()
            ? collect()
            : DesignTaskBdReview::query()
                ->where('action', 'completed')
                ->whereIn('design_task_id', $taskIds)
                ->with('submitter:id,name')
                ->get()
                ->keyBy('design_task_id');

        $requestCounts = $taskIds->isEmpty()
            ? collect()
            : DesignTaskRequest::query()
                ->whereIn('design_task_id', $taskIds)
                ->whereIn('request_type', ['split', 'swap', 'decline'])
                ->get(['design_task_id', 'request_type'])
                ->groupBy('design_task_id')
                ->map(fn (Collection $rows) => $rows->countBy('request_type'));

        $rows = [];
        $totals = [
            'total' => 0, 'completed' => 0, 'active' => 0, 'overdue' => 0,
            'split' => 0, 'swap' => 0, 'decline' => 0, 'reworks' => 0, 'reworkCreatives' => 0,
        ];
        $ratingSum = 0.0;
        $ratingCount = 0;

        foreach ($tasks as $task) {
            /** @var DesignTask $task */
            $completedAt = $completedAtByTask->get($task->id);
            $reworkMinutes = collect($reviewCyclesByTask->get($task->id, []))
                ->pluck('rework.duration_minutes')
                ->filter(fn ($value) => $value !== null)
                ->sum();
            $reworkCount = $reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount);
            $reworkCreatives = (int) ($reworkSentBack[$task->id] ?? 0);
            $completion = $reporting->completionInfo($task, $completedAt);
            $requestCountRow = $requestCounts->get($task->id, collect());
            $splitCount = (int) $requestCountRow->get('split', 0);
            $swapCount = (int) $requestCountRow->get('swap', 0);
            $declineCount = (int) $requestCountRow->get('decline', 0);
            $rating = $completedReviewByTask->get($task->id);

            $completedCreatives = $reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack);
            $totalCreatives = (int) $task->total_creatives;
            $remaining = max(0, $totalCreatives - $completedCreatives);
            $percentage = min(100, (int) round(($completedCreatives / max(1, $totalCreatives)) * 100));

            [$submissionLabel, $submissionBreakdown] = $this->submissionBreakdown($progressByTask->get($task->id, collect()));
            $reworkBreakdown = $this->reworkBreakdown($reworkReviewsByTask->get($task->id, collect()));
            $reworkTimeText = $reworkMinutes > 0 ? $reporting->minutesToText((int) $reworkMinutes) : '';

            $terminalAt = match ($task->status) {
                'completed' => $completedAt,
                'swap_tasks' => $swapRespondedAtByTask->get($task->id),
                'decline_tasks' => $declineRespondedAtByTask->get($task->id),
                default => null,
            };

            $startedMonth = $task->assigned_at?->format('M Y');
            $completedMonth = $terminalAt?->format('M Y');
            $reportMonth = $completedMonth ?? $startedMonth;
            $crossMonth = ($completedMonth !== null && $completedMonth !== $startedMonth) ? 'Yes' : 'No';

            $rows[] = [
                $task->task_id,
                $task->display_task_name ?? $task->task_name,
                optional($task->created_at)->format('d M Y'),
                ucwords(str_replace('_', ' ', (string) $task->vertical)),
                $task->task_nature,
                ucfirst((string) $task->party_type),
                $task->party_name,
                $task->contact_person,
                $task->mobile_number,
                ucfirst((string) $task->priority),
                $task->designer?->name ?? '—',
                $task->assigner?->name ?? '—',
                optional($task->assigned_at)->format('d M Y'),
                optional($task->due_at)->format('d M Y'),
                $totalCreatives,
                $statuses[$task->status] ?? ucwords(str_replace('_', ' ', (string) $task->status)),
                $completedCreatives,
                $remaining,
                $percentage,
                $submissionLabel,
                $submissionBreakdown,
                $reworkCount,
                $reworkCreatives,
                $reworkBreakdown,
                $reworkTimeText,
                $this->completionText($completion),
                $completion['days'],
                $splitCount,
                $swapCount,
                $declineCount,
                $rating ? DesignTaskBdReview::formatRating($rating->designer_attitude) : '',
                $rating ? DesignTaskBdReview::formatRating($rating->design_satisfaction) : '',
                $rating ? DesignTaskBdReview::formatRating($rating->rework_iteration) : '',
                $rating ? DesignTaskBdReview::formatRating($rating->meeting_deadline) : '',
                $rating ? DesignTaskBdReview::formatRating($rating->client_satisfaction) : '',
                $rating ? DesignTaskBdReview::formatRating($rating->overall_rating) : '',
                $rating?->comment ?? '',
                $rating?->submitter?->name ?? '',
                $rating ? $rating->created_at->format('d M Y') : '',
                $reportMonth,
                $crossMonth,
                $startedMonth,
                $completedMonth ?? '',
            ];

            $totals['total']++;
            $totals['completed'] += $task->status === 'completed' ? 1 : 0;
            $totals['active'] += $task->status !== 'completed' ? 1 : 0;
            $totals['overdue'] += $completion['status'] === 'overdue' ? 1 : 0;
            $totals['split'] += $splitCount;
            $totals['swap'] += $swapCount;
            $totals['decline'] += $declineCount;
            $totals['reworks'] += $reworkCount;
            $totals['reworkCreatives'] += $reworkCreatives;

            if ($rating && $rating->overall_rating !== null) {
                $ratingSum += (float) $rating->overall_rating;
                $ratingCount++;
            }
        }

        $summary = [
            ['Total Tasks', $totals['total']],
            ['Completed', $totals['completed']],
            ['Active', $totals['active']],
            ['Overdue', $totals['overdue']],
            ['Split Requests', $totals['split']],
            ['Swap Requests', $totals['swap']],
            ['Decline Requests', $totals['decline']],
            ['Total Reworks', $totals['reworks']],
            ['Total Rework Creatives', $totals['reworkCreatives']],
            ['Average Rating', $ratingCount > 0 ? DesignTaskBdReview::formatRating($ratingSum / $ratingCount) : ''],
        ];

        $filename = 'designer-head-tasks-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::HEADER, ',', '"', '\\');

            if (empty($rows)) {
                fputcsv($out, ['No matching tasks for the selected filters'], ',', '"', '\\');
                fclose($out);

                return;
            }

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }

            fputcsv($out, [], ',', '"', '\\');
            fputcsv($out, ['SUMMARY'], ',', '"', '\\');
            foreach ($summary as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function completionText(array $completion): string
    {
        return match ($completion['status']) {
            'on_time' => 'On Time',
            'late' => 'Completed '.$completion['days'].' days after due date',
            'overdue' => $completion['days'].' days overdue',
            default => 'In Progress',
        };
    }

    /**
     * @return array{0:string,1:string} [Single/Multiple label, "d M: n | d M: n" breakdown]
     */
    private function submissionBreakdown(Collection $records): array
    {
        if ($records->isEmpty()) {
            return ['', ''];
        }

        $byDate = $records
            ->groupBy(fn ($record) => $record->submitted_at->format('d M'))
            ->map(fn (Collection $rows) => $rows->sum('completed_count'));

        $breakdown = $byDate->map(fn ($count, $date) => "{$date}: {$count}")->implode(' | ');
        $label = $byDate->count() > 1 ? 'Multiple Submissions ('.$byDate->count().')' : 'Single Submission';

        return [$label, $breakdown];
    }

    private function reworkBreakdown(Collection $records): string
    {
        return $records
            ->map(fn ($record) => $record->created_at->format('d M').': '.(int) $record->number_of_creatives)
            ->implode(' | ');
    }

    /**
     * responded_at (designer_head_action_at, falling back to admin_action_at)
     * per task for one approved request type.
     */
    private function respondedAtByTask(Collection $taskIds, string $requestType): Collection
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
