<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskDetail extends Component
{
    use WithFileUploads;

    public DesignTask $task;
    public string $comment = '';
    public array $attachments = [];

    protected $listeners = ['request-created' => '$refresh'];

    public function mount(DesignTask $task): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && (int) $task->designer_id === (int) Auth::id(),
            403
        );

        $this->task = $task;
    }

    public function moveToNextStatus(): void
    {
        $nextStatus = app(DesignTaskStatusService::class)
            ->nextDesignerStatus($this->task->status);

        if ($nextStatus === null) {
            $this->addError('status', 'No further Designer status movement is available.');
            return;
        }

        $this->task = app(DesignTaskStatusService::class)
            ->moveAsDesigner($this->task, Auth::user(), $nextStatus, 'detail_button');

        $this->dispatch('task-status-changed', message: 'Task moved successfully.');
    }

    public function addComment(): void
    {
        $this->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ]);

        DB::transaction(function () {
            $newComment = DesignTaskComment::create([
                'design_task_id' => $this->task->id,
                'user_id' => Auth::id(),
                'status_at_comment' => $this->task->status,
                'comment' => trim($this->comment),
            ]);

            foreach ($this->attachments as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $originalBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $extension = strtolower($file->getClientOriginalExtension());
                $safeBase = $originalBase !== '' ? $originalBase : 'attachment';

                $fileName = sprintf(
                    '%s__comment-%05d__%s__%s__%02d.%s',
                    $this->task->task_id,
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
                    $this->task->vertical,
                    $this->task->task_id.'_'.Str::slug($this->task->task_name),
                    Str::slug($this->task->task_nature),
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

        $this->reset(['comment', 'attachments']);
        $this->dispatch('comment-added', message: 'Comment added successfully.');
    }

    public function render()
    {
        $this->task->refresh()->load(['designer:id,name,role', 'assigner:id,name,role']);

        $comments = DesignTaskComment::query()
            ->with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $this->task->id)
            ->latest()
            ->get();

        $requirementAttachmentGroups = $this->collectRequirementAttachments(
            $this->task->requirements ?? []
        );

        $requirementAttachmentCount = collect($requirementAttachmentGroups)
            ->sum(fn (array $group) => count($group['files']));

        $commentAttachmentCount = $comments->sum(
            fn (DesignTaskComment $comment) => $comment->attachments->count()
        );

        $requestRelations = [
            'requester:id,name,role',
            'targetDesigner:id,name',
            'approvedDesigner:id,name',
            'designerHeadActor:id,name,role',
            'adminActor:id,name,role',
            'task:id,task_id,task_name,designer_id,total_creatives',
        ];

        $splitRequests = DesignTaskRequest::query()
            ->with($requestRelations)
            ->where('request_type', 'split')
            ->where('overall_status', 'approved')
            ->where(function ($query) {
                $query->where('design_task_id', $this->task->id);

                $originatingRequestId = data_get($this->task->requirements, '_split_request_id');
                if ($originatingRequestId) {
                    $query->orWhere('id', $originatingRequestId);
                }
            })
            ->latest()
            ->get();

        $swapRequests = DesignTaskRequest::query()
            ->with($requestRelations)
            ->where('design_task_id', $this->task->id)
            ->where('request_type', 'swap')
            ->where('overall_status', 'approved')
            ->latest()
            ->get();

        $splitChildIds = $splitRequests
            ->pluck('split_details.created_task_id')
            ->filter()
            ->values();

        $splitChildren = DesignTask::query()
            ->with('designer:id,name')
            ->whereIn('id', $splitChildIds)
            ->get(['id', 'task_id', 'task_name', 'designer_id', 'total_creatives', 'status', 'assigned_at'])
            ->keyBy('id');

        $originTaskCode = data_get($this->task->requirements, '_split_from_task_id');
        $splitOriginTask = $originTaskCode
            ? DesignTask::query()->with('designer:id,name')->where('task_id', $originTaskCode)->first()
            : null;

        return view('livewire.designer.task-detail', [
            'statuses' => DesignTaskStatusService::STATUSES,
            'nextStatus' => app(DesignTaskStatusService::class)
                ->nextDesignerStatus($this->task->status),
            'comments' => $comments,
            'history' => $history = DesignTaskStatusHistory::query()
                ->with('changedBy:id,name,role')
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
            'pipelineEvents' => $this->buildPipelineEvents($history, $comments),
            'requests' => $requests = DesignTaskRequest::query()
                ->with([
                    'requester:id,name',
                    'targetDesigner:id,name',
                    'approvedDesigner:id,name',
                    'designerHeadActor:id,name',
                    'adminActor:id,name',
                ])
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
            'pendingRequestTypes' => $requests
                ->whereIn('overall_status', ['pending_approval', 'pending_designer_head', 'pending_admin'])
                ->pluck('request_type')
                ->unique()
                ->values()
                ->all(),
            'allowedRequestTypes' => app(DesignTaskRequestService::class)
                ->allowedTypes($this->task->status),
            'splitRequests' => $splitRequests,
            'swapRequests' => $swapRequests,
            'splitChildren' => $splitChildren,
            'splitOriginTask' => $splitOriginTask,
            'requirementAttachmentGroups' => $requirementAttachmentGroups,
            'requirementAttachmentCount' => $requirementAttachmentCount,
            'commentAttachmentCount' => $commentAttachmentCount,
            'attachmentCount' => $requirementAttachmentCount + $commentAttachmentCount,
        ]);
    }

    private function buildPipelineEvents($history, $comments)
    {
        $events = collect();

        foreach ($history as $event) {
            $role = $event->changedBy?->role ?? 'default';

            $events->push([
                'type' => 'history',
                'title' => $event->note ?: trim(
                    ($event->from_status ? (DesignTaskStatusService::STATUSES[$event->from_status] ?? $event->from_status).' → ' : '')
                    .(DesignTaskStatusService::STATUSES[$event->to_status] ?? $event->to_status)
                ),
                'description' => 'By '.($event->changedBy?->name ?? 'System').' · '.ucwords(str_replace('_', ' ', $event->change_source)),
                'role' => $role,
                'created_at' => $event->created_at,
            ]);
        }

        // Comments are part of the operational timeline. Including them here also
        // makes comments created before this enhancement visible in Pipeline History.
        foreach ($comments as $comment) {
            $role = $comment->user?->role ?? 'default';
            $statusLabel = DesignTaskStatusService::STATUSES[$comment->status_at_comment] ?? $comment->status_at_comment;
            $preview = Str::limit(trim((string) $comment->comment), 140);

            $events->push([
                'type' => 'comment',
                'title' => 'Comment Added',
                'description' => 'By '.($comment->user?->name ?? 'User').' · '.$statusLabel.($preview !== '' ? ' · “'.$preview.'”' : ''),
                'role' => $role,
                'created_at' => $comment->created_at,
            ]);
        }

        $hasCreatedEvent = $history->contains(fn ($event) => $event->change_source === 'task_created');
        $hasAssignedEvent = $history->contains(fn ($event) => $event->change_source === 'task_assigned');
        $assignerRole = $this->task->assigner?->role ?? 'bd';

        if (! $hasCreatedEvent) {
            $events->push([
                'type' => 'system',
                'title' => 'Task Created',
                'description' => 'Created by '.($this->task->assigner?->name ?? 'BD'),
                'role' => $assignerRole,
                'created_at' => $this->task->created_at,
            ]);
        }

        if (! $hasAssignedEvent && $this->task->designer_id) {
            $events->push([
                'type' => 'system',
                'title' => 'Task Assigned to '.($this->task->designer?->name ?? 'Designer'),
                'description' => 'Assigned by '.($this->task->assigner?->name ?? 'BD'),
                'role' => $assignerRole,
                'created_at' => $this->task->assigned_at ?: $this->task->created_at,
            ]);
        }

        return $events
            ->filter(fn (array $event) => $event['created_at'] !== null)
            ->sortByDesc(fn (array $event) => $event['created_at']->getTimestamp())
            ->values();
    }

    private function collectRequirementAttachments(array $requirements): array
    {
        $groups = [];

        foreach ($requirements as $key => $value) {
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

        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        $files[] = [
            'path' => $value,
            'name' => basename($value),
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'url' => Storage::disk('spaces')->url($value),
        ];
    }

    private function looksLikeStoredFilePath(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        return pathinfo($value, PATHINFO_EXTENSION) !== '';
    }
}
