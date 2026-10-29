<?php

namespace App\Services;

use App\Models\DesignTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Task Monitoring → "Daily Report" tab: ticket counts in the BD
 * Kanban's column names, for any span of IST days.
 *
 * For every span (a day, a week, a month or the whole period):
 *  - Tickets      = tasks still open when the span started (carried from the
 *                   previous day / period) plus tasks created in it.
 *  - Carry        = open when the span started — e.g. In Progress at the end
 *                   of day 1 is carried into day 2.
 *  - Status cols  = where each of those tickets stands at the end of the span.
 *
 * Layout follows the span length: 1 day → designer table; up to 7 days →
 * dates; up to 31 days → weeks → dates; longer → months → weeks → dates.
 * Deeper levels are collapsed Excel row groups (+ / − on the left), and every
 * date expands to its designers.
 *
 * Statuses at any moment come from design_task_status_histories (append-only,
 * one row per transition), loaded once and replayed per span. A past day
 * covers 00:00 – 23:59 IST, today runs till now. The designer is the task's
 * current designer.
 */
class DailyWorkReportService
{
    /** Statuses that end a ticket — not carried into the next day. */
    private const TERMINAL = ['completed', 'swap_tasks', 'bd_rejected'];

    private const BRAND_RED = 'E30613';

    /** Row fill per outline level of a period row (month / week / date). */
    private const LEVEL_FILL = ['FDECEE', 'F4F6F9', 'FFFFFF', 'FFFFFF'];

    public function __construct(private ProgressSubmissionReportService $submissions) {}

    public function timezone(): string
    {
        return $this->submissions->timezone();
    }

    /**
     * Report columns after Designer / Total / Carry: status key => BD Kanban title.
     *
     * @return array<string, string>
     */
    public function statusColumns(): array
    {
        $labels = DesignTaskStatusService::STATUSES;

        return [
            'pending_bd_approval' => 'New Task Requests',
            'draft' => 'Drafts',
        ] + collect(['assigned_tasks', 'review_analysis', 'need_clarification', 'yet_to_start', 'in_progress',
            'waiting_confirmation', 'rework', 'prepare_printing_file', 'completed'])
            ->mapWithKeys(fn ($key) => [$key => $labels[$key]])
            ->all();
    }

    /** day | week | month | year — how the Excel groups this range. */
    public function layout(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;

        return match (true) {
            $days <= 1 => 'day',
            $days <= 7 => 'week',
            $days <= 31 => 'month',
            default => 'year',
        };
    }

    /**
     * Tasks and their status history up to the end of the range: 00:00 of
     * $from to 23:59 of $to (or now, for today).
     *
     * @return array{meta: array, tasks: array<int, array>, history: array<int, array<int, array{0: string, 1: string}>>}
     */
    public function forRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay()->min(CarbonImmutable::now($this->timezone()));
        [, $endAt] = $this->submissions->window($start, $end);

        $history = [];
        foreach (DB::table('design_task_status_histories')
            ->where('created_at', '<=', $endAt)
            ->orderBy('id')
            ->select('design_task_id', 'to_status', 'created_at')
            ->cursor() as $h) {
            $history[$h->design_task_id][] = [(string) $h->created_at, (string) $h->to_status];
        }

        $tasks = DesignTask::withTrashed()
            ->whereIn('id', array_keys($history))
            ->with('designer:id,name')
            ->get(['id', 'task_id', 'task_name', 'designer_id', 'assigned_by'])
            ->mapWithKeys(fn (DesignTask $task) => [$task->id => [
                'task_id' => (string) $task->task_id,
                'task_name' => (string) $task->task_name,
                'bd_id' => $task->assigned_by,
                'designer_id' => $task->designer_id,
                'designer' => $task->designer?->name ?? 'Unassigned',
            ]])
            ->all();

        return [
            'meta' => ['from' => $start->format('Y-m-d H:i:s'), 'to' => $end->format('Y-m-d H:i:s')],
            'tasks' => $tasks,
            'history' => $history,
        ];
    }

    /** Narrows a report to the page's BD / Designer / Task filters. */
    public function filter(array $report, array $filters): array
    {
        $term = mb_strtolower(trim((string) ($filters['task'] ?? '')));

        $report['tasks'] = array_filter($report['tasks'], fn (array $t) => (empty($filters['bd_id']) || (int) $t['bd_id'] === (int) $filters['bd_id'])
            && (empty($filters['designer_id']) || (int) $t['designer_id'] === (int) $filters['designer_id'])
            && ($term === '' || str_contains(mb_strtolower($t['task_id'].' '.$t['task_name']), $term)));

        return $report;
    }

    /**
     * Counts for one span, per designer and in total.
     *
     * @return array{rows: array<string, array>, total: array}
     */
    public function counts(array $report, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$startAt, $endAt] = $this->submissions->window($from, $to);
        $blank = ['total' => 0, 'carried' => 0] + array_fill_keys(array_keys($this->statusColumns()), 0);
        $rows = [];
        $total = $blank;

        foreach ($report['tasks'] as $id => $task) {
            $history = $report['history'][$id] ?? [];
            if ($history === [] || $history[0][0] > $endAt) {
                continue; // did not exist yet
            }

            $atStart = null;
            $atEnd = null;
            foreach ($history as [$at, $status]) {
                if ($at > $endAt) {
                    break;
                }
                if ($at < $startAt) {
                    $atStart = $status;
                }
                $atEnd = $status;
            }

            $carried = $atStart !== null && ! in_array($atStart, self::TERMINAL, true);
            if (! $carried && $history[0][0] < $startAt) {
                continue; // closed before this span
            }

            $rows[$task['designer']] ??= $blank;
            foreach ([&$rows[$task['designer']], &$total] as &$bucket) {
                $bucket['total']++;
                $bucket['carried'] += $carried ? 1 : 0;
                if (array_key_exists($atEnd, $bucket)) {
                    $bucket[$atEnd]++;
                }
            }
            unset($bucket);
        }

        uksort($rows, 'strnatcasecmp');

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Nested spans for the Excel: months → weeks → dates, trimmed to the layout.
     *
     * @return array<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable, children: array}>
     */
    private function spans(CarbonImmutable $from, CarbonImmutable $to, string $layout): array
    {
        return match ($layout) {
            'week' => $this->daySpans($from, $to),
            'month' => $this->weekSpans($from, $to),
            default => $this->monthSpans($from, $to),
        };
    }

    private function daySpans(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spans = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $spans[] = ['label' => $day->format('D, d M Y'), 'from' => $day, 'to' => $day->endOfDay()->min($to), 'children' => []];
        }

        return $spans;
    }

    /** Monday–Sunday weeks, cut at the range edges. */
    private function weekSpans(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spans = [];
        $n = 1;
        for ($start = $from->startOfDay(); $start->lessThanOrEqualTo($to); $start = $end->addDay()->startOfDay()) {
            $end = $start->endOfWeek(CarbonImmutable::SUNDAY)->min($to);
            $label = 'Week '.$n++.' · '.$start->format('d M').($start->isSameDay($end) ? '' : ' – '.$end->format('d M'));
            $spans[] = ['label' => $label, 'from' => $start, 'to' => $end, 'children' => $this->daySpans($start, $end)];
        }

        return $spans;
    }

    private function monthSpans(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $spans = [];
        for ($start = $from->startOfDay(); $start->lessThanOrEqualTo($to); $start = $end->addDay()->startOfDay()) {
            $end = $start->endOfMonth()->min($to);
            $spans[] = ['label' => $start->format('F Y'), 'from' => $start, 'to' => $end, 'children' => $this->weekSpans($start, $end)];
        }

        return $spans;
    }

    public function filename(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return 'Daily_Report_'.$from->format('d-m-Y').($from->isSameDay($to) ? '' : '_to_'.$to->format('d-m-Y')).'.xlsx';
    }

    public function download(array $report, string $filename): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($report);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function spreadsheet(array $report): Spreadsheet
    {
        $tz = $this->timezone();
        $from = CarbonImmutable::parse($report['meta']['from'], $tz);
        $to = CarbonImmutable::parse($report['meta']['to'], $tz);
        $layout = $this->layout($from, $to);
        $header = array_merge([$layout === 'day' ? 'Designer' : 'Period / Designer', 'Total Tickets', 'Carry from Previous Day / Period'], array_values($this->statusColumns()));
        $lastCol = Coordinate::stringFromColumnIndex(count($header));

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()->setCreator((string) config('app.name'))->setTitle('Daily Report');
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daily Report');
        $sheet->setShowSummaryBelow(false); // +/− sits on the period row, above its details

        $period = $from->isSameDay($to)
            ? $from->format('l, d M Y').' ('.$from->format('h:i A').' – '.$to->format('h:i A').' IST)'
            : $from->format('d M Y, h:i A').'  to  '.$to->format('d M Y, h:i A').' (IST)';

        $sheet->setCellValue('A1', 'Adinn — Daily Report');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::BRAND_RED);
        $sheet->setCellValueExplicit('A2', 'Period: '.$period, DataType::TYPE_STRING);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('475467');

        $sheet->fromArray($header, null, 'A4');
        $headerStyle = $sheet->getStyle("A4:{$lastCol}4");
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND_RED);
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(4)->setRowHeight(42);

        $row = 5;
        $whole = $this->counts($report, $from, $to);

        if ($layout === 'day') {
            foreach ($whole['rows'] as $designer => $counts) {
                $this->writeCounts($sheet, $row++, $designer, $counts);
            }
        } else {
            foreach ($this->spans($from, $to, $layout) as $span) {
                $row = $this->writeSpan($sheet, $report, $span, 0, $row, $lastCol);
            }
        }

        $this->writeCounts($sheet, $row, $layout === 'day' ? 'Total' : 'Total (whole period)', $whole['total']);
        $totalStyle = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAECF0');

        $sheet->getStyle("A4:{$lastCol}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D5DD');
        $sheet->getStyle("B5:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B5:B{$row}")->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth($layout === 'day' ? 24 : 32);
        for ($c = 2; $c <= count($header); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth($c === 3 ? 16 : 13);
        }
        $sheet->freezePane('B5');

        $note = $row + 2;
        $notes = [
            'Total Tickets = tickets carried from the previous day / period + tickets created in it. The status columns show where those tickets stand at the end of that row\'s period (Transferred / Rejected tickets are counted in the total only).',
        ];
        if ($layout !== 'day') {
            $notes[] = 'Click + on the left (or the 1 / 2 / 3 buttons above the row numbers) to expand '.[
                'week' => 'each date into designers.',
                'month' => 'weeks → dates → designers.',
                'year' => 'months → weeks → dates → designers.',
            ][$layout];
        }
        foreach ($notes as $text) {
            $sheet->setCellValue("A{$note}", $text);
            $sheet->mergeCells("A{$note}:{$lastCol}{$note}");
            $sheet->getStyle("A{$note}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($note)->setRowHeight(28);
            $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('667085');
            $note++;
        }

        return $spreadsheet;
    }

    /** Writes a period row, then (collapsed) its sub-periods or, for a date, its designers. Returns the next free row. */
    private function writeSpan(Worksheet $sheet, array $report, array $span, int $level, int $row, string $lastCol): int
    {
        $counts = $this->counts($report, $span['from'], $span['to']);
        $this->writeCounts($sheet, $row, $span['label'], $counts['total']);
        $style = $sheet->getStyle("A{$row}:{$lastCol}{$row}");
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LEVEL_FILL[$level] ?? 'FFFFFF');
        $sheet->getStyle("A{$row}")->getAlignment()->setIndent($level * 2);
        $this->outline($sheet, $row, $level);

        $parent = $row++;
        if ($span['children'] !== []) {
            foreach ($span['children'] as $child) {
                $row = $this->writeSpan($sheet, $report, $child, $level + 1, $row, $lastCol);
            }
        } else {
            foreach ($counts['rows'] as $designer => $designerCounts) {
                $this->writeCounts($sheet, $row, $designer, $designerCounts);
                $sheet->getStyle("A{$row}")->getAlignment()->setIndent(($level + 1) * 2);
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->getColor()->setRGB('475467');
                $this->outline($sheet, $row++, $level + 1);
            }
        }

        if ($row > $parent + 1) {
            $sheet->getRowDimension($parent)->setCollapsed(true);
        }

        return $row;
    }

    /** Outline level for a row; anything below the top level starts hidden (collapsed). */
    private function outline(Worksheet $sheet, int $row, int $level): void
    {
        if ($level > 0) {
            $sheet->getRowDimension($row)->setOutlineLevel($level)->setVisible(false);
        }
    }

    private function writeCounts(Worksheet $sheet, int $row, string $label, array $counts): void
    {
        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $values = [$counts['total'], $counts['carried']];
        foreach (array_keys($this->statusColumns()) as $status) {
            $values[] = $counts[$status];
        }
        $sheet->fromArray($values, null, "B{$row}", true);
    }
}
