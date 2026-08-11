<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskEditHistory;
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
use Illuminate\Support\Facades\Schema;
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

    public ?int $eodCompletedCount = null;
    public bool $swapInitiatorReadOnly = false;

    private function isSwapShadowTask(?DesignTask $task = null): bool
    {
        $task ??= $this->task;
        $requirements = $task->requirements ?? [];

        if ((bool) data_get($requirements, '_swap_shadow', false)) {
            return true;
        }

        $userId = (int) Auth::id();

        if (
            (int) ($requirements['_swap_original_designer_id'] ?? 0) === $userId
            || (int) ($requirements['_swap_previous_designer_id'] ?? 0) === $userId
        ) {
            return true;
        }

        $swapRequestId = (int) ($requirements['_swap_request_id'] ?? 0);

        if ($swapRequestId > 0) {
            return DesignTaskRequest::query()
                ->whereKey($swapRequestId)
                ->where('request_type', 'swap')
                ->where('requested_by', $userId)
                ->where('overall_status', 'approved')
                ->exists();
        }

        return DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->where('request_type', 'swap')
            ->where('requested_by', $userId)
            ->where('overall_status', 'approved')
            ->exists();
    }

    private function canInteractFully(?DesignTask $task = null): bool
    {
        $task ??= $this->task;

        return (int) $task->designer_id === (int) Auth::id()
            && ! $this->isSwapShadowTask($task);
    }

    public function mount(DesignTask $task): void
    {
        $user = Auth::user();
        abort_unless($user?->role === 'designer', 403);

        $isCurrentDesigner = (int) $task->designer_id === (int) $user->id;

        $requirements = $task->requirements ?? [];
        $swapRequestId = (int) ($requirements['_swap_request_id'] ?? 0);
        $swapOriginalDesignerId = (int) ($requirements['_swap_original_designer_id'] ?? 0);
        $swapPreviousDesignerId = (int) ($requirements['_swap_previous_designer_id'] ?? 0);

        $isApprovedSwapInitiator = false;

        if ($swapOriginalDesignerId === (int) $user->id || $swapPreviousDesignerId === (int) $user->id) {
            $isApprovedSwapInitiator = true;
        }

        if (! $isApprovedSwapInitiator && $swapRequestId > 0) {
            $isApprovedSwapInitiator = DesignTaskRequest::query()
                ->whereKey($swapRequestId)
                ->where('request_type', 'swap')
                ->where('requested_by', $user->id)
                ->where('overall_status', 'approved')
                ->exists();
        }

        if (! $isApprovedSwapInitiator) {
            $isApprovedSwapInitiator = DesignTaskRequest::query()
                ->where('design_task_id', $task->id)
                ->where('request_type', 'swap')
                ->where('requested_by', $user->id)
                ->where('overall_status', 'approved')
                ->exists();
        }

        abort_unless($isCurrentDesigner || $isApprovedSwapInitiator, 403);

        $this->swapInitiatorReadOnly = $isApprovedSwapInitiator
            && (
                ! $isCurrentDesigner
                || (bool) data_get($requirements, '_swap_shadow', false)
            );

        $this->task = $task;
    }

    public function moveToNextStatus(): void
    {
        abort_unless($this->canInteractFully(), 403);

        abort_if($this->swapInitiatorReadOnly, 403, 'This swapped task is read-only for the original Designer.');

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

    public function submitEod(): void
    {
        abort_unless(
            Auth::user()?->role === 'designer'
            && $this->canInteractFully(),
            403
        );

        $this->validate([
            'eodCompletedCount' => ['required', 'integer', 'min:1'],
        ], [
            'eodCompletedCount.required' => 'Enter the number of creatives completed.',
            'eodCompletedCount.min' => 'Completed creatives must be at least 1.',
        ]);

        $task = DesignTask::query()->lockForUpdate()->findOrFail($this->task->id);

        $alreadyCompleted = (int) DesignTaskEodRecord::query()
            ->where('design_task_id', $task->id)
            ->sum('completed_count');

        $remainingBefore = max(0, (int) $task->total_creatives - $alreadyCompleted);
        $completedNow = (int) $this->eodCompletedCount;

        if ($remainingBefore <= 0) {
            $this->addError('eodCompletedCount', 'All creatives for this task are already completed.');
            return;
        }

        if ($completedNow > $remainingBefore) {
            $this->addError(
                'eodCompletedCount',
                'You can complete only '.$remainingBefore.' remaining creative'.($remainingBefore === 1 ? '' : 's').'.'
            );
            return;
        }

        $cumulativeCompleted = $alreadyCompleted + $completedNow;
        $remainingAfter = max(0, (int) $task->total_creatives - $cumulativeCompleted);

        $record = DesignTaskEodRecord::create([
            'design_task_id' => $task->id,
            'designer_id' => Auth::id(),
            'completed_count' => $completedNow,
            'total_creatives_snapshot' => (int) $task->total_creatives,
            'cumulative_completed' => $cumulativeCompleted,
            'remaining_creatives' => $remainingAfter,
            'submitted_at' => now(),
        ]);

        DesignTaskStatusHistory::create([
            'design_task_id' => $task->id,
            'from_status' => $task->status,
            'to_status' => $task->status,
            'changed_by' => Auth::id(),
            'change_source' => 'eod_submitted',
            'note' => 'EOD updated: '.$completedNow.' completed, '.$remainingAfter.' remaining.',
        ]);

        $this->task = $task->fresh();
        $this->eodCompletedCount = null;

        $this->dispatch(
            'eod-updated',
            message: 'EOD updated successfully.'
        );
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

        $eodRecords = DesignTaskEodRecord::query()
            ->with('designer:id,name,role')
            ->where('design_task_id', $this->task->id)
            ->latest('submitted_at')
            ->get();

        $eodCompletedTotal = (int) $eodRecords->sum('completed_count');
        $eodRemaining = max(0, (int) $this->task->total_creatives - $eodCompletedTotal);

        $editHistory = collect();

        if (Schema::hasTable('design_task_edit_histories')) {
            $editHistory = DesignTaskEditHistory::query()
                ->with('editor:id,name,role')
                ->where('design_task_id', $this->task->id)
                ->latest('created_at')
                ->get()
                ->groupBy('edit_batch_id');
        }

        return view('livewire.designer.task-detail', [
            'statuses' => DesignTaskStatusService::STATUSES,
            'nextStatus' => $this->swapInitiatorReadOnly
                ? null
                : app(DesignTaskStatusService::class)->nextDesignerStatus($this->task->status),
            'comments' => $comments,
            'history' => $history = DesignTaskStatusHistory::query()
                ->with('changedBy:id,name,role')
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
            'pipelineEvents' => $this->buildPipelineEvents($history, $comments),
            'eodRecords' => $eodRecords,
            'eodCompletedTotal' => $eodCompletedTotal,
            'eodRemaining' => $eodRemaining,
            'isCommentOnlySwap' => $this->isSwapShadowTask(),
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
            'allowedRequestTypes' => $this->swapInitiatorReadOnly
                ? []
                : app(DesignTaskRequestService::class)->allowedTypesForTask($this->task),
            'swapInitiatorReadOnly' => $this->swapInitiatorReadOnly,
            'splitRequests' => $splitRequests,
            'swapRequests' => $swapRequests,
            'splitChildren' => $splitChildren,
            'splitOriginTask' => $splitOriginTask,
            'requirementAttachmentGroups' => $requirementAttachmentGroups,
            'requirementAttachmentCount' => $requirementAttachmentCount,
            'commentAttachmentCount' => $commentAttachmentCount,
            'attachmentCount' => $requirementAttachmentCount + $commentAttachmentCount,
            'editHistory' => $editHistory,
        ]);
    }


    private function shortPipelineTitle(string $title, ?string $source = null): string
    {
        $title = trim($title);
        $source = trim((string) $source);
        $lower = strtolower($title);

        if (str_contains($lower, 'swap request')) {
            if (str_contains($lower, 'reject') || str_contains($lower, 'declin')) {
                return 'Swap Request Declined';
            }
            if (str_contains($lower, 'approv')) {
                return 'Swap Request Approved';
            }
            return 'Swap Request Created';
        }

        if (str_contains($lower, 'split request')) {
            if (str_contains($lower, 'reject') || str_contains($lower, 'declin')) {
                return 'Split Request Declined';
            }
            if (str_contains($lower, 'approv')) {
                return 'Split Request Approved';
            }
            return 'Split Request Created';
        }

        if (str_contains($lower, 'decline request')) {
            if (str_contains($lower, 'reject') || str_contains($lower, 'declin')) {
                return 'Decline Request Declined';
            }
            if (str_contains($lower, 'approv')) {
                return 'Decline Request Approved';
            }
            return 'Decline Request Created';
        }

        if ($source === 'task_created' || str_starts_with($lower, 'task created')) {
            return 'Task Created';
        }

        if ($source === 'task_assigned' || str_starts_with($lower, 'task assigned')) {
            return 'Task Assigned';
        }

        if ($source === 'comment_added' || str_starts_with($lower, 'comment added')) {
            return 'Comment Added';
        }

        if (preg_match('/^(.+?)\s*(?:→|->)\s*(.+)$/u', $title, $matches)) {
            return 'Moved to '.trim($matches[2]);
        }

        $words = preg_split('/\s+/', $title) ?: [];

        return count($words) <= 9
            ? $title
            : implode(' ', array_slice($words, 0, 9)).'…';
    }

    private function buildPipelineEvents($history, $comments)
    {
        $events = collect();

        foreach ($history as $event) {
            $role = $event->changedBy?->role ?? 'default';

            $events->push([
                'type' => 'history',
                'title' => $this->shortPipelineTitle(
                    $event->note ?: trim(
                        ($event->from_status ? (DesignTaskStatusService::STATUSES[$event->from_status] ?? $event->from_status).' → ' : '')
                        .(DesignTaskStatusService::STATUSES[$event->to_status] ?? $event->to_status)
                    ),
                    $event->change_source
                ),
                'description' => 'By '.($event->changedBy?->name ?? 'System'),
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
                'title' => 'Task Assigned',
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
