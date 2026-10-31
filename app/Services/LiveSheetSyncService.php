<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin → Task Monitoring → Daily Report → "Live Excel": mirrors the Daily
 * Report numbers into a Google Sheet with Daily, Weekly, Monthly and Yearly
 * tabs. Each lists every date / week / month / year from the first task until
 * now, newest first; each period row is a collapsed row group that expands to
 * that period's designers. An "AI Insights" tab scores each designer
 * (DesignerPerformanceService) with Period and Designer dropdowns. All tabs
 * are rewritten nightly at 23:59 IST (reports:live-sheet-sync) and on the
 * panel's "Sync now".
 *
 * Grouping is only rebuilt when a tab's row layout changes (a new period, a
 * new designer in a period), so expanded / collapsed rows survive the
 * ordinary number-only updates.
 */
class LiveSheetSyncService
{
    public const SETTING_KEY = 'live_sheet';

    /** Tab title => first-column heading, in tab order. */
    private const TABS = [
        'Daily' => 'Date / Designer',
        'Weekly' => 'Week / Designer',
        'Monthly' => 'Month / Designer',
        'Yearly' => 'Year / Designer',
    ];

    private const DATA_START = 4; // 0-based row index of the first data row (row 5)

    private const RED = ['red' => 0.89, 'green' => 0.024, 'blue' => 0.075];

    private const INSIGHTS_TAB = 'AI Insights';

    /** Hidden tab holding every period's rows; the AI Insights table FILTER()s it. */
    private const INSIGHTS_DATA_TAB = 'AI Insights Data';

    /** Period dropdown value => window length in days (incl. today). */
    private const INSIGHT_PERIODS = ['Daily' => 1, 'Weekly' => 7, 'Monthly' => 30, 'Yearly' => 365];

    private const INSIGHTS_HEADER = [
        'Rank', 'Designer', 'Score (0–100)', 'Grade', 'Trend vs previous period', 'On-time %', 'Avg turnaround (days)',
        'Rework %', 'Creatives / active day', 'BD rating (0–5)', 'Delivered', 'Completed', 'Overdue open',
        'Strengths', 'Concerns', 'Suggestion',
    ];

    public function __construct(
        private GoogleSheetsClient $sheets,
        private DailyWorkReportService $reports,
        private DesignerPerformanceService $performance,
    ) {}

    /** @return array{spreadsheet_id: ?string, title: ?string, enabled: bool, last_synced_at: ?string, last_error: ?string, layouts: array} */
    public function settings(): array
    {
        return array_merge([
            'spreadsheet_id' => null,
            'title' => null,
            'enabled' => false,
            'last_synced_at' => null,
            'last_error' => null,
            'layouts' => [],
        ], (array) AppSetting::getValue(self::SETTING_KEY, []));
    }

    public function active(): bool
    {
        $settings = $this->settings();

        return $settings['enabled'] && $settings['spreadsheet_id'] && $this->sheets->configured();
    }

    /** Accepts a full Google Sheets URL or a bare spreadsheet ID. */
    public static function parseSpreadsheetId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('~/spreadsheets/d/([A-Za-z0-9_-]{20,})~', $input, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{20,}$~', $input) ? $input : null;
    }

    /** Verifies access, saves the sheet and writes the three tabs. Throws on failure. */
    public function connect(string $spreadsheetId, ?int $userId): array
    {
        $sheet = $this->sheets->spreadsheet($spreadsheetId);
        $changed = $spreadsheetId !== $this->settings()['spreadsheet_id'];
        $this->save([
            'spreadsheet_id' => $spreadsheetId,
            'title' => $sheet['title'],
            'enabled' => true,
            'last_error' => null,
            'layouts' => $changed ? [] : $this->settings()['layouts'],
        ], $userId);
        $this->sync();

        return $this->settings();
    }

    public function disconnect(?int $userId): void
    {
        $this->save(['enabled' => false], $userId);
    }

    /**
     * Rewrites every tab (periods + AI Insights). Serialised so a nightly run
     * and a "Sync now" never write over each other; failures are recorded, not thrown.
     */
    public function sync(): bool
    {
        if (! $this->active()) {
            return false;
        }

        $tz = $this->reports->timezone();
        $lock = Cache::lock('live-sheet-sync', 300);

        try {
            $lock->block(30);
            $spreadsheetId = $this->settings()['spreadsheet_id'];

            $now = CarbonImmutable::now($tz);
            $first = DB::table('design_task_status_histories')->min('created_at');
            $start = ($first ? CarbonImmutable::parse($first, (string) config('app.timezone'))->setTimezone($tz) : $now)->startOfDay();
            $report = $this->reports->forRange($start, $now);

            foreach (array_keys(self::TABS) as $index => $tab) {
                $this->writeTab($spreadsheetId, $tab, $index, $this->periods($tab, $start, $now), $report, $start, $now);
            }
            $this->writeInsights($spreadsheetId, count(self::TABS));
            $this->removeOldTabs($spreadsheetId);

            $this->save(['last_synced_at' => $now->toIso8601String(), 'last_error' => null]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Live Excel sync failed', ['error' => $e->getMessage()]);
            $this->save(['last_error' => GoogleSheetsClient::describe($e)]);

            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Periods for one tab, newest first: days, Monday–Sunday weeks, calendar
     * months or calendar years — cut at the first task's day and at now.
     *
     * @return array<int, array{label: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    private function periods(string $tab, CarbonImmutable $start, CarbonImmutable $now): array
    {
        $periods = [];
        for ($from = $start; $from->lessThanOrEqualTo($now); $from = $to->addDay()->startOfDay()) {
            $to = (match ($tab) {
                'Daily' => $from->endOfDay(),
                'Weekly' => $from->endOfWeek(CarbonImmutable::SUNDAY),
                'Monthly' => $from->endOfMonth(),
                default => $from->endOfYear(),
            })->min($now);

            $label = match ($tab) {
                'Daily' => $from->format('D, d M Y'),
                'Weekly' => $from->format('d M').' – '.$to->format('d M Y'),
                'Monthly' => $from->format('F Y'),
                default => $from->format('Y'),
            };
            $periods[] = ['label' => $label, 'from' => $from, 'to' => $to];
        }

        return array_reverse($periods);
    }

    private function writeTab(string $spreadsheetId, string $tab, int $index, array $periods, array $report, CarbonImmutable $start, CarbonImmutable $now): void
    {
        $columns = $this->reports->statusColumns();
        $width = count($columns) + 3;
        $line = fn (string $label, array $c) => array_merge([$label, $c['total'], $c['carried']], array_map(fn ($k) => $c[$k], array_keys($columns)));

        // Rows: each period, then (collapsed) its designers.
        $rows = [];
        foreach ($periods as $period) {
            $counts = $this->reports->counts($report, $period['from'], $period['to']);
            $rows[] = ['level' => 0, 'values' => $line($period['label'], $counts['total'])];
            foreach ($counts['rows'] as $designer => $c) {
                $rows[] = ['level' => 1, 'values' => $line('    '.$designer, $c)];
            }
        }
        $rowLimit = max(2000, self::DATA_START + count($rows) + 100);

        $values = [
            ['Adinn — Daily Report (Live) · '.$tab],
            ['Period: '.$start->format('d M Y').' – '.$now->format('d M Y').' (IST), newest first · Click + on the left to see the designers.'],
            ['Last updated: '.$now->format('d M Y, h:i:s A').' IST'],
            array_merge([self::TABS[$tab], 'Total Tickets', 'Carry from Previous Day / Period'], array_values($columns)),
            ...array_column($rows, 'values'),
        ];

        $tabs = $this->sheets->spreadsheet($spreadsheetId)['tabs'];
        $isNew = ! array_key_exists($tab, $tabs);
        $sheetId = $isNew ? $this->createTab($spreadsheetId, $tab, $index, $width) : $tabs[$tab]['id'];
        if ($isNew || ! $tabs[$tab]['protected']) {
            $this->protectTab($spreadsheetId, $sheetId);
        }
        $this->sheets->batchUpdate($spreadsheetId, [['updateSheetProperties' => [
            'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['rowCount' => $rowLimit]],
            'fields' => 'gridProperties.rowCount',
        ]]]);

        $layout = md5(json_encode(array_map(fn ($r) => [$r['level'], $r['values'][0]], $rows)));
        $rebuild = $isNew || ($this->settings()['layouts'][$tab] ?? null) !== $layout;

        if ($rebuild && ! $isNew) {
            $this->resetTab($spreadsheetId, $sheetId, $tabs[$tab]['rowGroups'], $width, $rowLimit);
        }

        $quoted = "'".str_replace("'", "''", $tab)."'";
        $this->sheets->replaceValues($spreadsheetId, "{$quoted}!A1:Z{$rowLimit}", "{$quoted}!A1", $values);

        if ($rebuild) {
            $this->formatAndGroup($spreadsheetId, $sheetId, $rows, $width);
            $this->save(['layouts' => array_merge($this->settings()['layouts'], [$tab => $layout])]);
        }
    }

    /**
     * "AI Insights": one row per designer from DesignerPerformanceService, for
     * the Period and Designer picked in the dropdowns (C2 / E2). Every period
     * is pre-computed into the hidden data tab and the visible table is a
     * FILTER() over it, so switching the dropdowns needs no server round-trip.
     * The picked values survive each sync.
     */
    private function writeInsights(string $spreadsheetId, int $index): void
    {
        $tz = $this->reports->timezone();
        $data = [array_merge(['Period'], self::INSIGHTS_HEADER)];
        $summary = [['Period', 'Summary']];
        $names = [];

        foreach (self::INSIGHT_PERIODS as $period => $days) {
            $analysis = $this->performance->analyse($days);
            foreach ($this->insightRows($analysis) as $row) {
                $data[] = array_merge([$period], $row);
            }
            $names = array_merge($names, array_column($analysis['designers'], 'name'));
            $team = $analysis['team'];
            $summary[] = [$period, sprintf(
                '%s (%s – %s IST), trend vs the %d day(s) before · Team: %d designer(s), average score %s, top performer %s, %d deliveries, %d open task(s) overdue · Last updated %s IST',
                $period, $analysis['from']->format('d M Y'), $analysis['to']->format('d M Y'), $days,
                $team['designers'], $team['average'] ?? '—', $team['top'] ?? '—', $team['delivered'], $team['overdue'],
                $analysis['to']->format('d M Y, h:i A'),
            )];
        }
        $names = array_values(array_unique($names));
        $count = count($names);
        $last = self::DATA_START + $count; // exclusive end row index of the designer table

        // Hidden data tab: A–Q = period + table columns, T–U = per-period summary line.
        $dataTab = "'".self::INSIGHTS_DATA_TAB."'";
        $this->ensureDataTab($spreadsheetId, $index + 1);
        $this->sheets->replaceValues($spreadsheetId, "{$dataTab}!A1:Q5000", "{$dataTab}!A1", $data);
        $this->sheets->replaceValues($spreadsheetId, "{$dataTab}!T1:U50", "{$dataTab}!T1", $summary);

        $tabs = $this->sheets->spreadsheet($spreadsheetId)['tabs'];
        $tab = $tabs[self::INSIGHTS_TAB] ?? null;
        $width = count(self::INSIGHTS_HEADER);
        $sheetId = $tab ? $tab['id'] : $this->createTab($spreadsheetId, self::INSIGHTS_TAB, $index, $width);

        // Keep the viewer's current picks (defaults: Monthly, All).
        $picked = $tab ? ($this->sheets->values($spreadsheetId, "'".self::INSIGHTS_TAB."'!C2:E2")[0] ?? []) : [];
        $period = array_key_exists($picked[0] ?? '', self::INSIGHT_PERIODS) ? $picked[0] : 'Monthly';
        $designer = in_array($picked[2] ?? '', $names, true) ? $picked[2] : 'All';

        if ($tab) {
            $this->resetTab($spreadsheetId, $sheetId, [], $width, 2000);
        }
        $this->sheets->replaceValues($spreadsheetId, "'".self::INSIGHTS_TAB."'!A1:Z2000", "'".self::INSIGHTS_TAB."'!A1", [
            ['Adinn — AI Insights · Designer Performance'],
            ['', 'Period ▸', $period, 'Designer ▸', $designer, 'Pick a period and a designer (editors) · Scored from this app\'s own data (no external AI).'],
            ['=IFERROR(VLOOKUP($C$2,'.$dataTab.'!T:U,2,FALSE),"")'],
            self::INSIGHTS_HEADER,
            ['=IFERROR(FILTER('.$dataTab.'!B2:Q,'.$dataTab.'!A2:A=$C$2,($E$2="All")+('.$dataTab.'!C2:C=$E$2)),"No data for this selection")'],
        ], 'USER_ENTERED');

        $requests = [];
        // Old protection / colour rules go first so the new ones never stack up.
        foreach ($tab['protections'] ?? [] as $id) {
            $requests[] = ['deleteProtectedRange' => ['protectedRangeId' => $id]];
        }
        for ($i = 0; $i < ($tab['conditionalFormats'] ?? 0); $i++) {
            $requests[] = ['deleteConditionalFormatRule' => ['sheetId' => $sheetId, 'index' => 0]];
        }

        // Dropdowns: highlighted, editable without the protection warning.
        $cell = fn (int $col) => ['sheetId' => $sheetId, 'startRowIndex' => 1, 'endRowIndex' => 2, 'startColumnIndex' => $col, 'endColumnIndex' => $col + 1];
        foreach ([2 => array_keys(self::INSIGHT_PERIODS), 4 => array_merge(['All'], $names)] as $col => $options) {
            $requests[] = ['setDataValidation' => ['range' => $cell($col), 'rule' => [
                'condition' => ['type' => 'ONE_OF_LIST', 'values' => array_map(fn ($o) => ['userEnteredValue' => $o], $options)],
                'strict' => true,
                'showCustomUi' => true,
            ]]];
            $requests[] = $this->format($sheetId, 1, 2, $col, $col + 1, [
                'backgroundColor' => ['red' => 1, 'green' => 0.95, 'blue' => 0.8],
                'horizontalAlignment' => 'CENTER',
                'textFormat' => ['bold' => true],
            ]);
            $requests[] = $this->format($sheetId, 1, 2, $col - 1, $col, ['horizontalAlignment' => 'RIGHT', 'textFormat' => ['bold' => true]]);
        }
        $requests[] = ['addProtectedRange' => ['protectedRange' => [
            'range' => ['sheetId' => $sheetId],
            'unprotectedRanges' => [$cell(2), $cell(4)],
            'description' => 'Live Excel — filled automatically by the Design Task Manager. Use the Period / Designer dropdowns; other edits are overwritten on the next update.',
            'warningOnly' => true,
        ]]];

        // Table: number columns centred, % columns as %, advice wrapped, designer bold.
        $requests[] = $this->format($sheetId, self::DATA_START, $last, 0, 13, ['horizontalAlignment' => 'CENTER', 'verticalAlignment' => 'TOP']);
        $requests[] = $this->format($sheetId, self::DATA_START, $last, 13, $width, ['wrapStrategy' => 'WRAP', 'verticalAlignment' => 'TOP']);
        $requests[] = $this->format($sheetId, self::DATA_START, $last, 1, 2, ['horizontalAlignment' => 'LEFT', 'textFormat' => ['bold' => true]]);
        foreach ([5, 7] as $col) { // On-time % (F), Rework % (H)
            $requests[] = $this->format($sheetId, self::DATA_START, $last, $col, $col + 1, ['numberFormat' => ['type' => 'PERCENT', 'pattern' => '0%']]);
        }

        // Score + grade coloured by grade, whatever rows the filter shows.
        $first = self::DATA_START + 1;
        foreach (['A' => [0.82, 0.98, 0.87], 'B' => [0.91, 0.97, 0.84], 'C' => [1, 0.96, 0.84], 'D' => [1, 0.89, 0.89]] as $grade => [$r, $g, $b]) {
            $requests[] = ['addConditionalFormatRule' => ['index' => 0, 'rule' => [
                'ranges' => [['sheetId' => $sheetId, 'startRowIndex' => self::DATA_START, 'endRowIndex' => $last, 'startColumnIndex' => 2, 'endColumnIndex' => 4]],
                'booleanRule' => [
                    'condition' => ['type' => 'CUSTOM_FORMULA', 'values' => [['userEnteredValue' => "=\$D{$first}=\"{$grade}\""]]],
                    'format' => ['backgroundColor' => ['red' => $r, 'green' => $g, 'blue' => $b], 'textFormat' => ['bold' => true]],
                ],
            ]]];
        }

        if (! $tab) {
            // Narrow number columns; wide text columns for the advice.
            foreach ([0 => 50, 1 => 160, 4 => 130, 13 => 300, 14 => 300, 15 => 320] as $col => $px) {
                $requests[] = ['updateDimensionProperties' => [
                    'range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => $col, 'endIndex' => $col + 1],
                    'properties' => ['pixelSize' => $px],
                    'fields' => 'pixelSize',
                ]];
            }
        }
        $this->sheets->batchUpdate($spreadsheetId, $requests);

        $this->insightCharts($spreadsheetId, $sheetId, $tab['charts'] ?? [], $last);
    }

    /** Table rows (INSIGHTS_HEADER order) for one analysed period, best score first. */
    private function insightRows(array $analysis): array
    {
        // Charted columns (score, on-time %, rework %) hold real numbers or stay blank —
        // a "—" text cell would make Google treat the whole column as text and drop the chart.
        $pct = fn (?float $v) => $v === null ? '' : round($v, 4);

        return array_map(function (array $d, int $i) use ($pct) {
            $m = $d['metrics'];

            return [
                $d['score'] === null ? '—' : $i + 1,
                $d['name'],
                $d['score'] ?? '',
                $d['grade'],
                match (true) {
                    $d['score'] === null => '—',
                    $d['trend'] === null => 'new',
                    $d['trend'] > 0 => '▲ +'.$d['trend'],
                    $d['trend'] < 0 => '▼ '.$d['trend'],
                    default => '● 0',
                },
                $pct($m['on_time_rate']),
                $m['turnaround'] ?? '—',
                $pct($m['rework_rate']),
                $m['per_day'] ?? '—',
                $m['rating'] ?? '—',
                $m['delivered'],
                $m['completed'],
                $m['overdue'],
                $d['strengths'],
                $d['concerns'],
                $d['suggestion'],
            ];
        }, $analysis['designers'], array_keys($analysis['designers']));
    }

    /** The hidden tab the AI Insights FILTER() reads from. */
    private function ensureDataTab(string $spreadsheetId, int $index): void
    {
        $tabs = $this->sheets->spreadsheet($spreadsheetId)['tabs'];
        if (array_key_exists(self::INSIGHTS_DATA_TAB, $tabs)) {
            return;
        }

        $reply = $this->sheets->batchUpdate($spreadsheetId, [['addSheet' => ['properties' => [
            'title' => self::INSIGHTS_DATA_TAB,
            'index' => $index,
            'hidden' => true,
            'gridProperties' => ['rowCount' => 5000, 'columnCount' => 26],
        ]]]]);
        $this->protectTab($spreadsheetId, (int) ($reply['replies'][0]['addSheet']['properties']['sheetId'] ?? 0));
    }

    /**
     * Three charts under the designer table, rebuilt every sync so they always
     * cover the table rows: score, on-time vs rework, and workload. They follow
     * the Period / Designer dropdowns because they read the filtered table.
     */
    private function insightCharts(string $spreadsheetId, int $sheetId, array $oldCharts, int $lastRow): void
    {
        // Header row (index 3) is included so the series take their names from it.
        $column = fn (int $col) => ['sourceRange' => ['sources' => [[
            'sheetId' => $sheetId, 'startRowIndex' => 3, 'endRowIndex' => $lastRow, 'startColumnIndex' => $col, 'endColumnIndex' => $col + 1,
        ]]]];
        $color = fn (float $r, float $g, float $b) => ['red' => $r, 'green' => $g, 'blue' => $b];
        $chart = fn (string $title, string $type, array $series, string $valueAxis, int $offsetX, array $axis = []) => ['addChart' => ['chart' => [
            'spec' => ['title' => $title, 'basicChart' => [
                'chartType' => $type,
                'legendPosition' => count($series) > 1 ? 'BOTTOM_LEGEND' : 'NO_LEGEND',
                'headerCount' => 1,
                'axis' => [['position' => $valueAxis] + $axis],
                'domains' => [['domain' => $column(1)]],
                'series' => array_map(fn ($s) => ['series' => $column($s[0]), 'targetAxis' => $valueAxis, 'color' => $s[1]], $series),
            ]],
            'position' => ['overlayPosition' => [
                'anchorCell' => ['sheetId' => $sheetId, 'rowIndex' => $lastRow + 1, 'columnIndex' => 0],
                'offsetXPixels' => $offsetX,
                'widthPixels' => 500,
                'heightPixels' => 320,
            ]],
        ]]];
        $fixed = fn (float $max) => ['viewWindowOptions' => ['viewWindowMode' => 'EXPLICIT', 'viewWindowMin' => 0, 'viewWindowMax' => $max]];
        $red = $color(0.89, 0.024, 0.075);

        $requests = array_map(fn ($id) => ['deleteEmbeddedObject' => ['objectId' => $id]], $oldCharts);
        $requests[] = $chart('Designer score (0–100)', 'BAR', [[2, $red]], 'BOTTOM_AXIS', 0, ['title' => 'Score'] + $fixed(100));
        $requests[] = $chart('On-time % vs Rework %', 'COLUMN', [[5, $color(0.13, 0.55, 0.13)], [7, $color(0.96, 0.62, 0.04)]], 'LEFT_AXIS', 520, $fixed(1));
        $requests[] = $chart('Workload: delivered, completed, overdue', 'COLUMN', [[10, $color(0.11, 0.42, 0.85)], [11, $color(0.13, 0.55, 0.13)], [12, $red]], 'LEFT_AXIS', 1040);

        $this->sheets->batchUpdate($spreadsheetId, $requests);
    }

    /** Drops the per-day ("30-10-2026") and per-month ("October 2026") tabs earlier versions wrote. */
    private function removeOldTabs(string $spreadsheetId): void
    {
        $old = array_filter(
            $this->sheets->spreadsheet($spreadsheetId)['tabs'],
            fn ($t, $title) => preg_match('/^(\d{2}-\d{2}-\d{4}|(January|February|March|April|May|June|July|August|September|October|November|December) \d{4})$/', $title),
            ARRAY_FILTER_USE_BOTH,
        );
        if ($old !== []) {
            $this->sheets->batchUpdate($spreadsheetId, array_values(array_map(fn ($t) => ['deleteSheet' => ['sheetId' => $t['id']]], $old)));
        }
    }

    /** New tab at its place (Daily, Weekly, Monthly), with the title and header row styled like the Excel download. */
    private function createTab(string $spreadsheetId, string $title, int $index, int $width): int
    {
        $reply = $this->sheets->batchUpdate($spreadsheetId, [[
            'addSheet' => ['properties' => [
                'title' => $title,
                'index' => $index,
                'gridProperties' => [
                    'frozenRowCount' => 4,
                    'frozenColumnCount' => 1,
                    'columnCount' => max(26, $width),
                    'rowCount' => 2000,
                    'rowGroupControlAfter' => false, // +/− on the period row itself
                ],
            ]],
        ]]);
        $sheetId = (int) ($reply['replies'][0]['addSheet']['properties']['sheetId'] ?? 0);

        $this->sheets->batchUpdate($spreadsheetId, [
            $this->format($sheetId, 0, 1, 0, 1, ['textFormat' => ['bold' => true, 'fontSize' => 14, 'foregroundColor' => self::RED]]),
            $this->format($sheetId, 3, 4, 0, $width, [
                'backgroundColor' => self::RED,
                'horizontalAlignment' => 'CENTER',
                'verticalAlignment' => 'MIDDLE',
                'wrapStrategy' => 'WRAP',
                'textFormat' => ['bold' => true, 'foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1]],
            ]),
            ['updateDimensionProperties' => [
                'range' => ['sheetId' => $sheetId, 'dimension' => 'COLUMNS', 'startIndex' => 0, 'endIndex' => 1],
                'properties' => ['pixelSize' => 230],
                'fields' => 'pixelSize',
            ]],
            ['updateDimensionProperties' => [
                'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => 3, 'endIndex' => 4],
                'properties' => ['pixelSize' => 48],
                'fields' => 'pixelSize',
            ]],
        ]);

        return $sheetId;
    }

    /**
     * Team members get Editor access so they can use the + / − groups
     * (Google hides those from view-only users). A warning-only protection
     * makes them confirm before changing a number; the next sync rewrites it anyway.
     */
    private function protectTab(string $spreadsheetId, int $sheetId): void
    {
        $this->sheets->batchUpdate($spreadsheetId, [['addProtectedRange' => ['protectedRange' => [
            'range' => ['sheetId' => $sheetId],
            'description' => 'Live Excel — filled automatically by the Design Task Manager. Use + / − to expand; edits are overwritten on the next update.',
            'warningOnly' => true,
        ]]]]);
    }

    /** Before a layout rebuild: drop old row groups (deepest first), unhide rows and clear data-row formatting. */
    private function resetTab(string $spreadsheetId, int $sheetId, array $groups, int $width, int $rowLimit): void
    {
        usort($groups, fn ($a, $b) => ($b['depth'] ?? 1) <=> ($a['depth'] ?? 1));
        $requests = array_map(fn ($g) => ['deleteDimensionGroup' => ['range' => [
            'sheetId' => $sheetId,
            'dimension' => 'ROWS',
            'startIndex' => (int) ($g['range']['startIndex'] ?? 0),
            'endIndex' => (int) $g['range']['endIndex'],
        ]]], $groups);
        $requests[] = ['updateDimensionProperties' => [
            'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => self::DATA_START, 'endIndex' => $rowLimit],
            'properties' => ['hiddenByUser' => false],
            'fields' => 'hiddenByUser',
        ]];
        $requests[] = ['repeatCell' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => self::DATA_START, 'endRowIndex' => $rowLimit, 'startColumnIndex' => 0, 'endColumnIndex' => $width],
            'cell' => ['userEnteredFormat' => (object) []],
            'fields' => 'userEnteredFormat',
        ]];

        $this->sheets->batchUpdate($spreadsheetId, $requests);
    }

    /** Designer rows in grey, period rows bold on light red, and one collapsed group of designers under each period. */
    private function formatAndGroup(string $spreadsheetId, int $sheetId, array $rows, int $width): void
    {
        $last = self::DATA_START + count($rows);
        $requests = [
            $this->format($sheetId, self::DATA_START, $last, 1, $width, ['horizontalAlignment' => 'CENTER']),
            $this->format($sheetId, self::DATA_START, $last, 0, $width, ['textFormat' => ['foregroundColor' => ['red' => 0.28, 'green' => 0.33, 'blue' => 0.4]]]),
        ];
        $groups = [];

        foreach ($rows as $i => $row) {
            if ($row['level'] !== 0) {
                continue;
            }
            $index = self::DATA_START + $i;
            $requests[] = $this->format($sheetId, $index, $index + 1, 0, $width, [
                'backgroundColor' => ['red' => 0.992, 'green' => 0.925, 'blue' => 0.933],
                'textFormat' => ['bold' => true, 'foregroundColor' => ['red' => 0.063, 'green' => 0.094, 'blue' => 0.157]],
            ]);

            $end = $i + 1;
            while ($end < count($rows) && $rows[$end]['level'] > 0) {
                $end++;
            }
            if ($end > $i + 1) {
                $groups[] = ['start' => $index + 1, 'end' => self::DATA_START + $end];
            }
        }

        foreach ($groups as $g) {
            $requests[] = ['addDimensionGroup' => ['range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => $g['start'], 'endIndex' => $g['end']]]];
        }
        foreach ($groups as $g) {
            $requests[] = ['updateDimensionGroup' => [
                'dimensionGroup' => [
                    'range' => ['sheetId' => $sheetId, 'dimension' => 'ROWS', 'startIndex' => $g['start'], 'endIndex' => $g['end']],
                    'depth' => 1,
                    'collapsed' => true,
                ],
                'fields' => 'collapsed',
            ]];
        }

        $this->sheets->batchUpdate($spreadsheetId, $requests);
    }

    private function format(int $sheetId, int $rowStart, int $rowEnd, int $colStart, int $colEnd, array $format): array
    {
        return ['repeatCell' => [
            'range' => ['sheetId' => $sheetId, 'startRowIndex' => $rowStart, 'endRowIndex' => $rowEnd, 'startColumnIndex' => $colStart, 'endColumnIndex' => $colEnd],
            'cell' => ['userEnteredFormat' => $format],
            'fields' => 'userEnteredFormat('.implode(',', array_keys($format)).')',
        ]];
    }

    private function save(array $changes, ?int $userId = null): void
    {
        AppSetting::putValue(self::SETTING_KEY, array_merge($this->settings(), $changes), $userId);
    }
}
