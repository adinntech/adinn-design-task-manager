<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskEditHistory;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskPipelineService;
use App\Services\DesignTaskStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

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

        $clarificationComments = $comments
            ->where('status_at_comment', 'need_clarification')
            ->sortBy('created_at')
            ->values();

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

        $pipelineEvents = app(DesignTaskPipelineService::class)->build(
            $task,
            $history,
            $comments,
            $editHistory
        );

        $bdReviews = DesignTaskBdReview::query()
            ->with('submitter:id,name,role')
            ->where('design_task_id', $task->id)
            ->latest()
            ->get();

        $taskRating = $bdReviews
            ->first(fn (DesignTaskBdReview $review) => $review->action === 'completed');

        return view('bd.tasks.show', [
            'task' => $task,
            'statuses' => DesignTaskStatusService::STATUSES,
            'comments' => $comments,
            'clarificationComments' => $clarificationComments,
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
            'requirementAttachmentCount' => $requirementAttachmentCount,
            'commentAttachmentCount' => $commentAttachmentCount,
            'attachmentCount' => $requirementAttachmentCount + $commentAttachmentCount,
            'bdReviews' => $bdReviews,
            'taskRating' => $taskRating,
        ]);
    }

    public function submitRework(Request $request, DesignTask $task): RedirectResponse
    {
        $this->authorizeBdTask($request, $task);

        $data = $request->validate([
            'number_of_creatives' => ['required', 'integer', 'min:1', 'max:'.$task->total_creatives],
            'comment' => ['required', 'string', 'max:10000'],
        ], [
            'number_of_creatives.required' => 'Enter the number of creatives requiring rework.',
            'number_of_creatives.max' => 'Rework creatives cannot exceed the total number of creatives.',
            'comment.required' => 'Enter the rework comments.',
        ]);

        DB::transaction(function () use ($request, $task, $data) {
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages(['status' => 'Rework can be requested only while the task is Waiting for Confirmation.']);
            }

            if ((int) $data['number_of_creatives'] > (int) $lockedTask->total_creatives) {
                throw ValidationException::withMessages([
                    'number_of_creatives' => 'Rework creatives cannot exceed the current total number of creatives.',
                ]);
            }

            DesignTaskBdReview::create([
                'design_task_id' => $lockedTask->id,
                'submitted_by' => $request->user()->id,
                'action' => 'rework',
                'number_of_creatives' => (int) $data['number_of_creatives'],
                'comment' => trim($data['comment']),
            ]);

            DesignTaskComment::create([
                'design_task_id' => $lockedTask->id,
                'user_id' => $request->user()->id,
                'status_at_comment' => $lockedTask->status,
                'comment' => 'Rework requested for '.(int) $data['number_of_creatives'].' creative(s).'."\n".trim($data['comment']),
            ]);

            $fromStatus = $lockedTask->status;
            $lockedTask->update(['status' => 'rework']);

            DesignTaskStatusHistory::create([
                'design_task_id' => $lockedTask->id,
                'from_status' => $fromStatus,
                'to_status' => 'rework',
                'changed_by' => $request->user()->id,
                'change_source' => 'bd_rework',
                'note' => 'BD requested rework for '.(int) $data['number_of_creatives'].' creative(s).',
            ]);
        });

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => 'eod'])
            ->with('success', 'Task moved to Rework.');
    }

    public function completeWithRating(Request $request, DesignTask $task): RedirectResponse
    {
        $this->authorizeBdTask($request, $task);

        $halfStarRule = function (string $attribute, mixed $value, \Closure $fail): void {
            $number = (float) $value;
            if ($number < 0.5 || $number > 5 || abs(($number * 2) - round($number * 2)) > 0.0001) {
                $fail('Each rating must be between 0.5 and 5 stars in 0.5-star increments.');
            }
        };

        $data = $request->validate([
            'designer_attitude' => ['required', 'numeric', $halfStarRule],
            'design_satisfaction' => ['required', 'numeric', $halfStarRule],
            'rework_iteration' => ['required', 'numeric', $halfStarRule],
            'meeting_deadline' => ['required', 'numeric', $halfStarRule],
            'client_satisfaction' => ['required', 'numeric', $halfStarRule],
            'rating_comment' => ['nullable', 'string', 'max:10000'],
        ]);

        $overall = round((
            (float) $data['designer_attitude']
            + (float) $data['design_satisfaction']
            + (float) $data['rework_iteration']
            + (float) $data['meeting_deadline']
            + (float) $data['client_satisfaction']
        ) / 5, 2);

        if ($overall < 3 && trim((string) ($data['rating_comment'] ?? '')) === '') {
            return back()
                ->withErrors(['rating_comment' => 'Comments are mandatory when the overall rating is below 3 stars.'])
                ->withInput();
        }

        DB::transaction(function () use ($request, $task, $data, $overall) {
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages(['status' => 'A task can be completed only from Waiting for Confirmation.']);
            }

            $progress = app(DesignTaskProgressService::class)->percentage($lockedTask);
            if ($progress < 100) {
                throw ValidationException::withMessages(['status' => 'The task cannot be completed until creative progress reaches 100%.']);
            }

            if (DesignTaskBdReview::query()->where('design_task_id', $lockedTask->id)->where('action', 'completed')->exists()) {
                throw ValidationException::withMessages(['status' => 'A completion rating has already been submitted for this task.']);
            }

            DesignTaskBdReview::create([
                'design_task_id' => $lockedTask->id,
                'submitted_by' => $request->user()->id,
                'action' => 'completed',
                'number_of_creatives' => (int) $lockedTask->total_creatives,
                'comment' => trim((string) ($data['rating_comment'] ?? '')) ?: null,
                'designer_attitude' => $data['designer_attitude'],
                'design_satisfaction' => $data['design_satisfaction'],
                'rework_iteration' => $data['rework_iteration'],
                'meeting_deadline' => $data['meeting_deadline'],
                'client_satisfaction' => $data['client_satisfaction'],
                'overall_rating' => $overall,
            ]);

            $fromStatus = $lockedTask->status;
            $lockedTask->update(['status' => 'completed']);

            DesignTaskStatusHistory::create([
                'design_task_id' => $lockedTask->id,
                'from_status' => $fromStatus,
                'to_status' => 'completed',
                'changed_by' => $request->user()->id,
                'change_source' => 'bd_completion_rating',
                'note' => 'Task completed by BD with an overall rating of '.number_format($overall, 2).' / 5.',
            ]);
        });

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => 'ratings'])
            ->with('success', 'Task completed and rating submitted successfully.');
    }

    private function authorizeBdTask(Request $request, DesignTask $task): void
    {
        abort_unless(
            $request->user()?->role === 'bd'
            && (int) $task->assigned_by === (int) $request->user()->id,
            403
        );
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

        $redirectTab = in_array($request->input('redirect_tab'), ['overview', 'comments'], true)
            ? $request->input('redirect_tab')
            : 'comments';

        return redirect()
            ->route('bd.tasks.show', ['task' => $task, 'tab' => $redirectTab])
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
