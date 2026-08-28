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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskExportController extends Controller
{
    private const HEADER_FILL = 'E30613';

    private const HEADER_TEXT = 'FFFFFF';

    private const BODY_TEXT = '15171C';

    private const BORDER_COLOR = 'E5E7EB';

    private const HEADER = [
        'Task ID', 'Task Name', 'Designer', 'BD', 'Vertical', 'Task Type',
        'Created At', 'Assigned At', 'Due Date', 'Completed At', 'Status',
        'Creatives', 'Progress Details', 'Rework Details',
        'Split/Swap/Decline Details', 'Ratings', 'Deadline Result', 'Cross-Month Info',
    ];

    private const COLUMN_WIDTHS = [
        14, 28, 18, 18, 14, 24, 12, 12, 12, 12, 18, 18, 28, 24, 22, 32, 24, 22,
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

            $terminalAt = match ($task->status) {
                'completed' => $completedAt,
                'swap_tasks' => $swapRespondedAtByTask->get($task->id),
                'decline_tasks' => $declineRespondedAtByTask->get($task->id),
                default => null,
            };

            $rows[] = [
                $task->task_id,
                $task->display_task_name ?? $task->task_name,
                $task->designer?->name ?? '—',
                $task->assigner?->name ?? '—',
                ucwords(str_replace('_', ' ', (string) $task->vertical)),
                $task->task_nature,
                optional($task->created_at)->format('d M Y'),
                optional($task->assigned_at)->format('d M Y'),
                optional($task->due_at)->format('d M Y'),
                optional($completedAt)->format('d M Y'),
                $statuses[$task->status] ?? ucwords(str_replace('_', ' ', (string) $task->status)),
                "Total: {$totalCreatives}\nDone: {$completedCreatives}\nRemaining: {$remaining}\nProgress: {$percentage}%",
                $this->progressCell($progressByTask->get($task->id, collect())),
                $this->reworkCell($reworkCount, $reworkCreatives, $reworkMinutes > 0 ? $reporting->minutesToText((int) $reworkMinutes) : null, $reworkReviewsByTask->get($task->id, collect())),
                $this->requestCell($splitCount, $swapCount, $declineCount),
                $this->ratingCell($rating),
                $this->completionText($completion),
                $this->crossMonthCell($task->assigned_at?->format('M Y'), $terminalAt?->format('M Y')),
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
            ['Active Tasks', $totals['active']],
            ['Completed', $totals['completed']],
            ['Overdue', $totals['overdue']],
            ['Split Requests', $totals['split']],
            ['Swap Requests', $totals['swap']],
            ['Decline Requests', $totals['decline']],
            ['Rework Count', $totals['reworks']],
            ['Rework Creative Count', $totals['reworkCreatives']],
            ['Average Rating', $ratingCount > 0 ? DesignTaskBdReview::formatRating($ratingSum / $ratingCount) : '—'],
        ];

        $spreadsheet = $this->buildSpreadsheet($rows, $summary);
        $filename = 'designer-head-tasks-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildSpreadsheet(array $rows, array $summary): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $tasksSheet = $spreadsheet->getActiveSheet();
        $tasksSheet->setTitle('Tasks');
        $tasksSheet->fromArray(self::HEADER, null, 'A1');
        $this->styleHeaderRow($tasksSheet, count(self::HEADER), 1);

        if (empty($rows)) {
            $tasksSheet->setCellValue('A2', 'No matching tasks for the selected filters');
            $tasksSheet->mergeCells('A2:'.$this->columnLetter(count(self::HEADER)).'2');
        } else {
            $tasksSheet->fromArray($rows, null, 'A2');
            $this->styleBodyRows($tasksSheet, count(self::HEADER), 2, count($rows) + 1, true);
        }

        foreach (self::COLUMN_WIDTHS as $index => $width) {
            $tasksSheet->getColumnDimension($this->columnLetter($index + 1))->setWidth($width);
        }
        $tasksSheet->freezePane('A2');

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->fromArray(['Metric', 'Value'], null, 'A1');
        $this->styleHeaderRow($summarySheet, 2, 1);
        $summarySheet->fromArray($summary, null, 'A2');
        $this->styleBodyRows($summarySheet, 2, 2, count($summary) + 1, false);
        $summarySheet->getColumnDimension('A')->setWidth(26);
        $summarySheet->getColumnDimension('B')->setWidth(16);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function styleHeaderRow(Worksheet $sheet, int $columnCount, int $row): void
    {
        $range = 'A'.$row.':'.$this->columnLetter($columnCount).$row;
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::HEADER_FILL]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function styleBodyRows(Worksheet $sheet, int $columnCount, int $startRow, int $endRow, bool $wrap): void
    {
        $range = 'A'.$startRow.':'.$this->columnLetter($columnCount).$endRow;
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['color' => ['rgb' => self::BODY_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => $wrap],
        ]);
    }

    private function columnLetter(int $oneBasedIndex): string
    {
        return Coordinate::stringFromColumnIndex($oneBasedIndex);
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
     * "Single Submission" / "3 submissions", one "d M: n" line per submission date.
     */
    private function progressCell(Collection $records): string
    {
        if ($records->isEmpty()) {
            return 'No submissions yet';
        }

        $byDate = $records
            ->groupBy(fn ($record) => $record->submitted_at->format('d M'))
            ->map(fn (Collection $rows) => $rows->sum('completed_count'));

        $label = $byDate->count() > 1 ? $byDate->count().' submissions' : 'Single Submission';
        $lines = $byDate->map(fn ($count, $date) => "{$date}: {$count}")->values()->all();

        return implode("\n", array_merge([$label], $lines));
    }

    private function reworkCell(int $count, int $creatives, ?string $timeSpentText, Collection $records): string
    {
        if ($count === 0) {
            return 'No rework';
        }

        $lines = [
            $count.' '.($count === 1 ? 'rework' : 'reworks'),
            $creatives.' creatives',
        ];

        if ($timeSpentText !== null) {
            $lines[] = $timeSpentText.' spent';
        }

        foreach ($records as $record) {
            $lines[] = $record->created_at->format('d M').': '.(int) $record->number_of_creatives;
        }

        return implode("\n", $lines);
    }

    private function requestCell(int $split, int $swap, int $decline): string
    {
        if ($split === 0 && $swap === 0 && $decline === 0) {
            return 'No requests';
        }

        return "Split: {$split}\nSwap: {$swap}\nDecline: {$decline}";
    }

    private function ratingCell(?DesignTaskBdReview $rating): string
    {
        if (! $rating) {
            return 'Not rated yet';
        }

        $lines = [];

        if ($rating->overall_rating !== null) {
            $lines[] = 'Overall: '.DesignTaskBdReview::formatRating($rating->overall_rating).' / 5';
        }

        $lines[] = sprintf(
            'DA: %s | DS: %s | RI: %s | MD: %s | CS: %s',
            DesignTaskBdReview::formatRating($rating->designer_attitude),
            DesignTaskBdReview::formatRating($rating->design_satisfaction),
            DesignTaskBdReview::formatRating($rating->rework_iteration),
            DesignTaskBdReview::formatRating($rating->meeting_deadline),
            DesignTaskBdReview::formatRating($rating->client_satisfaction)
        );

        if (! empty($rating->comment)) {
            $lines[] = 'Comment: '.$rating->comment;
        }

        $lines[] = 'Rated by: '.($rating->submitter?->name ?? '—').' on '.$rating->created_at->format('d M Y');

        return implode("\n", $lines);
    }

    private function crossMonthCell(?string $startedMonth, ?string $completedMonth): string
    {
        $lines = ['Started: '.($startedMonth ?? '—')];

        if ($completedMonth !== null) {
            $lines[] = 'Completed: '.$completedMonth;
            $lines[] = 'Cross-Month: '.($completedMonth !== $startedMonth ? 'Yes' : 'No');
        } else {
            $lines[] = 'Cross-Month: No';
        }

        return implode("\n", $lines);
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
