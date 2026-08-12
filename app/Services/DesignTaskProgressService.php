<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskStatusHistory;

class DesignTaskProgressService
{
    public function completed(DesignTask $task): int
    {
        if (isset($task->completed_creatives)) {
            return min((int) $task->total_creatives, (int) $task->completed_creatives);
        }

        return min(
            (int) $task->total_creatives,
            (int) DesignTaskEodRecord::query()
                ->where('design_task_id', $task->id)
                ->where('update_type', 'progress')
                ->sum('completed_count')
        );
    }

    public function percentage(DesignTask $task): int
    {
        $total = max(1, (int) $task->total_creatives);
        return min(100, (int) round(($this->completed($task) / $total) * 100));
    }

    public function remaining(DesignTask $task): int
    {
        return max(0, (int) $task->total_creatives - $this->completed($task));
    }

    public function isComplete(DesignTask $task): bool
    {
        return $this->completed($task) >= (int) $task->total_creatives;
    }

    public function reworkCount(DesignTask $task): int
    {
        return DesignTaskStatusHistory::query()
            ->where('design_task_id', $task->id)
            ->where('to_status', 'rework')
            ->count();
    }

    public function currentReworkHasUpload(DesignTask $task): bool
    {
        $count = $this->reworkCount($task);
        if ($count < 1) return false;

        return DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->where('update_type', 'rework')
            ->where('rework_count_snapshot', $count)
            ->whereNotNull('attachment_path')
            ->exists();
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
