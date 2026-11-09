<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Shared JSON shapes for the mobile API (tasks, comments, requests). */
trait SerializesTasks
{
    /**
     * Files for the Overview tab, same data the web detail pages show:
     *  - requirement_files: the reference files the BD attached when creating the task (every vertical / nature has its
     *    own upload fields), grouped by field. Stored in the `requirements` JSON as paths on the spaces disk.
     *  - submissions: what the designer handed in, one entry per progress submission (primary + extra files).
     *
     * @return array{requirement_files: list<array<string, mixed>>, submissions: list<array<string, mixed>>}
     */
    protected function taskFiles(DesignTask $task): array
    {
        $groups = [];
        foreach ((array) $task->requirements as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            $found = [];
            $this->collectStoredFiles($value, $found);
            if ($found !== []) {
                $groups[] = ['key' => (string) $key, 'label' => Str::headline((string) $key), 'files' => $found];
            }
        }

        $submissions = DesignTaskEodRecord::where('design_task_id', $task->id)->latest('submitted_at')->get()
            ->map(fn (DesignTaskEodRecord $r) => [
                'id' => $r->id,
                'update_type' => $r->update_type,
                'completed_count' => (int) $r->completed_count,
                'cumulative_completed' => (int) $r->cumulative_completed,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
                'files' => collect($r->attachment_links)->map(fn (array $l) => $this->fileEntry($l['name'], $l['url']))->values()->all(),
            ])
            ->filter(fn (array $s) => $s['files'] !== [])
            ->values()->all();

        return ['requirement_files' => $groups, 'submissions' => $submissions];
    }

    /** @param  list<array<string, mixed>>  $files */
    private function collectStoredFiles(mixed $value, array &$files): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectStoredFiles($item, $files);
            }

            return;
        }

        $path = is_string($value) ? trim($value) : '';
        if ($path === '' || filter_var($path, FILTER_VALIDATE_URL) || ! str_contains($path, '/') || pathinfo($path, PATHINFO_EXTENSION) === '') {
            return;
        }

        $files[] = $this->fileEntry(basename($path), Storage::disk('spaces')->url($path));
    }

    /** @return array{name: string, extension: string, kind: string, url: string} kind = image|audio|video|file */
    private function fileEntry(string $name, string $url): array
    {
        $ext = strtolower(pathinfo(parse_url($name, PHP_URL_PATH) ?: $name, PATHINFO_EXTENSION));
        $kind = match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'], true) => 'image',
            in_array($ext, ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'opus', 'amr'], true) => 'audio',
            in_array($ext, ['mp4', 'mov', 'webm', 'mkv'], true) => 'video',
            default => 'file',
        };

        return ['name' => $name, 'extension' => strtoupper($ext ?: 'FILE'), 'kind' => $kind, 'url' => $url];
    }

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

    /**
     * The task's "Edit History" (what a BD changed on it, as on the web): one entry per save, newest first, each with
     * the field-by-field old and new values.
     *
     * @return list<array<string, mixed>>
     */
    protected function editHistory(DesignTask $task): array
    {
        return DesignTaskEditHistory::with('editor:id,name')
            ->where('design_task_id', $task->id)
            ->orderBy('id')
            ->get()
            ->groupBy(fn (DesignTaskEditHistory $h) => $h->edit_batch_id ?: 'single-'.$h->id)
            ->map(fn ($changes) => [
                'editor' => $changes->first()->editor?->name,
                'created_at' => $changes->first()->created_at?->toIso8601String(),
                'changes' => $changes->map(fn (DesignTaskEditHistory $c) => [
                    'field' => $c->field_name,
                    'old' => $c->old_value,
                    'new' => $c->new_value,
                ])->values()->all(),
            ])
            ->sortByDesc('created_at')
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
