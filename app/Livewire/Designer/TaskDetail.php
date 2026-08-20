<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskPipelineService;
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
    public string $clarificationMessage = '';
    public array $clarificationAttachments = [];

    protected $listeners = ['request-created' => '$refresh'];

    public ?int $eodCompletedCount = null;
    public $taskUpdateAttachment = null;
    public $reworkAttachment = null;
    public ?int $reworkCompletedCount = null;
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


    private function canViewTaskUpdation(?DesignTask $task = null): bool
    {
        $task ??= $this->task;

        return in_array($task->status, [
            'in_progress',
            'waiting_confirmation',
            'rework',
            'completed',
        ], true);
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

        $this->reconcileCompletedRework();
    }

    private function reconcileCompletedRework(): void
    {
        if ($this->swapInitiatorReadOnly || $this->task->status !== 'rework') {
            return;
        }

        $progressService = app(DesignTaskProgressService::class);

        // 100% overall progress is the final completion signal.
        // Do not keep legacy Rework tasks stuck only because older rows do not
        // have a DesignTaskBdReview record for the current cycle.
        if (! $progressService->isComplete($this->task)) {
            return;
        }

        DB::transaction(function (): void {
            $task = DesignTask::query()->lockForUpdate()->findOrFail($this->task->id);
            $progressService = app(DesignTaskProgressService::class);

            if ($task->status !== 'rework' || ! $progressService->isComplete($task)) {
                return;
            }

            $task->update(['status' => 'waiting_confirmation']);

            app(DesignTaskRequestService::class)->autoRejectPendingForStatus(
                $task,
                'waiting_confirmation',
                Auth::user()
            );

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => 'rework',
                'to_status' => 'waiting_confirmation',
                'changed_by' => Auth::id(),
                'change_source' => 'rework_reconciled',
                'note' => 'Rework progress reached 100% and was automatically moved to Waiting for BD Review.',
            ]);
        });

        $this->task = $this->task->fresh();
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

        $this->persistComment($this->comment, $this->attachments);

        $this->reset(['comment', 'attachments']);
        $this->dispatch('comment-added', message: 'Comment added successfully.');
    }

    public function addClarification(): void
    {
        if ($this->task->status !== 'need_clarification') {
            $this->addError('clarificationMessage', 'Clarification can only be sent while the task is in Clarification Needed status.');
            return;
        }

        $this->validate([
            'clarificationMessage' => ['required', 'string', 'max:10000'],
            'clarificationAttachments' => ['array', 'max:10'],
            'clarificationAttachments.*' => ['file', 'max:102400'],
        ]);

        $this->persistComment($this->clarificationMessage, $this->clarificationAttachments);

        $this->reset(['clarificationMessage', 'clarificationAttachments']);
        $this->dispatch('comment-added', message: 'Clarification sent successfully.');
    }

    private function persistComment(string $message, array $files): void
    {
        DB::transaction(function () use ($message, $files) {
            $newComment = DesignTaskComment::create([
                'design_task_id' => $this->task->id,
                'user_id' => Auth::id(),
                'status_at_comment' => $this->task->status,
                'comment' => trim($message),
            ]);

            foreach ($files as $index => $file) {
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
    }

    public function submitEod(): void
    {
        abort_unless(Auth::user()?->role === 'designer' && $this->canInteractFully(), 403);

        if ($this->task->status !== 'in_progress') {
            $this->addError('eodCompletedCount', 'Task Updation is enabled only while the task is In Progress.');
            return;
        }

        $this->validate([
            'eodCompletedCount' => ['required', 'integer', 'min:1'],
            'taskUpdateAttachment' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ], [
            'eodCompletedCount.required' => 'Enter the number of creatives added to progress.',
            'taskUpdateAttachment.required' => 'Upload the Task Updation ZIP file.',
            'taskUpdateAttachment.mimes' => 'Task Updation accepts ZIP files only.',
        ]);

        $file = $this->taskUpdateAttachment;

        DB::transaction(function () use ($file): void {
            $task = DesignTask::query()->lockForUpdate()->findOrFail($this->task->id);

            if ((int) $task->designer_id !== (int) Auth::id() || $task->status !== 'in_progress') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'eodCompletedCount' => 'Task Updation is no longer available for this task.',
                ]);
            }

            $progressService = app(DesignTaskProgressService::class);
            $alreadyCompleted = $progressService->completed($task);
            $remainingBefore = max(0, (int) $task->total_creatives - $alreadyCompleted);
            $progressAdded = (int) $this->eodCompletedCount;

            if ($remainingBefore <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'eodCompletedCount' => 'Creative progress is already 100%.',
                ]);
            }

            if ($progressAdded > $remainingBefore) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'eodCompletedCount' => 'You can add only '.$remainingBefore.' remaining creative'.($remainingBefore === 1 ? '' : 's').'.',
                ]);
            }

            $cumulativeCompleted = $alreadyCompleted + $progressAdded;
            $remainingAfter = max(0, (int) $task->total_creatives - $cumulativeCompleted);
            $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
            $directory = implode('/', [$root, now()->format('Y'), $task->vertical, $task->task_id.'_'.Str::slug($task->task_name), Str::slug($task->task_nature), 'task-updation']);
            $fileName = $task->task_id.'__task-updation__'.now()->format('Ymd-His-v').'.zip';
            $path = $file->storePubliclyAs($directory, $fileName, 'spaces');

            DesignTaskEodRecord::create([
                'design_task_id' => $task->id,
                'designer_id' => Auth::id(),
                'update_type' => 'progress',
                'completed_count' => $progressAdded,
                'total_creatives_snapshot' => (int) $task->total_creatives,
                'cumulative_completed' => $cumulativeCompleted,
                'remaining_creatives' => $remainingAfter,
                'rework_count_snapshot' => null,
                'attachment_disk' => 'spaces',
                'attachment_path' => $path,
                'attachment_original_name' => $file->getClientOriginalName(),
                'submitted_at' => now(),
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => $task->status,
                'to_status' => $task->status,
                'changed_by' => Auth::id(),
                'change_source' => 'task_updation',
                'note' => 'Task Updation submitted: '.$progressAdded.' progress added, '.$remainingAfter.' remaining.',
            ]);
        });

        $this->task = $this->task->fresh();
        $this->reset(['eodCompletedCount', 'taskUpdateAttachment']);
        $this->dispatch('eod-updated', message: 'Task Updation submitted successfully.');
    }

    public function submitReworkUpdate(): void
    {
        abort_unless(Auth::user()?->role === 'designer' && $this->canInteractFully(), 403);

        if ($this->task->status !== 'rework') {
            $this->addError('reworkCompletedCount', 'Rework submission is available only while the task is in Rework.');
            return;
        }

        $progressService = app(DesignTaskProgressService::class);
        $pendingBefore = $progressService->currentReworkPending($this->task);

        $this->validate([
            'reworkCompletedCount' => ['required', 'integer', 'min:1', 'max:'.$pendingBefore],
            'reworkAttachment' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ], [
            'reworkCompletedCount.required' => 'Enter the number of reworked creatives being submitted.',
            'reworkCompletedCount.max' => 'You cannot submit more creatives than the current Rework pending count.',
            'reworkAttachment.required' => 'Upload the Rework creative ZIP.',
            'reworkAttachment.mimes' => 'Rework creative upload accepts ZIP files only.',
        ]);

        $file = $this->reworkAttachment;
        $submittedCount = (int) $this->reworkCompletedCount;
        $nextStage = 'rework';
        $pendingAfter = $pendingBefore;

        DB::transaction(function () use ($file, $submittedCount, &$nextStage, &$pendingAfter): void {
            $task = DesignTask::query()->lockForUpdate()->findOrFail($this->task->id);

            if ((int) $task->designer_id !== (int) Auth::id() || $task->status !== 'rework') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reworkCompletedCount' => 'Rework submission is no longer available for this task.',
                ]);
            }

            $progressService = app(DesignTaskProgressService::class);
            $reworkCount = $progressService->reworkCount($task);
            $pending = $progressService->currentReworkPending($task);

            if ($reworkCount < 1 || $pending < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reworkCompletedCount' => 'No pending creatives remain in the current Rework cycle.',
                ]);
            }

            if ($submittedCount > $pending) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reworkCompletedCount' => 'You cannot submit more creatives than the current Rework pending count.',
                ]);
            }

            $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
            $directory = implode('/', [
                $root,
                now()->format('Y'),
                $task->vertical,
                $task->task_id.'_'.Str::slug($task->task_name),
                Str::slug($task->task_nature),
                'rework',
                'rework-'.$reworkCount,
            ]);

            $fileName = $task->task_id.'__rework-'.$reworkCount.'__'.now()->format('Ymd-His-v').'.zip';
            $path = $file->storePubliclyAs($directory, $fileName, 'spaces');

            // completed() includes this record, so calculate snapshots after create.
            DesignTaskEodRecord::create([
                'design_task_id' => $task->id,
                'designer_id' => Auth::id(),
                'update_type' => 'rework',
                'completed_count' => $submittedCount,
                'total_creatives_snapshot' => (int) $task->total_creatives,
                'cumulative_completed' => 0,
                'remaining_creatives' => 0,
                'rework_count_snapshot' => $reworkCount,
                'attachment_disk' => 'spaces',
                'attachment_path' => $path,
                'attachment_original_name' => $file->getClientOriginalName(),
                'submitted_at' => now(),
            ]);

            $completedNow = $progressService->completed($task);
            $remainingNow = $progressService->remaining($task);
            $pendingAfter = $progressService->currentReworkPending($task);

            DesignTaskEodRecord::query()
                ->where('design_task_id', $task->id)
                ->where('update_type', 'rework')
                ->where('rework_count_snapshot', $reworkCount)
                ->latest('id')
                ->limit(1)
                ->update([
                    'cumulative_completed' => $completedNow,
                    'remaining_creatives' => $remainingNow,
                ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => 'rework',
                'to_status' => 'rework',
                'changed_by' => Auth::id(),
                'change_source' => 'rework_upload',
                'note' => 'Rework #'.$reworkCount.': '.$submittedCount.' creative(s) resubmitted. '.$pendingAfter.' rework creative(s) pending.',
            ]);

            $isFullyComplete = $progressService->isComplete($task);

            if ($isFullyComplete || $pendingAfter === 0) {
                $nextStage = $isFullyComplete
                    ? 'waiting_confirmation'
                    : 'in_progress';

                // At 100%, overall progress is authoritative. This prevents a task
                // being trapped in Rework because of stale cycle metadata.
                if ($isFullyComplete) {
                    $pendingAfter = 0;
                }

                $task->update(['status' => $nextStage]);

                app(DesignTaskRequestService::class)->autoRejectPendingForStatus(
                    $task,
                    $nextStage,
                    Auth::user()
                );

                DesignTaskStatusHistory::create([
                    'design_task_id' => $task->id,
                    'from_status' => 'rework',
                    'to_status' => $nextStage,
                    'changed_by' => Auth::id(),
                    'change_source' => 'rework_completed',
                    'note' => $nextStage === 'waiting_confirmation'
                        ? 'Rework #'.$reworkCount.' reached 100% and returned to Waiting for Confirmation.'
                        : 'Rework #'.$reworkCount.' completed and returned to In Progress because overall progress is below 100%.',
                ]);
            }
        });

        $this->task = $this->task->fresh();
        $this->reset(['reworkCompletedCount', 'reworkAttachment']);

        $message = $pendingAfter > 0
            ? 'Rework submitted. '.$pendingAfter.' creative(s) remain in this Rework cycle.'
            : ($nextStage === 'waiting_confirmation'
                ? 'Rework completed. Task sent to Waiting for Confirmation.'
                : 'Rework completed. Task returned to In Progress.');

        $this->dispatch('eod-updated', message: $message);
        $this->dispatch('task-status-changed', message: $message);
    }

    public function render()
    {
        $this->task->refresh()->load(['designer:id,name,role', 'assigner:id,name,role']);

        $comments = DesignTaskComment::query()
            ->with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $this->task->id)
            ->latest()
            ->get();

        $clarificationComments = $comments
            ->where('status_at_comment', 'need_clarification')
            ->sortBy('created_at')
            ->values();

        $generalComments = $comments
            ->reject(fn ($c) => $c->status_at_comment === 'need_clarification')
            ->values();

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

        $progressService = app(DesignTaskProgressService::class);
        $eodCompletedTotal = $progressService->completed($this->task);
        $eodRemaining = $progressService->remaining($this->task);
        $progressPercentage = $progressService->percentage($this->task);
        $progressColorKey = $progressService->colorKey($progressPercentage);
        $reworkCount = $progressService->reworkCount($this->task);
        $currentReworkHasUpload = $progressService->currentReworkHasUpload($this->task);

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
            'generalComments' => $generalComments,
            'clarificationComments' => $clarificationComments,
            'history' => $history = DesignTaskStatusHistory::query()
                ->with('changedBy:id,name,role')
                ->where('design_task_id', $this->task->id)
                ->latest()
                ->get(),
            'pipelineEvents' => app(DesignTaskPipelineService::class)->build($this->task, $history, $comments, $editHistory),
            'eodRecords' => $eodRecords,
            'eodCompletedTotal' => $eodCompletedTotal,
            'eodRemaining' => $eodRemaining,
            'progressPercentage' => $progressPercentage,
            'progressColorKey' => $progressColorKey,
            'reworkCount' => $reworkCount,
            'currentReworkHasUpload' => $currentReworkHasUpload,
            'currentReworkRequested' => $progressService->currentReworkRequested($this->task),
            'currentReworkCompleted' => $progressService->currentReworkCompleted($this->task),
            'currentReworkPending' => $progressService->currentReworkPending($this->task),
            'latestReworkReview' => DesignTaskBdReview::query()
                ->where('design_task_id', $this->task->id)
                ->where('action', 'rework')
                ->latest()
                ->first(),
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
            'showTaskUpdation' => $this->canViewTaskUpdation(),
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
