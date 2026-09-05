<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared xlsx task-report builder, extracted from the original Designer Head
 * export so Designer Head, Designer and BD can all produce the same report
 * structure/columns from the same underlying board data — scoped by whatever
 * filters (designerId/bdId forced per role) the caller passes in.
 */
class DesignTaskExportService
{
    private const HEADER_FILL = 'E30613';

    private const HEADER_TEXT = 'FFFFFF';

    private const BODY_TEXT = '15171C';

    private const BORDER_COLOR = 'E5E7EB';

    private const LATE_ROW_FILL = 'FFC7CE';

    private const LATE_ROW_TEXT = '9C0006';

    private const GREEN_FILL = 'C6EFCE';

    private const GREEN_TEXT = '006100';

    /** 1-based column index of the "Status" header — where the completed-late green indicator is applied. */
    private const STATUS_COLUMN = 12;

    /** Rows above the column header on the Tasks sheet, reserved for the Report Summary block. */
    private const SUMMARY_ROWS = 6;

    private const HEADER = [
        'S.NO', 'Task ID', 'Task Name', 'Designer', 'BD', 'Vertical', 'Task Type',
        'Created At', 'Assigned At', 'Due Date', 'Completed At', 'Status',
        'Creatives', 'Progress Details', 'Rework Details',
        'Split/Swap/Decline Details', 'Ratings', 'Deadline Result', 'Cross-Month Info',
        'Period Continuation',
    ];

    private const COLUMN_WIDTHS = [
        6, 14, 28, 18, 18, 14, 24, 12, 12, 12, 12, 18, 18, 28, 24, 22, 32, 24, 22,
    ];

    public function __construct(
        private DesignerHeadTaskBoardService $boardService,
        private DesignTaskReportingService $reporting,
    ) {}

    /**
     * @param  array{search:string,vertical:string,priority:string,designerId:string,bdId:string,period:string,dateFrom:string,dateTo:string}  $filters
     */
    public function export(array $filters, string $filenamePrefix): StreamedResponse
    {
        $board = $this->boardService->build($filters);
        // Origin-period tasks + their swap-shadow counterparts only — excludes the
        // board's read-only "continuation from" extras (tasks that originated in an
        // earlier period) so the report never double-counts a task across two runs.
        $tasks = $board['tasks']->concat($board['swapShadowTasks'])->values();
        $statuses = $board['statuses'];
        $taskIds = $tasks->pluck('id');
        $reportSummary = $this->reportSummaryLines($filters, $board['periodStart'], $board['periodEnd']);

        $eodProgress = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'progress'), $taskIds, 'SUM(completed_count)');
        $eodRework = $this->reporting->mapByTask(DesignTaskEodRecord::query()->where('update_type', 'rework'), $taskIds, 'SUM(completed_count)');
        $reworkSentBack = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'SUM(number_of_creatives)');
        $reworkReviewCount = $this->reporting->mapByTask(DesignTaskBdReview::query()->where('action', 'rework'), $taskIds, 'COUNT(*)');
        $reworkHistoryCount = $this->reporting->mapByTask(
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
                ? $this->reporting->reviewCyclesFor($taskKeyById->get((int) $taskId), $historyRows, $reworkReviewsByTask, $reworkReviewCount, $reworkHistoryCount)
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
        $overdueCompletedRowNumbers = [];
        $activeOverdueRowNumbers = [];
        $rowNumber = 0;
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
            $reworkCount = $this->reporting->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount);
            $reworkCreatives = (int) ($reworkSentBack[$task->id] ?? 0);
            $completion = $this->reporting->completionInfo($task, $completedAt);
            $requestCountRow = $requestCounts->get($task->id, collect());
            $splitCount = (int) $requestCountRow->get('split', 0);
            $swapCount = (int) $requestCountRow->get('swap', 0);
            $declineCount = (int) $requestCountRow->get('decline', 0);
            $rating = $completedReviewByTask->get($task->id);

            $completedCreatives = $this->reporting->completedFor($task, $eodProgress, $eodRework, $reworkSentBack);
            $totalCreatives = (int) $task->total_creatives;
            $remaining = max(0, $totalCreatives - $completedCreatives);
            $percentage = min(100, (int) round(($completedCreatives / max(1, $totalCreatives)) * 100));

            $terminalAt = match ($task->status) {
                'completed' => $completedAt,
                'swap_tasks' => $swapRespondedAtByTask->get($task->id),
                'decline_tasks' => $declineRespondedAtByTask->get($task->id),
                default => null,
            };

            $rowNumber++;
            if ($completion['status'] === 'late') {
                $overdueCompletedRowNumbers[] = $rowNumber;
            } elseif ($completion['status'] === 'overdue') {
                $activeOverdueRowNumbers[] = $rowNumber;
            }

            $rows[] = [
                $rowNumber,
                $task->task_id,
                $task->display_task_name ?? $task->task_name,
                $task->designer?->name ?? '—',
                $task->assigner?->name ?? '—',
                ucwords(str_replace('_', ' ', (string) $task->vertical)),
                $task->task_nature,
                optional($task->created_at)->format('d M Y'),
                optional($task->assigned_at)->format('d M Y'),
                optional($task->due_at)->format('d M Y h:i A'),
                optional($completedAt)->format('d M Y'),
                $statuses[$task->status] ?? ucwords(str_replace('_', ' ', (string) $task->status)),
                "Total: {$totalCreatives}\nDone: {$completedCreatives}\nRemaining: {$remaining}\nProgress: {$percentage}%",
                $this->progressCell($progressByTask->get($task->id, collect())),
                $this->reworkCell($reworkCount, $reworkCreatives, $reworkMinutes > 0 ? $this->reporting->minutesToText((int) $reworkMinutes) : null, $reworkReviewsByTask->get($task->id, collect())),
                $this->requestCell($splitCount, $swapCount, $declineCount),
                $this->ratingCell($rating),
                $this->completionText($completion),
                $this->crossMonthCell($task->assigned_at?->format('M Y'), $terminalAt?->format('M Y')),
                $task->continuation_label ?? 'No',
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

        $spreadsheet = $this->buildSpreadsheet($rows, $summary, $reportSummary, $overdueCompletedRowNumbers, $activeOverdueRowNumbers);
        $filename = $filenamePrefix.'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  string[][]  $reportSummary               ['Label', 'Value'] pairs shown in the Report
     *                                                   Summary block above the column header.
     * @param  int[]  $overdueCompletedRowNumbers        1-based position of each row (within $rows) that
     *                                                    completed after its due date — red row, green
     *                                                    "Status" cell (still overdue on completion, but done).
     * @param  int[]  $activeOverdueRowNumbers           1-based position of each row (within $rows) that is
     *                                                    still open and past its due date — red row only.
     */
    private function buildSpreadsheet(array $rows, array $summary, array $reportSummary, array $overdueCompletedRowNumbers = [], array $activeOverdueRowNumbers = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $columnCount = count(self::HEADER);
        $headerRow = self::SUMMARY_ROWS + 1;
        $firstDataRow = $headerRow + 1;

        $tasksSheet = $spreadsheet->getActiveSheet();
        $tasksSheet->setTitle('Tasks');
        $this->writeReportSummary($tasksSheet, $reportSummary, $columnCount);

        $tasksSheet->fromArray(self::HEADER, null, 'A'.$headerRow);
        $this->styleHeaderRow($tasksSheet, $columnCount, $headerRow);

        if (empty($rows)) {
            $tasksSheet->setCellValue('A'.$firstDataRow, 'No matching tasks for the selected filters');
            $tasksSheet->mergeCells('A'.$firstDataRow.':'.$this->columnLetter($columnCount).$firstDataRow);
        } else {
            $tasksSheet->fromArray($rows, null, 'A'.$firstDataRow);
            $this->styleBodyRows($tasksSheet, $columnCount, $firstDataRow, $firstDataRow + count($rows) - 1, true);

            foreach ($activeOverdueRowNumbers as $rowNumber) {
                $this->styleOverdueRow($tasksSheet, $columnCount, $firstDataRow - 1 + $rowNumber);
            }

            foreach ($overdueCompletedRowNumbers as $rowNumber) {
                $sheetRow = $firstDataRow - 1 + $rowNumber;
                $this->styleOverdueRow($tasksSheet, $columnCount, $sheetRow);
                $this->styleCompletedStatusCell($tasksSheet, $sheetRow);
            }
        }

        foreach (self::COLUMN_WIDTHS as $index => $width) {
            $tasksSheet->getColumnDimension($this->columnLetter($index + 1))->setWidth($width);
        }
        $tasksSheet->freezePane('A'.$firstDataRow);

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

    /**
     * Overrides the fill/font for one already-styled body row — either still open
     * and past its due date, or completed after its due date — with the same red
     * "overdue" indication either way. Rows completed on time (or not yet due)
     * keep the normal white styling.
     */
    private function styleOverdueRow(Worksheet $sheet, int $columnCount, int $row): void
    {
        $range = 'A'.$row.':'.$this->columnLetter($columnCount).$row;
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['color' => ['rgb' => self::LATE_ROW_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LATE_ROW_FILL]],
        ]);
    }

    /**
     * Green "Status" cell on an otherwise-red overdue row — the row's red
     * stays the overdue indication, this one cell marks that the task did
     * get completed, so the two colors never fight for the same space.
     */
    private function styleCompletedStatusCell(Worksheet $sheet, int $row): void
    {
        $cell = $this->columnLetter(self::STATUS_COLUMN).$row;
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::GREEN_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::GREEN_FILL]],
        ]);
    }

    /**
     * @return string[][] ['Label', 'Value'] pairs for the Report Summary block.
     */
    private function reportSummaryLines(array $filters, Carbon $periodStart, Carbon $periodEnd): array
    {
        if (! empty($filters['overdue'])) {
            return [
                ['Period Type', 'Overdue (All Periods)'],
                ['From', 'N/A — overdue tasks are shown regardless of the selected period'],
                ['To', 'N/A'],
                ['Generated At', now()->format('d M Y h:i A')],
            ];
        }

        $periodTypeLabel = match ($filters['period'] ?? 'current_month') {
            'last_month' => 'Last Month',
            'custom' => 'Custom Period',
            default => 'Current Month',
        };

        return [
            ['Period Type', $periodTypeLabel],
            ['From', $periodStart->format('d M Y')],
            ['To', $periodEnd->format('d M Y')],
            ['Generated At', now()->format('d M Y h:i A')],
        ];
    }

    /**
     * Writes the Report Summary block into rows 1-{self::SUMMARY_ROWS} of the
     * Tasks sheet, above the column header — the first thing visible when the
     * file opens (row 1, column A).
     */
    private function writeReportSummary(Worksheet $sheet, array $reportSummary, int $columnCount): void
    {
        $lastColumn = $this->columnLetter(min(6, $columnCount));

        $sheet->setCellValue('A1', 'Report Summary');
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $row = 2;
        foreach ($reportSummary as [$label, $value]) {
            $cell = 'A'.$row;
            $sheet->setCellValue($cell, $label.': '.$value);
            $sheet->mergeCells($cell.':'.$lastColumn.$row);
            $sheet->getStyle($cell)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::BODY_TEXT]],
            ]);
            $row++;
        }
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
