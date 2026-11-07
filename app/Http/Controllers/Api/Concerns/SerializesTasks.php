<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;

/** Shared JSON shapes for the mobile API (tasks, comments, requests). */
trait SerializesTasks
{
    /**
     * The History tab: status changes and chat messages on one time-ordered list. Chat uses the same filter as the
     * chat tab itself, so clarification-thread replies stay out. `kind` tells the app which one a row is.
     *
     * @return list<array<string, mixed>>
     */
    protected function timeline(DesignTask $task): array
    {
        $status = DesignTaskStatusHistory::where('design_task_id', $task->id)->orderBy('id')
            ->get(['id', 'from_status', 'to_status', 'change_source', 'note', 'created_at'])
            ->map(fn ($h) => [
                'kind' => 'status',
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'change_source' => $h->change_source,
                'note' => $h->note,
                'created_at' => $h->created_at?->toIso8601String(),
                '_sort' => [$h->created_at?->getTimestamp() ?? 0, 0, $h->id],
            ]);

        $chat = DesignTaskComment::with(['user:id,name,role'])->withCount('attachments')
            ->where('design_task_id', $task->id)
            ->where(fn ($q) => $q->whereNull('context')->orWhere('context', '!=', 'clarification'))
            ->orderBy('id')
            ->get()
            ->map(fn (DesignTaskComment $c) => [
                'kind' => 'comment',
                'note' => $c->comment,
                'by' => $c->user?->name,
                'role' => $c->user?->role,
                'attachments' => (int) $c->attachments_count,
                'created_at' => $c->created_at?->toIso8601String(),
                '_sort' => [$c->created_at?->getTimestamp() ?? 0, 1, $c->id],
            ]);

        return $status->concat($chat)
            ->sortBy('_sort')
            ->map(function (array $row) {
                unset($row['_sort']);

                return $row;
            })
            ->values()
            ->all();
    }

    protected function summary(DesignTask $task): array
    {
        return [
            'id' => $task->id,
            'task_id' => $task->task_id,
            'task_name' => $task->task_name,
            'display_task_name' => $task->display_task_name,
            'vertical' => $task->vertical,
            'task_nature' => $task->task_nature,
            'party_name' => $task->party_name,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_at' => $task->due_at?->toIso8601String(),
            'total_creatives' => (int) $task->total_creatives,
            'bd_approval_status' => $task->bd_approval_status,
            'task_category' => $task->task_category,
            'designer' => $task->relationLoaded('designer') && $task->designer
                ? ['id' => $task->designer->id, 'name' => $task->designer->name]
                : null,
        ];
    }

    protected function commentPayload(DesignTaskComment $c): array
    {
        return [
            'id' => $c->id,
            'comment' => $c->comment,
            'status_at_comment' => $c->status_at_comment,
            'created_at' => $c->created_at?->toIso8601String(),
            'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'role' => $c->user->role] : null,
            'attachments' => $c->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size' => $a->size,
                'url' => $a->url,
            ])->values(),
        ];
    }

    protected function requestPayload(DesignTaskRequest $r): array
    {
        return [
            'id' => $r->id,
            'request_type' => $r->request_type,
            'overall_status' => $r->overall_status,
            'reason' => $r->reason,
            'decision_reason' => $r->decision_reason,
            'from_status' => $r->from_status,
            'to_status' => $r->to_status,
            'split_details' => $r->split_details,
            'created_at' => $r->created_at?->toIso8601String(),
            'task' => $r->relationLoaded('task') && $r->task ? $this->summary($r->task) : null,
            'requester' => $r->relationLoaded('requester') && $r->requester ? ['id' => $r->requester->id, 'name' => $r->requester->name] : null,
            'target_designer' => $r->relationLoaded('targetDesigner') && $r->targetDesigner ? ['id' => $r->targetDesigner->id, 'name' => $r->targetDesigner->name] : null,
        ];
    }
}
