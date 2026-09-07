<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Notifications\ReworkRequestedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCommentNotification;
use App\Notifications\TaskRatedNotification;
use App\Notifications\TaskRequestNotification;
use App\Notifications\TaskStatusChangedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single place that decides WHO gets notified for a task event and dispatches
 * the notification. Called from existing controllers/services right after
 * their business transaction has already committed — never from inside a
 * transaction, and never allowed to throw back into the caller, so a
 * notification failure can never break or roll back the real workflow action.
 */
class TaskNotificationService
{
    public function taskAssigned(DesignTask $task, User $assignedBy, User $designer): void
    {
        $this->send($designer, new TaskAssignedNotification($task, $assignedBy));
    }

    public function statusChanged(DesignTask $task, string $toStatus, User $changedBy): void
    {
        $bd = $task->assigner ?? User::find($task->assigned_by);

        if ($bd && (int) $bd->id !== (int) $changedBy->id) {
            $this->send($bd, new TaskStatusChangedNotification($task, $toStatus, $changedBy));
        }
    }

    public function reworkRequested(DesignTask $task, int $reworkCount, int $reworkCreatives, string $comment, User $bd): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);

        if ($designer) {
            $this->send($designer, new ReworkRequestedNotification($task, $reworkCount, $reworkCreatives, $comment, $bd));
        }
    }

    public function taskRated(DesignTask $task, DesignTaskBdReview $review): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);

        if ($designer) {
            $this->send($designer, new TaskRatedNotification($task, $review));
        }
    }

    /**
     * Notify the "other party" on a task comment — never the author. A BD or
     * Designer comment notifies the counterpart on the task; a Designer Head
     * comment notifies both the assigned Designer and the BD.
     */
    public function commentAdded(DesignTask $task, User $author, string $comment, bool $isClarification = false): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);
        $bd = $task->assigner ?? User::find($task->assigned_by);

        $recipients = collect([$designer, $bd])
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => (int) $user->id === (int) $author->id);

        foreach ($recipients as $recipient) {
            $this->send($recipient, new TaskCommentNotification($task, $author, $comment, $isClarification));
        }
    }

    public function requestSubmitted(DesignTaskRequest $taskRequest): void
    {
        $task = $taskRequest->task ?? DesignTask::find($taskRequest->design_task_id);

        if (! $task) {
            return;
        }

        $requester = $taskRequest->requester;
        $approverRoles = $taskRequest->request_type === 'decline' ? ['designer_head'] : ['designer_head', 'admin'];

        $approvers = User::query()->whereIn('role', $approverRoles)->where('is_active', true)->get();

        foreach ($approvers as $approver) {
            $this->send($approver, new TaskRequestNotification($task, $taskRequest->request_type, 'submitted', $requester));
        }
    }

    public function requestDecided(DesignTaskRequest $taskRequest, string $event, User $actor): void
    {
        $task = $taskRequest->task ?? DesignTask::find($taskRequest->design_task_id);

        if (! $task) {
            return;
        }

        $requester = $taskRequest->requester;

        if ($requester && (int) $requester->id !== (int) $actor->id) {
            $this->send($requester, new TaskRequestNotification($task, $taskRequest->request_type, $event, $actor));
        }

        if ($event === 'approved' && $taskRequest->approvedDesigner) {
            $newDesigner = $taskRequest->approvedDesigner;
            $newTaskId = data_get($taskRequest->split_details, 'created_task_id')
                ?? data_get($taskRequest->split_details, 'active_task_id');
            $newTask = $newTaskId ? DesignTask::find($newTaskId) : $task->fresh();

            if ($newTask && (int) $newDesigner->id !== (int) $requester?->id) {
                $this->send($newDesigner, new TaskAssignedNotification($newTask, $actor));
            }
        }
    }

    private function send(User $recipient, Notification $notification): void
    {
        try {
            $recipient->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Notification dispatch failed', [
                'recipient_id' => $recipient->id,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
