<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Live Excel → "AI Insights" tab: a scoring model built only from this app's
 * own data (no external AI service). For each designer over a window of the
 * last N calendar days including today (1 = today, 7, 30, 365 — the tab's
 * Period dropdown), compared with the N days before:
 *
 *   on-time %     deliveries (first move to Waiting for BD Review) on/before due
 *   turnaround    days from assignment to that first delivery
 *   rework %      delivered tasks sent back to Rework
 *   throughput    creatives submitted per active day (progress uploads)
 *   BD rating     average overall rating BD gave on completion (0–5)
 *   overdue open  open tasks already past their due time (now)
 *
 * Speed and throughput are scored against the team's own median, so the
 * baseline moves with the team. Score = weighted mean of the available
 * components (0–100) minus an overdue penalty; strengths, concerns and a
 * suggestion are written from the same numbers.
 */
class DesignerPerformanceService
{
    /** Component weights (renormalised over the components a designer has data for). */
    private const WEIGHTS = ['on_time' => 30, 'quality' => 20, 'rating' => 20, 'speed' => 15, 'throughput' => 15];

    private const OVERDUE_PENALTY = 5;      // points per overdue open task …

    private const OVERDUE_PENALTY_MAX = 25; // … capped

    private const OPEN_EXCLUDED = ['draft', 'completed', 'swap_tasks', 'pending_bd_approval', 'bd_rejected'];

    public function __construct(private ProgressSubmissionReportService $submissions) {}

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, team: array, designers: array<int, array>}
     */
    public function analyse(int $days = 30): array
    {
        $tz = $this->submissions->timezone();
        $to = CarbonImmutable::now($tz);
        $from = $to->startOfDay()->subDays($days - 1); // e.g. 1 → today from 00:00, 7 → the last 7 days incl. today
        $previousFrom = $from->subDays($days);

        $current = $this->window($from, $to);
        $previous = $this->window($previousFrom, $from->subSecond());
        $overdue = $this->overdueOpen($to);

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $designer) use ($current, $previous, $overdue) {
                $metrics = ($current['metrics'][$designer->id] ?? $this->emptyMetrics()) + ['overdue' => $overdue[$designer->id] ?? 0];
                $score = $this->score($metrics, $current['medians']);
                $before = $this->score(($previous['metrics'][$designer->id] ?? $this->emptyMetrics()) + ['overdue' => 0], $previous['medians']);

                return ['name' => $designer->name, 'metrics' => $metrics, 'medians' => $current['medians']]
                    + $score
                    + ['trend' => $score['score'] === null || $before['score'] === null ? null : $score['score'] - $before['score']]
                    + $this->insights($metrics, $score['components'], $current['medians']);
            })
            ->sortByDesc(fn ($d) => $d['score'] ?? -1)
            ->values()
            ->all();

        $scored = array_filter($designers, fn ($d) => $d['score'] !== null);

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'team' => [
                'designers' => count($designers),
                'scored' => count($scored),
                'average' => $scored === [] ? null : (int) round(array_sum(array_column($scored, 'score')) / count($scored)),
                'top' => $scored === [] ? null : reset($scored)['name'],
                'delivered' => array_sum(array_map(fn ($d) => $d['metrics']['delivered'], $designers)),
                'overdue' => array_sum($overdue),
                'medians' => $current['medians'],
            ],
            'designers' => $designers,
        ];
    }

    /** Raw metrics per designer id for one window, plus team medians. */
    private function window(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = $this->submissions->window($from, $to);
        $appTz = (string) config('app.timezone');

        $firstAssigned = DB::table('design_task_status_histories')
            ->where('to_status', '!=', 'draft')
            ->groupBy('design_task_id')
            ->selectRaw('design_task_id, MIN(created_at) AS at')
            ->pluck('at', 'design_task_id');

        // First delivery (move to Waiting for BD Review) per task, inside the window.
        $deliveries = DB::table('design_task_status_histories')
            ->where('to_status', 'waiting_confirmation')
            ->groupBy('design_task_id')
            ->selectRaw('design_task_id, MIN(created_at) AS at')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$start, $end])
            ->pluck('at', 'design_task_id');

        $reworked = DB::table('design_task_status_histories')
            ->where('to_status', 'rework')
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('design_task_id')
            ->flip();

        $completed = DB::table('design_task_status_histories')
            ->where('to_status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('design_task_id');

        $taskIds = $deliveries->keys()->merge($reworked->keys())->merge($completed)->unique();
        $tasks = DesignTask::withTrashed()->whereIn('id', $taskIds)->get(['id', 'designer_id', 'due_at'])->keyBy('id');

        $metrics = [];
        $bucket = function (?int $designerId) use (&$metrics) {
            $metrics[$designerId] ??= $this->emptyMetrics();

            return $designerId;
        };

        foreach ($deliveries as $taskId => $at) {
            $task = $tasks->get($taskId);
            if (! $task || ! $task->designer_id) {
                continue;
            }
            $d = $bucket($task->designer_id);
            $deliveredAt = CarbonImmutable::parse($at, $appTz);
            $metrics[$d]['delivered']++;
            $metrics[$d]['on_time'] += $task->due_at && $deliveredAt->lessThanOrEqualTo($task->due_at) ? 1 : 0;
            if (isset($firstAssigned[$taskId])) {
                $metrics[$d]['turnaround_sum'] += max(0, CarbonImmutable::parse($firstAssigned[$taskId], $appTz)->diffInHours($deliveredAt)) / 24;
            }
            $metrics[$d]['reworked'] += isset($reworked[$taskId]) ? 1 : 0;
        }
        foreach ($completed as $taskId) {
            if ($designerId = $tasks->get($taskId)?->designer_id) {
                $metrics[$bucket($designerId)]['completed']++;
            }
        }

        foreach (DB::table('design_task_eod_records')
            ->where('update_type', 'progress')
            ->whereBetween('submitted_at', [$start, $end])
            ->groupBy('designer_id')
            ->selectRaw('designer_id, SUM(completed_count) AS creatives, COUNT(DISTINCT DATE(submitted_at)) AS active_days')
            ->get() as $row) {
            $d = $bucket((int) $row->designer_id);
            $metrics[$d]['creatives'] = (int) $row->creatives;
            $metrics[$d]['active_days'] = (int) $row->active_days;
        }

        foreach (DB::table('design_task_bd_reviews as r')
            ->join('design_tasks as t', 't.id', '=', 'r.design_task_id')
            ->whereNotNull('r.overall_rating')
            ->whereBetween('r.created_at', [$start, $end])
            ->groupBy('t.designer_id')
            ->selectRaw('t.designer_id, AVG(r.overall_rating) AS rating, COUNT(*) AS reviews')
            ->get() as $row) {
            if ($row->designer_id) {
                $d = $bucket((int) $row->designer_id);
                $metrics[$d]['rating'] = round((float) $row->rating, 2);
                $metrics[$d]['reviews'] = (int) $row->reviews;
            }
        }

        foreach ($metrics as &$m) {
            $m['on_time_rate'] = $m['delivered'] ? $m['on_time'] / $m['delivered'] : null;
            $m['turnaround'] = $m['delivered'] ? round($m['turnaround_sum'] / $m['delivered'], 1) : null;
            $m['rework_rate'] = $m['delivered'] ? $m['reworked'] / $m['delivered'] : null;
            $m['per_day'] = $m['active_days'] ? round($m['creatives'] / $m['active_days'], 1) : null;
        }
        unset($m);

        return [
            'metrics' => $metrics,
            'medians' => [
                'turnaround' => $this->median(array_column($metrics, 'turnaround')),
                'per_day' => $this->median(array_column($metrics, 'per_day')),
            ],
        ];
    }

    /** @return array<int, int> designer id => open tasks already past due */
    private function overdueOpen(CarbonImmutable $now): array
    {
        return DesignTask::query()
            ->whereNotNull('designer_id')
            ->whereNotIn('status', self::OPEN_EXCLUDED)
            ->where('due_at', '<', $now)
            ->groupBy('designer_id')
            ->selectRaw('designer_id, COUNT(*) AS c')
            ->pluck('c', 'designer_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }

    /** @return array{score: ?int, grade: string, components: array<string, float>} */
    private function score(array $m, array $medians): array
    {
        $components = array_filter([
            'on_time' => $m['on_time_rate'],
            'quality' => $m['rework_rate'] === null ? null : 1 - min(1, $m['rework_rate']),
            'rating' => $m['rating'] === null ? null : $m['rating'] / 5,
            // Faster than the team median scores higher; capped so one outlier cannot dominate.
            'speed' => $m['turnaround'] && $medians['turnaround'] ? min(1.25, $medians['turnaround'] / max(0.1, $m['turnaround'])) / 1.25 : null,
            'throughput' => $m['per_day'] && $medians['per_day'] ? min(1.25, $m['per_day'] / $medians['per_day']) / 1.25 : null,
        ], fn ($v) => $v !== null);

        if ($components === []) {
            return ['score' => null, 'grade' => '—', 'components' => []];
        }

        $weight = array_sum(array_intersect_key(self::WEIGHTS, $components));
        $raw = array_sum(array_map(fn ($k, $v) => self::WEIGHTS[$k] * $v, array_keys($components), $components)) / $weight * 100;
        $score = (int) max(0, min(100, round($raw - min(self::OVERDUE_PENALTY_MAX, self::OVERDUE_PENALTY * $m['overdue']))));

        return [
            'score' => $score,
            'grade' => match (true) {
                $score >= 85 => 'A',
                $score >= 70 => 'B',
                $score >= 55 => 'C',
                default => 'D',
            },
            'components' => $components,
        ];
    }

    /** Plain-language strengths, concerns and one suggestion, from the same numbers. */
    private function insights(array $m, array $c, array $medians): array
    {
        if ($c === [] && $m['overdue'] === 0) {
            return ['strengths' => '—', 'concerns' => '—', 'suggestion' => 'No deliveries, uploads or ratings in this period — not enough data to judge.'];
        }

        $strengths = [];
        $concerns = [];
        $fixes = []; // in priority order; the first one becomes the suggestion

        if ($m['overdue'] > 0) {
            $concerns[] = $m['overdue'].' open task(s) past due';
            $fixes[] = 'Clear the '.$m['overdue'].' overdue task(s) first.';
        }

        if (isset($c['on_time'])) {
            $line = sprintf('%d of %d deliveries on time (%d%%)', $m['on_time'], $m['delivered'], round($c['on_time'] * 100));
            if ($c['on_time'] >= 0.85) {
                $strengths[] = $line;
            } elseif ($c['on_time'] < 0.7) {
                $concerns[] = $line;
                $fixes[] = 'Start earlier on near-due tasks — '.($m['delivered'] - $m['on_time']).' delivery(ies) were late.';
            }
        }

        if (isset($c['quality'])) {
            if ($c['quality'] >= 0.9) {
                $strengths[] = 'little or no rework';
            } elseif ($c['quality'] < 0.75) {
                $concerns[] = $m['reworked'].' task(s) sent to rework';
                $fixes[] = 'Confirm requirements with BD before designing — '.$m['reworked'].' task(s) came back for rework.';
            }
        }

        if (isset($c['rating'])) {
            $line = 'BD rating '.number_format($m['rating'], 1).'/5';
            if ($m['rating'] >= 4.3) {
                $strengths[] = $line;
            } elseif ($m['rating'] < 3.5) {
                $concerns[] = $line;
                $fixes[] = 'Read the BD review comments — average rating is '.number_format($m['rating'], 1).'/5.';
            }
        }

        if (isset($c['speed']) && $medians['turnaround']) {
            $line = $m['turnaround'].' day(s) to deliver vs team '.$medians['turnaround'];
            if ($m['turnaround'] <= $medians['turnaround'] * 0.8) {
                $strengths[] = 'faster than team ('.$line.')';
            } elseif ($m['turnaround'] > $medians['turnaround'] * 1.3) {
                $concerns[] = 'slower than team ('.$line.')';
                $fixes[] = 'Break big tasks into daily progress uploads to shorten turnaround.';
            }
        }

        if (isset($c['throughput']) && $medians['per_day']) {
            $line = $m['per_day'].' creatives/day vs team '.$medians['per_day'];
            if ($m['per_day'] >= $medians['per_day'] * 1.2) {
                $strengths[] = 'high output ('.$line.')';
            } elseif ($m['per_day'] < $medians['per_day'] * 0.7) {
                $concerns[] = 'low output ('.$line.')';
                $fixes[] = 'Output is below the team — check workload or blockers.';
            }
        }

        return [
            'strengths' => $strengths === [] ? '—' : ucfirst(implode('; ', $strengths)).'.',
            'concerns' => $concerns === [] ? '—' : ucfirst(implode('; ', $concerns)).'.',
            'suggestion' => $fixes[0] ?? 'Keep it up — steady performance across the board.',
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'delivered' => 0, 'on_time' => 0, 'turnaround_sum' => 0.0, 'reworked' => 0, 'completed' => 0,
            'creatives' => 0, 'active_days' => 0, 'rating' => null, 'reviews' => 0,
            'on_time_rate' => null, 'turnaround' => null, 'rework_rate' => null, 'per_day' => null,
        ];
    }

    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        if ($values === []) {
            return null;
        }
        sort($values);
        $mid = intdiv(count($values), 2);

        return round(count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 1);
    }
}
