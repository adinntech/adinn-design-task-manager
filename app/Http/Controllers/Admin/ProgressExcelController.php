<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DailyWorkReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Task Monitoring → "Daily Report" tab: downloads the designer-wise
 * ticket counts (total, carried forward, per Kanban status) for one IST day
 * or a period.
 */
class ProgressExcelController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function export(Request $request, DailyWorkReportService $reports): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'bd_id' => ['nullable', 'integer'],
            'designer_id' => ['nullable', 'integer'],
            'task' => ['nullable', 'string', 'max:60'],
        ]);

        $tz = $reports->timezone();
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], $tz);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $data['to'], $tz);

        if ($to->isAfter(CarbonImmutable::now($tz)->startOfDay())) {
            throw ValidationException::withMessages(['to' => 'Future dates have no work yet.']);
        }
        if ($from->isAfter($to)) {
            throw ValidationException::withMessages(['from' => 'From date must be on or before To date.']);
        }
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a period of '.self::MAX_RANGE_DAYS.' days or less.']);
        }

        $filters = array_filter([
            'bd_id' => $data['bd_id'] ?? null,
            'designer_id' => $data['designer_id'] ?? null,
            'task' => isset($data['task']) ? trim($data['task']) : null,
        ], fn ($v) => $v !== null && $v !== '');

        $report = $reports->filter($reports->forRange($from, $to), $filters);

        return $reports->download($report, $reports->filename($from, $to));
    }
}
