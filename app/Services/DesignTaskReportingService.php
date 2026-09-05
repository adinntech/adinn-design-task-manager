<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Batched, N+1-safe per-task math shared by the Designer Head Dashboard and
 * the Designer Head task export: creative-completion totals, rework counts,
 * on-time/late/overdue text, and BD-review-cycle (submission -> decision)
 * pairing with rework duration derived from status-history timestamps.
 */
class DesignTaskReportingService
{
    public function mapByTask($query, Collection $taskIds, string $expression): Collection
    {
        if ($taskIds->isEmpty()) {
            return collect();
        }

        return $query
            ->whereIn('design_task_id', $taskIds)
            ->groupBy('design_task_id')
            ->selectRaw('design_task_id, '.$expression.' AS total')
            ->pluck('total', 'design_task_id')
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) $value]);
    }

    public function completedFor(DesignTask $task, Collection $eodProgress, Collection $eodRework, Collection $reworkSentBack): int
    {
        $completed = (int) ($eodProgress[$task->id] ?? 0)
            + (int) ($eodRework[$task->id] ?? 0)
            - (int) ($reworkSentBack[$task->id] ?? 0);

        return max(0, min((int) $task->total_creatives, $completed));
    }

    /**
     * Timeliness against the ORIGINAL due date, using the actual completion timestamp —
     * never derived from rework/status text. A task completed after its due date is
     * "late" (with days-late), not "overdue" (which only applies while still incomplete).
     */
    public function completionInfo(DesignTask $task, ?Carbon $completedAt): array
    {
        if ($completedAt) {
            $isLate = (bool) ($task->due_at && $completedAt->gt($task->due_at));
            $daysLate = $isLate ? (int) $task->due_at->diffInDays($completedAt) : 0;

            return ['status' => $isLate ? 'late' : 'on_time', 'days' => $daysLate];
        }

        if ($task->status !== 'completed' && $task->due_at && $task->due_at->lt(now())) {
            return ['status' => 'overdue', 'days' => (int) $task->due_at->diffInDays(now())];
        }

        return ['status' => 'in_progress', 'days' => 0];
    }

    public function reworkCountFor(DesignTask $task, Collection $reworkReviewCount, Collection $reworkHistoryCount): int
    {
        $reviewCount = (int) ($reworkReviewCount[$task->id] ?? 0);

        return $reviewCount > 0
            ? $reviewCount
            : (int) ($reworkHistoryCount[$task->id] ?? 0);
    }

    public function durationText(?Carbon $from, ?Carbon $to): ?string
    {
        if (! $from || ! $to || $to->lt($from)) {
            return null;
        }

        $diff = $from->diff($to);

        if ($diff->d > 0) {
            return $diff->d.'d '.$diff->h.'h';
        }

        return $diff->h > 0 || $diff->i > 0 ? $diff->h.'h '.$diff->i.'m' : '0m';
    }

    public function minutesToText(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $days.'d '.$hours.'h';
        }

        return $hours > 0 || $mins > 0 ? $hours.'h '.$mins.'m' : '0m';
    }

    /**
     * One task's BD-review cycles (submission -> decision pairs), from the immutable
     * status-history log. When a cycle's decision is Rework, it also carries that
     * rework's own count/creatives/start/return timestamps and Designer's rework
     * duration — shared by the Task Details "rework spent time" total and the BD
     * Review Turnaround table, so both agree on timing.
     */
    public function reviewCyclesFor(DesignTask $task, Collection $historyRows, Collection $reworkCyclesByTask, Collection $reworkReviewCount, Collection $reworkHistoryCount): array
    {
        // Pair each "moved to BD review" event with the next decision after it;
        // an unmatched trailing submission means BD hasn't decided yet (pending).
        $cycles = [];
        $submittedAt = null;
        foreach ($historyRows as $row) {
            if ($row->to_status === 'waiting_confirmation') {
                $submittedAt = $row->created_at;
            } elseif ($submittedAt !== null) {
                $cycles[] = ['submitted_at' => $submittedAt, 'decision_at' => $row->created_at, 'decision_status' => $row->to_status];
                $submittedAt = null;
            }
        }
        if ($submittedAt !== null) {
            $cycles[] = ['submitted_at' => $submittedAt, 'decision_at' => null, 'decision_status' => 'pending'];
        }

        // Floor of "how many reworks" is the app-wide reworkCountFor(); some legacy
        // rows only exist in status-history with no matching DesignTaskBdReview, so
        // the total is bumped to whichever count is actually higher — this way the
        // per-row "Rework X of Y" label can never show X greater than Y.
        $reworkCyclesDetected = count(array_filter($cycles, fn ($c) => $c['decision_status'] === 'rework'));
        $totalReworks = max($this->reworkCountFor($task, $reworkReviewCount, $reworkHistoryCount), $reworkCyclesDetected);
        $reworkCreativesByOrdinal = $reworkCyclesByTask->get($task->id, collect())->values();

        $rows = [];
        $reworkOrdinal = 0;
        foreach ($cycles as $index => $cycle) {
            $dueAt = $task->due_at;
            $onTimeText = $dueAt === null
                ? '—'
                : ($cycle['submitted_at']->lte($dueAt)
                    ? 'On Time'
                    : 'Late • '.$this->durationText($dueAt, $cycle['submitted_at']));

            // When BD sends this cycle to Rework, the Designer's rework window runs
            // from that decision until the NEXT cycle's submission (moved back to
            // BD review) — or is still open/pending if there's no next cycle yet.
            $rework = null;
            if ($cycle['decision_status'] === 'rework') {
                $reworkOrdinal++;
                $movedBackAt = $cycles[$index + 1]['submitted_at'] ?? null;
                $reworkEnd = $movedBackAt ?? now();
                $rework = [
                    'ordinal' => $reworkOrdinal,
                    'total' => $totalReworks,
                    'creatives' => (int) ($reworkCreativesByOrdinal->get($reworkOrdinal - 1)?->number_of_creatives ?? 0),
                    'started_at' => $cycle['decision_at'],
                    'moved_back_at' => $movedBackAt,
                    'duration_text' => $this->durationText($cycle['decision_at'], $reworkEnd),
                    'duration_minutes' => $cycle['decision_at']->diffInMinutes($reworkEnd),
                ];
            }

            $rows[] = [
                'task' => $task,
                'submitted_at' => $cycle['submitted_at'],
                'decision_at' => $cycle['decision_at'],
                'decision_status' => $cycle['decision_status'],
                'duration_text' => $this->durationText($cycle['submitted_at'], $cycle['decision_at'] ?? now()),
                'designer_on_time_text' => $onTimeText,
                'rework' => $rework,
            ];
        }

        return $rows;
    }

    /**
     * Groups one task's `design_task_eod_records` into a parent/child tree for
     * the Progress Updates display: one parent per BD rework cycle (from the
     * immutable status-history log via reviewCyclesFor(), NOT the per-record
     * `rework_count_snapshot` column, which has been observed to disagree with
     * the actual number of BD rework requests on some tasks), plus a single
     * "initial progress" bucket for every plain progress submission.
     */
    public function progressTimeline(DesignTask $task): array
    {
        $eodRecords = DesignTaskEodRecord::query()
            ->with('designer:id,name')
            ->where('design_task_id', $task->id)
            ->orderBy('submitted_at')
            ->get();

        $reworkReviews = DesignTaskBdReview::query()
            ->where('design_task_id', $task->id)
            ->where('action', 'rework')
            ->with('submitter:id,name')
            ->orderBy('created_at')
            ->get();

        $reworkReviewCount = collect([$task->id => $reworkReviews->count()]);
        $reworkHistoryCount = collect([
            $task->id => DesignTaskStatusHistory::query()
                ->where('design_task_id', $task->id)
                ->where('to_status', 'rework')
                ->where('change_source', 'bd_rework')
                ->count(),
        ]);

        $historyRows = DesignTaskStatusHistory::query()
            ->where('design_task_id', $task->id)
            ->whereIn('to_status', ['waiting_confirmation', 'rework', 'completed'])
            ->orderBy('created_at')
            ->get(['to_status', 'created_at']);

        $cycles = $this->reviewCyclesFor($task, $historyRows, collect([$task->id => $reworkReviews]), $reworkReviewCount, $reworkHistoryCount);

        $reworkParents = [];
        foreach ($cycles as $cycle) {
            if ($cycle['rework'] === null) {
                continue;
            }

            $ordinal = $cycle['rework']['ordinal'];
            $review = $reworkReviews->get($ordinal - 1);
            $reworkParents[$ordinal] = [
                'ordinal' => $ordinal,
                'startedAt' => $cycle['rework']['started_at'],
                'endedAt' => $cycle['rework']['moved_back_at'],
                'requestedCount' => $cycle['rework']['creatives'],
                'bdName' => $review?->submitter?->name,
                'comment' => $review?->comment,
                'attachmentUrl' => $review?->attachment_url,
                'attachmentName' => $review?->attachment_original_name,
                'durationText' => $this->humanDuration((int) $cycle['rework']['duration_minutes']),
                'durationMinutes' => (int) $cycle['rework']['duration_minutes'],
                'children' => collect(),
            ];
        }

        $lastOrdinal = empty($reworkParents) ? null : array_key_last($reworkParents);

        foreach ($eodRecords->where('update_type', 'rework') as $record) {
            $matched = null;

            foreach ($reworkParents as $ordinal => $parent) {
                $windowEnd = $parent['endedAt'] ?? now();
                if ($record->submitted_at->betweenIncluded($parent['startedAt'], $windowEnd)) {
                    $matched = $ordinal;
                    break;
                }
            }

            if ($matched === null) {
                $snapshot = (int) $record->rework_count_snapshot;
                $matched = array_key_exists($snapshot, $reworkParents) ? $snapshot : $lastOrdinal;
            }

            if ($matched !== null) {
                $reworkParents[$matched]['children']->push($record);
            }
        }

        foreach ($reworkParents as $ordinal => &$parent) {
            $parent['remainingCount'] = max(0, $parent['requestedCount'] - $parent['children']->sum('completed_count'));
            $parent['children'] = $this->withTimeTaken($parent['children']->sortBy('submitted_at')->values(), $parent['startedAt']);
        }
        unset($parent);

        $initial = $this->withTimeTaken(
            $eodRecords->where('update_type', 'progress')->sortBy('submitted_at')->values(),
            null
        );

        // ------------------------------------------------------------------
        // Tree structure used by the shared Progress Updates tree.
        // The Submission is the single ROOT; every rework cycle (and the final
        // BD approval, when present) is a DIRECT child/sibling branch of that
        // root — never nested under another rework. Each rework branch carries
        // only its own completion submissions as its children. Everything is
        // derived from the immutable status-history / BD-review / EOD-record
        // relations above; cycles are never inferred by order only.
        // ------------------------------------------------------------------
        $reworkParentsOrdered = collect($reworkParents)->sortBy('ordinal')->values();

        // -- Submission root -------------------------------------------------
        $firstCycle = $cycles[0] ?? null;
        $submissionStage = [
            'type' => 'submission',
            'title' => 'Submission',
            'children' => $initial,
            'submittedBy' => $initial->first()?->designer?->name ?? $task->designer?->name,
            'submittedAt' => $initial->first()?->submitted_at,
            'reviewStartedAt' => $firstCycle['submitted_at'] ?? null,
            'reviewDurationMinutes' => $firstCycle && $firstCycle['submitted_at']
                ? (int) $firstCycle['submitted_at']->diffInMinutes($firstCycle['decision_at'] ?? now())
                : null,
        ];

        // -- Sibling branches under the root (rework cycles, then final) ------
        $branches = collect();

        foreach ($reworkParentsOrdered as $parent) {
            $branches->push([
                'type' => 'rework',
                'ordinal' => $parent['ordinal'],
                'startedAt' => $parent['startedAt'],
                'bdName' => $parent['bdName'],
                'comment' => $parent['comment'],
                'attachmentUrl' => $parent['attachmentUrl'],
                'attachmentName' => $parent['attachmentName'],
                'requestedCount' => $parent['requestedCount'],
                'remainingCount' => $parent['remainingCount'],
                'durationText' => $parent['durationText'],
                'durationMinutes' => $parent['durationMinutes'],
                'children' => $parent['children'],
            ]);
        }

        // -- Final BD Approval (only once the task is completed) -------------
        $completedDecision = collect($cycles)->last(fn ($c) => $c['decision_status'] === 'completed');
        $finalReview = DesignTaskBdReview::query()
            ->with('submitter:id,name')
            ->where('design_task_id', $task->id)
            ->where('action', 'completed')
            ->latest('created_at')
            ->first();

        if ($task->status === 'completed' && $finalReview !== null) {
            $branches->push([
                'type' => 'final',
                'status' => 'Completed',
                'approvedBy' => $finalReview->submitter?->name ?? '—',
                'approvedAt' => $finalReview->created_at,
                'submittedAt' => $completedDecision['submitted_at'] ?? null,
                'decisionAt' => $completedDecision['decision_at'] ?? $finalReview->created_at,
                'reviewDurationMinutes' => $completedDecision && $completedDecision['submitted_at']
                    ? (int) $completedDecision['submitted_at']->diffInMinutes($completedDecision['decision_at'] ?? now())
                    : null,
                'totalReworkCount' => $reworkParentsOrdered->count(),
                'totalReworkCreatives' => $reworkParentsOrdered->sum('requestedCount'),
                'totalDesignerReworkTime' => $this->humanDuration($reworkParentsOrdered->sum('durationMinutes')),
            ]);
        }

        return [
            'initial' => $initial,
            'reworks' => collect($reworkParents)->sortByDesc('ordinal')->values(),
            'flow' => [
                'submission' => $submissionStage,
                'branches' => $branches,
            ],
        ];
    }

    /**
     * Human completion status for the Submission root card. Uses the original
     * assigned/due timestamps and the ACTUAL completion timestamp so rework
     * never shifts the original assignment date. Returns a display label:
     *   "Completed On Time"
     *   "Completed 10 days after due date"
     *   "Completed 10 days 2 hours after due date"
     *   "10 days 2 hours overdue"        (still incomplete & past due)
     */
    public function completionStatus(DesignTask $task, ?Carbon $completedAt): string
    {
        $due = $task->due_at;
        if ($completedAt && $due) {
            if ($completedAt->lte($due)) {
                return 'Completed On Time';
            }

            return 'Completed '.$this->humanDuration((int) $due->diffInMinutes($completedAt)).' after due date';
        }

        if ($completedAt) {
            return 'Completed On Time';
        }

        if ($due && $due->lt(now())) {
            return $this->humanDuration((int) $due->diffInMinutes(now())).' overdue';
        }

        return 'In Progress';
    }

    /**
     * Stamps each record (in chronological order) with a `time_taken_text`
     * attribute — elapsed time since the previous sibling, or since `$startedAt`
     * for the first child of a rework parent — then reverses to latest-first
     * for display, so the underlying elapsed-time math is never computed
     * against the already-reversed display order.
     */
    private function withTimeTaken(Collection $chronological, ?Carbon $startedAt): Collection
    {
        $previous = $startedAt;

        return $chronological->map(function (DesignTaskEodRecord $record) use (&$previous) {
            $record->setAttribute(
                'time_taken_text',
                $previous ? $this->humanDuration((int) $previous->diffInMinutes($record->submitted_at)) : null
            );
            $previous = $record->submitted_at;

            return $record;
        })->reverse()->values();
    }

    /**
     * Verbose duration wording for the Progress Updates tree ("2 hours",
     * "1 day 10 hours") — deliberately separate from durationText()/
     * minutesToText() above, which keep their existing abbreviated style
     * ("2d 4h") used by the Dashboard, so that display is unaffected.
     */
    public function humanDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'day' : 'days');
        }
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }
        if ($days === 0 && $mins > 0) {
            $parts[] = $mins.' min';
        }

        return $parts ? implode(' ', $parts) : '0 min';
    }
}
