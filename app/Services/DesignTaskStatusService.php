<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignTaskStatusService
{
    public const STATUSES = [
        'assigned_tasks' => 'Assigned Tasks',
        'review_analysis' => 'Review and Analysis',
        'need_clarification' => 'Need Clarification',
        'yet_to_start' => 'Yet to Start',
        'in_progress' => 'In Progress',
        'waiting_confirmation' => 'Waiting for Confirmation',
        'rework' => 'Rework',
        'completed' => 'Completed',
        'swap_tasks' => 'Swap Tasks',
    ];

    private const ORDER = [
        'assigned_tasks' => 1,
        'review_analysis' => 2,
        'need_clarification' => 3,
        'yet_to_start' => 4,
        'in_progress' => 5,
        'waiting_confirmation' => 6,
        'rework' => 7,
        'completed' => 8,
    ];

    public function moveAsDesigner(DesignTask $task, User $designer, string $targetStatus, string $source = 'designer'): DesignTask
    {
        if ($designer->role !== 'designer' || (int) $task->designer_id !== (int) $designer->id) {
            throw new AuthorizationException('You are not allowed to update this task.');
        }

        if (! array_key_exists($targetStatus, self::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'The selected task status is invalid.']);
        }

        $fromStatus = $task->status;

        if (! $this->designerCanMove($fromStatus, $targetStatus)) {
            throw ValidationException::withMessages(['status' => 'This status movement is not permitted for the Designer.']);
        }

        if ($targetStatus === 'waiting_confirmation') {
            if (! in_array($fromStatus, ['in_progress', 'rework'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Waiting for Confirmation is available only from In Progress or Rework.',
                ]);
            }

            $progress = app(DesignTaskProgressService::class);
            if (! $progress->isComplete($task)) {
                throw ValidationException::withMessages([
                    'status' => 'Complete 100% of the creatives before moving to Waiting for Confirmation.',
                ]);
            }

            if ($fromStatus === 'rework' && ! $progress->currentReworkHasUpload($task)) {
                throw ValidationException::withMessages([
                    'status' => 'Upload the current Rework ZIP in Task Updation before moving to Waiting for Confirmation.',
                ]);
            }
        }

        return DB::transaction(function () use ($task, $designer, $targetStatus, $fromStatus, $source) {
            $task->update(['status' => $targetStatus]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by' => $designer->id,
                'change_source' => $source,
            ]);

            return $task->fresh();
        });
    }

    public function designerCanMove(string $fromStatus, string $targetStatus): bool
    {
        if ($fromStatus === $targetStatus) return false;
        if ($fromStatus === 'swap_tasks' || $targetStatus === 'swap_tasks') return false;
        if (in_array($targetStatus, ['rework', 'completed'], true)) return false;
        if (in_array($fromStatus, ['waiting_confirmation', 'completed'], true)) return false;

        if ($fromStatus === 'rework') {
            return $targetStatus === 'waiting_confirmation';
        }

        if ($targetStatus === 'waiting_confirmation') {
            return $fromStatus === 'in_progress';
        }

        return (self::ORDER[$targetStatus] ?? 0) > (self::ORDER[$fromStatus] ?? 0);
    }

    public function nextDesignerStatus(string $currentStatus): ?string
    {
        return match ($currentStatus) {
            'assigned_tasks' => 'review_analysis',
            'review_analysis' => 'need_clarification',
            'need_clarification' => 'yet_to_start',
            'yet_to_start' => 'in_progress',
            'in_progress' => 'waiting_confirmation',
            'rework' => 'waiting_confirmation',
            default => null,
        };
    }
}
