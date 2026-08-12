<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssignedTaskController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->role === 'bd', 403);

        return view('bd.tasks.index');
    }

    public function show(Request $request, DesignTask $task): View
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id,
            403
        );

        $task->load(['designer:id,name,email,role', 'assigner:id,name,email,role']);

        $comments = DesignTaskComment::query()
            ->with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $history = DesignTaskStatusHistory::query()
            ->with('changedBy:id,name,role')
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $requestRelations = [
            'requester:id,name,role',
            'targetDesigner:id,name',
            'approvedDesigner:id,name',
            'designerHeadActor:id,name,role',
            'adminActor:id,name,role',
        ];

        $splitRequests = DesignTaskRequest::query()
            ->with($requestRelations)
            ->where('request_type', 'split')
            ->where(function ($query) use ($task) {
                $query->where('design_task_id', $task->id);

                $originatingRequestId = data_get($task->requirements, '_split_request_id');
                if ($originatingRequestId) {
                    $query->orWhere('id', $originatingRequestId);
                }
            })
            ->latest()
            ->get();

        $swapRequests = DesignTaskRequest::query()
            ->with($requestRelations)
            ->where('request_type', 'swap')
            ->where(function ($query) use ($task) {
                $query->where('design_task_id', $task->id);

                $swapRequestId = data_get($task->requirements, '_swap_request_id');
                if ($swapRequestId) {
                    $query->orWhere('id', $swapRequestId);
                }
            })
            ->latest()
            ->get();

        $eodRecords = DesignTaskEodRecord::query()
            ->with('designer:id,name,role')
            ->where('design_task_id', $task->id)
            ->latest('submitted_at')
            ->get();

        $progressService = app(DesignTaskProgressService::class);
        $eodCompletedTotal = $progressService->completed($task);
        $eodRemaining = $progressService->remaining($task);
        $progressPercentage = $progressService->percentage($task);
        $progressColorKey = $progressService->colorKey($progressPercentage);
        $reworkCount = $progressService->reworkCount($task);

        $editHistory = collect();
        if (Schema::hasTable('design_task_edit_histories')) {
            $editHistory = DesignTaskEditHistory::query()
                ->with('editor:id,name,role')
                ->where('design_task_id', $task->id)
                ->latest('created_at')
                ->get()
                ->groupBy('edit_batch_id');
        }

        $requirementAttachmentGroups = $this->collectRequirementAttachments($task->requirements ?? []);
        $requirementAttachmentCount = collect($requirementAttachmentGroups)
            ->sum(fn (array $group) => count($group['files']));
        $commentAttachmentCount = $comments->sum(fn ($comment) => $comment->attachments->count());

        $pipelineEvents = collect();
        foreach ($history as $event) {
            $pipelineEvents->push([
                'title' => $event->note ?: $this->statusTitle($event->from_status, $event->to_status),
                'description' => 'By '.($event->changedBy?->name ?? 'System'),
                'role' => $event->changedBy?->role ?? 'default',
                'created_at' => $event->created_at,
            ]);
        }
        foreach ($comments as $comment) {
            $pipelineEvents->push([
                'title' => 'Comment Added',
                'description' => 'By '.($comment->user?->name ?? 'User').' · '.Str::limit(trim((string) $comment->comment), 120),
                'role' => $comment->user?->role ?? 'default',
                'created_at' => $comment->created_at,
            ]);
        }
        $pipelineEvents = $pipelineEvents->sortByDesc(fn ($event) => $event['created_at']?->getTimestamp() ?? 0)->values();

        return view('bd.tasks.show', [
            'task' => $task,
            'statuses' => DesignTaskStatusService::STATUSES,
            'comments' => $comments,
            'pipelineEvents' => $pipelineEvents,
            'splitRequests' => $splitRequests,
            'swapRequests' => $swapRequests,
            'eodRecords' => $eodRecords,
            'eodCompletedTotal' => $eodCompletedTotal,
            'eodRemaining' => $eodRemaining,
            'progressPercentage' => $progressPercentage,
            'progressColorKey' => $progressColorKey,
            'reworkCount' => $reworkCount,
            'editHistory' => $editHistory,
            'requirementAttachmentGroups' => $requirementAttachmentGroups,
            'attachmentCount' => $requirementAttachmentCount + $commentAttachmentCount,
        ]);
    }

    public function addComment(Request $request, DesignTask $task): RedirectResponse
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id,
            403
        );

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ]);

        DB::transaction(function () use ($request, $task, $data) {
            $newComment = DesignTaskComment::create([
                'design_task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status_at_comment' => $task->status,
                'comment' => trim($data['comment']),
            ]);

            foreach ($request->file('attachments', []) as $index => $file) {
                $originalBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $extension = strtolower($file->getClientOriginalExtension());
                $safeBase = $originalBase !== '' ? $originalBase : 'attachment';

                $fileName = sprintf(
                    '%s__comment-%05d__%s__%s__%02d.%s',
                    $task->task_id,
                    $newComment->id,
                    $safeBase,
                    now()->format('Ymd-His-v'),
                    $index + 1,
                    $extension
                );

                $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');

                $directory = implode('/', [
                    $root,
                    now()->format('Y'),
                    $task->vertical,
                    $task->task_id.'_'.Str::slug($task->task_name),
                    Str::slug($task->task_nature),
                    'comments',
                    'comment-'.$newComment->id,
                ]);

                $path = $file->storePubliclyAs($directory, $fileName, 'spaces');

                DesignTaskCommentAttachment::create([
                    'design_task_comment_id' => $newComment->id,
                    'disk' => 'spaces',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        });

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => 'comments'])
            ->with('success', 'Comment added successfully.');
    }

    private function statusTitle(?string $from, ?string $to): string
    {
        $statuses = DesignTaskStatusService::STATUSES;
        return $from
            ? 'Moved to '.($statuses[$to] ?? Str::headline((string) $to))
            : ($statuses[$to] ?? Str::headline((string) $to));
    }

    private function collectRequirementAttachments(array $requirements): array
    {
        $groups = [];
        foreach ($requirements as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            $files = [];
            $this->extractStoredFiles($value, $files);
            if ($files === []) {
                continue;
            }
            $groups[] = [
                'key' => (string) $key,
                'label' => Str::headline((string) $key),
                'files' => $files,
            ];
        }
        return $groups;
    }

    private function extractStoredFiles(mixed $value, array &$files): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->extractStoredFiles($item, $files);
            }
            return;
        }

        if (! is_string($value) || ! $this->looksLikeStoredFilePath($value)) {
            return;
        }

        $files[] = [
            'path' => $value,
            'name' => basename($value),
            'extension' => strtoupper(pathinfo($value, PATHINFO_EXTENSION) ?: 'FILE'),
            'url' => Storage::disk('spaces')->url($value),
        ];
    }

    private function looksLikeStoredFilePath(string $value): bool
    {
        $value = trim($value);
        return $value !== ''
            && ! filter_var($value, FILTER_VALIDATE_URL)
            && str_contains($value, '/')
            && pathinfo($value, PATHINFO_EXTENSION) !== '';
    }
}
