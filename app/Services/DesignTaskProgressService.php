<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskStatusHistory;

class DesignTaskProgressService
{
    public function completed(DesignTask $task): int
    {
        $normalProgress = (int) DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'progress')
            ->sum('completed_count');

        $reworkCompleted = (int) DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'rework')
            ->sum('completed_count');

        $reworkSentBack = (int) DesignTaskBdReview::query()
            ->where('design_task_id', $task->id)
            ->where('action', 'rework')
            ->sum('number_of_creatives');

        $completed = $normalProgress + $reworkCompleted - $reworkSentBack;

        return max(0, min((int) $task->total_creatives, $completed));
    }

    /**
     * $completed accepts an already-computed completed() result so callers
     * that need several of these figures for the same task (every task-detail
     * page) can compute completed() once and reuse it, instead of each of
     * these methods re-running its 3 SUM queries from scratch. Optional and
     * defaults to recomputing, so existing single-value callers are unaffected.
     */
    public function percentage(DesignTask $task, ?int $completed = null): int
    {
        $total = max(1, (int) $task->total_creatives);
        $completed ??= $this->completed($task);

        return min(100, (int) round(($completed / $total) * 100));
    }

    public function remaining(DesignTask $task, ?int $completed = null): int
    {
        $completed ??= $this->completed($task);

        return max(0, (int) $task->total_creatives - $completed);
    }

    public function isComplete(DesignTask $task, ?int $completed = null): bool
    {
        $completed ??= $this->completed($task);

        return $completed >= (int) $task->total_creatives;
    }

    /**
     * Rework number changes only when BD starts a new rework cycle.
     */
    public function reworkCount(DesignTask $task): int
    {
        $reviewCount = DesignTaskBdReview::query()
            ->where('design_task_id', $task->id)
            ->where('action', 'rework')
            ->count();

        if ($reviewCount > 0) {
            return $reviewCount;
        }

        // Legacy fallback for tasks created before BD review records existed.
        return DesignTaskStatusHistory::query()
            ->where('design_task_id', $task->id)
            ->where('to_status', 'rework')
            ->where('change_source', 'bd_rework')
            ->count();
    }

    public function currentReworkRequested(DesignTask $task): int
    {
        return (int) (DesignTaskBdReview::query()
            ->where('design_task_id', $task->id)
            ->where('action', 'rework')
            ->latest('id')
            ->value('number_of_creatives') ?? 0);
    }

    public function currentReworkCompleted(DesignTask $task): int
    {
        $cycle = $this->reworkCount($task);

        if ($cycle < 1) {
            return 0;
        }

        $requested = $this->currentReworkRequested($task);
        $submitted = (int) DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'rework')
            ->where('rework_count_snapshot', $cycle)
            ->sum('completed_count');

        return $requested > 0 ? min($requested, $submitted) : $submitted;
    }

    public function currentReworkPending(DesignTask $task, ?int $completed = null): int
    {
        // Overall 100% is the final source of truth. This also self-heals
        // legacy Rework rows whose cycle metadata no longer matches progress.
        if ($this->isComplete($task, $completed)) {
            return 0;
        }

        return max(0, $this->currentReworkRequested($task) - $this->currentReworkCompleted($task));
    }

    /**
     * Kept for compatibility with existing status service naming.
     * A rework cycle is considered complete only after every creative sent by BD
     * in that cycle has been resubmitted by the Designer.
     */
    public function currentReworkHasUpload(DesignTask $task, ?int $completed = null): bool
    {
        return $this->currentReworkRequested($task) > 0
            && ($this->isComplete($task, $completed) || $this->currentReworkPending($task, $completed) === 0);
    }

    public function colorKey(int $percentage): string
    {
        return match (true) {
            $percentage >= 100 => 'complete',
            $percentage >= 75 => 'high',
            $percentage >= 50 => 'mid',
            $percentage >= 25 => 'low',
            default => 'start',
        };
    }
}
