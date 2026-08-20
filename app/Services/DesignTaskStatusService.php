<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignTaskStatusService
{
    public const STATUSES = [
        'assigned_tasks' => 'New Assignments',
        'review_analysis' => 'Requirement Review',
        'need_clarification' => 'Clarification Needed',
        'yet_to_start' => 'Ready to Start',
        'in_progress' => 'In Progress',
        'waiting_confirmation' => 'Waiting for BD Review',
        'rework' => 'Rework',
        'completed' => 'Completed',
        'swap_tasks' => 'Transferred Tasks',
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

    public function moveAsDesigner(
        DesignTask $task,
        User $designer,
        string $targetStatus,
        string $source = 'designer'
    ): DesignTask {
        if ($designer->role !== 'designer') {
            throw new AuthorizationException('You are not allowed to update this task.');
        }

        if (! array_key_exists($targetStatus, self::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'The selected task status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($task, $designer, $targetStatus, $source) {
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ((int) $lockedTask->designer_id !== (int) $designer->id) {
                throw new AuthorizationException('You are not allowed to update this task.');
            }

            $fromStatus = $lockedTask->status;

            $hasPendingDecline = DesignTaskRequest::query()
                ->where('design_task_id', $lockedTask->id)
                ->where('request_type', 'decline')
                ->whereIn('overall_status', ['pending_approval', 'pending_designer_head', 'pending_admin'])
                ->exists();

            if ($hasPendingDecline) {
                throw ValidationException::withMessages([
                    'status' => 'This task has a pending Decline request. Status cannot be changed until it is resolved.',
                ]);
            }

            if (! $this->designerCanMove($fromStatus, $targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'This status movement is not permitted for the Designer.',
                ]);
            }

            if ($targetStatus === 'waiting_confirmation') {
                $progressService = app(DesignTaskProgressService::class);

                if ($progressService->percentage($lockedTask) < 100) {
                    throw ValidationException::withMessages([
                        'status' => 'BD Review is available only after creative progress reaches 100%.',
                    ]);
                }

                if ($fromStatus === 'rework' && ! $progressService->currentReworkHasUpload($lockedTask)) {
                    throw ValidationException::withMessages([
                        'status' => 'Upload the corrected Rework ZIP before sending the task for BD Review.',
                    ]);
                }
            }

            $lockedTask->update(['status' => $targetStatus]);

            app(DesignTaskRequestService::class)->autoRejectPendingForStatus(
                $lockedTask,
                $targetStatus,
                $designer
            );

            DesignTaskStatusHistory::create([
                'design_task_id' => $lockedTask->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by' => $designer->id,
                'change_source' => $source,
            ]);

            return $lockedTask->fresh();
        });
    }

    public function designerCanMove(string $fromStatus, string $targetStatus): bool
    {
        if ($fromStatus === $targetStatus) {
            return false;
        }

        if ($fromStatus === 'swap_tasks' || $targetStatus === 'swap_tasks') {
            return false;
        }

        // After BD sends a task to Rework, the corrected ZIP is submitted in the
        // Rework stage and the Designer returns it directly for BD confirmation.
        if ($fromStatus === 'rework') {
            return $targetStatus === 'waiting_confirmation';
        }

        if (in_array($targetStatus, ['rework', 'completed'], true)) {
            return false;
        }

        if (in_array($fromStatus, ['waiting_confirmation', 'completed'], true)) {
            return false;
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
