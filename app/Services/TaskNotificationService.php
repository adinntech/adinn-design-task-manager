<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Models\UserActivityFlag;
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
    /**
     * Every notification category except 'comment'. These are the categories
     * that represent a change to a task list/board — a comment never moves a
     * task between columns, so it never flags a refresh and is never cleared
     * by a task-list Refresh click. Category strings are also written into
     * each Notification::toArray()'s 'category' key, so the bell/refresh-flag
     * and the actual notification rows always agree on the same taxonomy.
     */
    public const LIST_CATEGORIES = ['assignment', 'status', 'rework', 'rating', 'split', 'swap', 'decline', 'status_change', 'bd_approval'];

    public function taskAssigned(DesignTask $task, User $assignedBy, User $designer): void
    {
        $this->send($designer, new TaskAssignedNotification($task, $assignedBy));
        $this->flag($designer, 'assignment');
    }

    /**
     * BD is notified only for movements on their own task (existing behaviour).
     * Designer Head is notified for every movement on every task (no "team"
     * concept in this schema — Head already sees all tasks unscoped). Admin is
     * notified only when a task reaches Waiting for BD Review, matching the
     * brief's role-specific §5 copy.
     */
    public function statusChanged(DesignTask $task, string $toStatus, User $changedBy): void
    {
        $bd = $task->assigner ?? User::find($task->assigned_by);

        if ($bd && (int) $bd->id !== (int) $changedBy->id) {
            $this->send($bd, new TaskStatusChangedNotification($task, $toStatus, $changedBy));
            $this->flag($bd, 'status');
        }

        $this->notifyRoleOfStatusChange('designer_head', $task, $toStatus, $changedBy);

        if ($toStatus === 'waiting_confirmation') {
            $this->notifyRoleOfStatusChange('admin', $task, $toStatus, $changedBy);
        }
    }

    private function notifyRoleOfStatusChange(string $role, DesignTask $task, string $toStatus, User $changedBy): void
    {
        $recipients = User::query()->where('role', $role)->where('is_active', true)->get();

        foreach ($recipients as $recipient) {
            if ((int) $recipient->id === (int) $changedBy->id) {
                continue;
            }

            $this->send($recipient, new TaskStatusChangedNotification($task, $toStatus, $changedBy));
            $this->flag($recipient, 'status');
        }
    }

    /**
     * A Designer created a task and picked this BD to confirm it — reuses the
     * same generic TaskRequestNotification already used for split/swap/decline/
     * status_change requests (see its 'bd_approval' copy branch) instead of a
     * dedicated notification class.
     */
    public function bdApprovalRequested(DesignTask $task, User $designer): void
    {
        $bd = $task->assigner ?? User::find($task->assigned_by);

        if (! $bd) {
            return;
        }

        $this->send($bd, new TaskRequestNotification($task, 'bd_approval', 'submitted', $designer));
        $this->flag($bd, 'bd_approval');
    }

    /**
     * The BD approved or rejected a Designer-created task's confirmation request.
     */
    public function bdApprovalDecided(DesignTask $task, string $event, User $bd, ?string $comment = null): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);

        if (! $designer) {
            return;
        }

        $this->send($designer, new TaskRequestNotification($task, 'bd_approval', $event, $bd, $comment));
        $this->flag($designer, 'bd_approval');
    }

    public function reworkRequested(DesignTask $task, int $reworkCount, int $reworkCreatives, string $comment, User $bd): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);
        $notification = new ReworkRequestedNotification($task, $reworkCount, $reworkCreatives, $comment, $bd);

        if ($designer) {
            $this->send($designer, $notification);
            $this->flag($designer, 'rework');
        }

        $this->notifyHeads($notification, $bd, 'rework');
    }

    public function taskRated(DesignTask $task, DesignTaskBdReview $review): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);
        $notification = new TaskRatedNotification($task, $review);

        if ($designer) {
            $this->send($designer, $notification);
            $this->flag($designer, 'rating');
        }

        $this->notifyHeads($notification, $review->submitter ?? $designer, 'rating');
    }

    /**
     * Notify the "other party" on a task comment — never the author. A BD or
     * Designer comment notifies the counterpart on the task; a Designer Head
     * comment notifies both the assigned Designer and the BD. Designer Head
     * and Admin are always included as independent, visibility-only
     * recipients (own unread state — see TaskCommentNotification-based
     * comment counters). No refresh-flag: a comment never moves a task
     * between list/board columns.
     */
    public function commentAdded(DesignTask $task, User $author, string $comment, bool $isClarification = false): void
    {
        $designer = $task->designer ?? User::find($task->designer_id);
        $bd = $task->assigner ?? User::find($task->assigned_by);
        $heads = User::query()->where('role', 'designer_head')->where('is_active', true)->get();
        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();

        $recipients = collect([$designer, $bd])
            ->concat($heads)
            ->concat($admins)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => (int) $user->id === (int) $author->id);

        foreach ($recipients as $recipient) {
            $this->send($recipient, new TaskCommentNotification($task, $author, $comment, $isClarification));
        }
    }

    /**
     * Routed to the requester's assigned Designer Head only (not every Head)
     * — falls back to every active Head only when the requester has no Head
     * assigned yet (legacy/unassigned Designer), so a request never goes
     * unnoticed. Admin recipients (split/swap) are unchanged.
     */
    public function requestSubmitted(DesignTaskRequest $taskRequest): void
    {
        $task = $taskRequest->task ?? DesignTask::find($taskRequest->design_task_id);

        if (! $task) {
            return;
        }

        $requester = $taskRequest->requester;
        $headOnly = in_array($taskRequest->request_type, ['decline', 'status_change'], true);

        $heads = User::query()
            ->where('role', 'designer_head')
            ->where('is_active', true)
            ->when($requester?->designer_head_id, fn ($query) => $query->whereKey($requester->designer_head_id))
            ->get();

        $approvers = $headOnly
            ? $heads
            : $heads->concat(User::query()->where('role', 'admin')->where('is_active', true)->get());

        foreach ($approvers as $approver) {
            $this->send($approver, new TaskRequestNotification($task, $taskRequest->request_type, 'submitted', $requester));
            $this->flag($approver, $taskRequest->request_type);
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
            $this->flag($requester, $taskRequest->request_type);
        }

        if ($event === 'approved' && $taskRequest->approvedDesigner) {
            $newDesigner = $taskRequest->approvedDesigner;
            $newTaskId = data_get($taskRequest->split_details, 'created_task_id')
                ?? data_get($taskRequest->split_details, 'active_task_id');
            $newTask = $newTaskId ? DesignTask::find($newTaskId) : $task->fresh();

            if ($newTask && (int) $newDesigner->id !== (int) $requester?->id) {
                $this->send($newDesigner, new TaskAssignedNotification($newTask, $actor));
                $this->flag($newDesigner, 'assignment');
            }
        }
    }

    /**
     * No BD task-detail edit-save action exists anywhere in the codebase yet
     * (DesignTaskEditHistory currently has zero creators), so there is nothing
     * successful to hook a notification onto. Left as a documented no-op ready
     * to wire in once that endpoint ships — see the plan's scope notes.
     */
    public function taskEdited(DesignTask $task, User $editor): void
    {
        // Intentionally unimplemented.
    }

    /**
     * Notify every active Designer Head (no "team" scoping in this schema —
     * Head already sees every task unscoped). Rework/rating both move the
     * task to a different status, which moves it to a different column on
     * Head's Kanban, so these also flag Head's refresh state — not just
     * visibility in the bell.
     */
    private function notifyHeads(Notification $notification, ?User $actor, string $category): void
    {
        $heads = User::query()->where('role', 'designer_head')->where('is_active', true)->get();

        foreach ($heads as $head) {
            if ($actor && (int) $head->id === (int) $actor->id) {
                continue;
            }

            $this->send($head, $notification);
            $this->flag($head, $category);
        }
    }

    private function flag(User $user, string $category): void
    {
        try {
            UserActivityFlag::query()->updateOrCreate(
                ['user_id' => $user->id, 'scope' => $category],
                ['flagged_at' => now()]
            );
        } catch (Throwable $e) {
            Log::warning('Activity flag write failed', [
                'user_id' => $user->id,
                'category' => $category,
                'error' => $e->getMessage(),
            ]);
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
