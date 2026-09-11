<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place a Designer-created task's pending_bd_approval confirmation is
 * decided — shared by App\Livewire\Bd\TaskKanban (Kanban card buttons) and
 * App\Livewire\Bd\TaskConfirmationActions (Ticket Details page) so the two
 * surfaces never drift. Notification dispatch stays with the caller (same
 * convention as DesignTaskRequestService), not this service.
 */
class DesignTaskBdApprovalService
{
    public function approve(DesignTask $task, User $bd, ?string $comment): DesignTask
    {
        return DB::transaction(function () use ($task, $bd, $comment): DesignTask {
            $locked = DesignTask::query()
                ->lockForUpdate()
                ->whereKey($task->id)
                ->where('assigned_by', $bd->id)
                ->where('status', 'pending_bd_approval')
                ->firstOrFail();

            $locked->update([
                'status' => 'assigned_tasks',
                'bd_approval_status' => 'approved',
                'bd_approval_comment' => $comment,
                'bd_decided_by' => $bd->id,
                'bd_decided_at' => now(),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => 'pending_bd_approval',
                'to_status' => 'assigned_tasks',
                'changed_by' => $bd->id,
                'change_source' => 'bd_approval',
                'note' => $comment ?: 'Task confirmed by BD.',
            ]);

            return $locked->fresh();
        });
    }

    public function reject(DesignTask $task, User $bd, string $comment): DesignTask
    {
        return DB::transaction(function () use ($task, $bd, $comment): DesignTask {
            $locked = DesignTask::query()
                ->lockForUpdate()
                ->whereKey($task->id)
                ->where('assigned_by', $bd->id)
                ->where('status', 'pending_bd_approval')
                ->firstOrFail();

            $locked->update([
                'status' => 'bd_rejected',
                'bd_approval_status' => 'rejected',
                'bd_approval_comment' => $comment,
                'bd_decided_by' => $bd->id,
                'bd_decided_at' => now(),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $locked->id,
                'from_status' => 'pending_bd_approval',
                'to_status' => 'bd_rejected',
                'changed_by' => $bd->id,
                'change_source' => 'bd_approval',
                'note' => $comment,
            ]);

            return $locked->fresh();
        });
    }
}
