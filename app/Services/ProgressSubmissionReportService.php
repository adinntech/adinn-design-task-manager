<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin → Task Monitoring → "Submitted Progress Details": the persisted Task
 * Updation / Rework submissions (design_task_eod_records) in an IST time
 * window. Shared by the on-page tab (TaskActivityController) and the daily
 * work Excel (DailyWorkReportService), so both always agree.
 */
class ProgressSubmissionReportService
{
    public function timezone(): string
    {
        return (string) config('daily-report.timezone', 'Asia/Kolkata');
    }

    /**
     * Stored timestamps are in the app timezone; the window is chosen in IST.
     *
     * @return array{0: string, 1: string}
     */
    public function window(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $appTz = (string) config('app.timezone');

        return [
            $from->setTimezone($appTz)->format('Y-m-d H:i:s'),
            $to->setTimezone($appTz)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Submissions in the window, narrowed by the page's BD / Designer / Task filters.
     *
     * @param  array{bd_id?: int|string|null, designer_id?: int|string|null, task?: string|null}  $filters
     * @param  array{0: string, 1: string}  $window
     */
    public function query(array $filters, array $window): Builder
    {
        $bdId = $filters['bd_id'] ?? null;
        $term = isset($filters['task']) ? trim((string) $filters['task']) : '';

        return DesignTaskEodRecord::query()
            ->whereIn('update_type', ['progress', 'rework'])
            ->whereBetween('submitted_at', $window)
            ->when($filters['designer_id'] ?? null, fn ($q, $id) => $q->where('designer_id', $id))
            ->when($bdId || $term !== '', fn ($q) => $q->whereIn(
                'design_task_id',
                DesignTask::withTrashed()
                    ->where('status', '!=', 'draft')
                    ->when($bdId, fn ($t, $id) => $t->where('assigned_by', $id))
                    ->when($term !== '', fn ($t) => $t->where(fn ($w) => $w
                        ->where('task_id', 'like', '%'.$term.'%')
                        ->orWhere('task_name', 'like', '%'.$term.'%')))
                    ->select('id')
            ));
    }
}
