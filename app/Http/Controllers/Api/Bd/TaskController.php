<?php

namespace App\Http\Controllers\Api\Bd;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskEodRecord;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\BdTaskReviewService;
use App\Services\CloudMultipartUploadService;
use App\Services\CommentReadStateService;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskBdApprovalService;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskStatusService;
use App\Services\TaskNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** BD mobile API: own-task board, detail, chat, rework, rating/completion, designer-task approvals. */
class TaskController extends Controller
{
    use SerializesTasks;

    public function board(Request $request, DesignerHeadTaskBoardService $boards): JsonResponse
    {
        $bd = $request->user();

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:20'],
            'designer_id' => ['nullable', 'integer'],
            'project_number' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'in:current_month,last_month,custom'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $overdue = ($f['priority'] ?? '') === 'overdue';

        $board = $boards->build([
            'search' => $f['search'] ?? '',
            'vertical' => $f['vertical'] ?? '',
            'priority' => $overdue ? '' : ($f['priority'] ?? ''),
            'designerId' => (string) ($f['designer_id'] ?? ''),
            'bdId' => (string) $bd->id,
            'projectNumber' => $f['project_number'] ?? '',
            'period' => $f['period'] ?? 'current_month',
            'dateFrom' => $f['date_from'] ?? now()->startOfMonth()->format('Y-m-d'),
            'dateTo' => $f['date_to'] ?? now()->endOfMonth()->format('Y-m-d'),
            'overdue' => $overdue,
        ]);

        $drafts = DesignTask::where('status', 'draft')->where('assigned_by', $bd->id)->orderByDesc('created_at')->get();
        $pending = DesignTask::with('designer:id,name')->where('status', 'pending_bd_approval')->where('assigned_by', $bd->id)->orderByDesc('created_at')->get();

        $tasks = $board['visibleTasks']->loadMissing('designer:id,name')->concat($drafts)->concat($pending);
        $statuses = ['pending_bd_approval' => 'New Task Requests', 'draft' => 'Draft'] + $board['statuses'];

        return response()->json([
            'statuses' => collect($statuses)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            // The extra keys let the app draw the same kanban card as the designer's board.
            'tasks' => $tasks->map(fn (DesignTask $t) => $this->summary($t) + [
                'party_type' => $t->party_type,
                'created_at' => $t->created_at?->toIso8601String(),
                'now_with' => $t->designer?->name,
            ])->values(),
            'designers' => User::where('role', 'designer')->where('is_active', true)
                ->whereHas('assignedTasks', fn ($q) => $q->where('assigned_by', $bd->id))->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => $board['tasks']->count() + $drafts->count() + $pending->count(),
                'active' => $board['tasks']->whereNotIn('status', ['completed'])->count(),
                'waiting' => $board['tasks']->where('status', 'waiting_confirmation')->count(),
                'completed' => $board['tasks']->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function show(Request $request, DesignTask $task, DesignTaskProgressService $progress, CommentReadStateService $readState): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        $task->loadMissing(['designer:id,name,email', 'assigner:id,name']);

        $readState->markReadFor($request->user(), $task);

        $clarifications = DesignTaskComment::with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $task->id)->where('context', 'clarification')->orderBy('id')->get();

        return response()->json([
            'task' => $this->summary($task) + [
                'party_type' => $task->party_type,
                'contact_person' => $task->contact_person,
                'mobile_number' => $task->mobile_number,
                'zoho_project_number' => $task->zoho_project_number,
                'requirements' => $task->requirements,
                'assigned_at' => $task->assigned_at?->toIso8601String(),
                'bd_approval_comment' => $task->bd_approval_comment,
            ],
            'progress' => [
                'completed' => $progress->completed($task),
                'total' => (int) $task->total_creatives,
                'remaining' => $progress->remaining($task),
                'percentage' => $progress->percentage($task),
                'rework_count' => $progress->reworkCount($task),
            ],
            'actions' => [
                'can_review' => $task->status === 'waiting_confirmation',
                'can_decide_confirmation' => $task->status === 'pending_bd_approval',
            ],
            'eod_records' => DesignTaskEodRecord::where('design_task_id', $task->id)->latest('submitted_at')
                ->get(['id', 'update_type', 'completed_count', 'cumulative_completed', 'remaining_creatives', 'rework_count_snapshot', 'submitted_at']),
            'reviews' => DesignTaskBdReview::with('submitter:id,name')->where('design_task_id', $task->id)->latest()->get()
                ->map(fn ($r) => [
                    'id' => $r->id, 'action' => $r->action, 'number_of_creatives' => $r->number_of_creatives, 'comment' => $r->comment,
                    'overall_rating' => $r->overall_rating, 'created_at' => $r->created_at?->toIso8601String(),
                ]),
            'history' => $this->timeline($task),
            'files' => $this->taskFiles($task),
            'edit_history' => $this->editHistory($task),
            'requests' => DesignTaskRequest::with(['requester:id,name', 'targetDesigner:id,name'])
                ->where('design_task_id', $task->id)->latest()->get()->map(fn ($r) => $this->requestPayload($r))->values(),
            'clarifications' => $clarifications->map(fn ($c) => $this->commentPayload($c))->values(),
        ]);
    }

    public function comments(Request $request, DesignTask $task): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        $afterId = (int) $request->query('after_id', 0);

        $comments = DesignTaskComment::with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $task->id)
            ->where(fn ($q) => $q->whereNull('context')->orWhere('context', '!=', 'clarification'))
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')->limit(200)->get();

        return response()->json(['data' => $comments->map(fn ($c) => $this->commentPayload($c))->values()]);
    }

    /** Chat message, or (clarification=true) a reply in the clarification thread. Files = finished `comment` upload ids. */
    public function storeComment(Request $request, DesignTask $task, CloudMultipartUploadService $uploads, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeOwner($request, $task);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'clarification' => ['nullable', 'boolean'],
            'upload_ids' => ['nullable', 'string', 'max:200'],
        ]);

        $isClarification = (bool) ($data['clarification'] ?? false);
        abort_if($isClarification && $task->status === 'completed', 403, 'Clarification is read-only once the task is completed.');

        $resolved = $uploads->resolveCommentUploads($request->user(), $task, $data['upload_ids'] ?? null);

        $comment = DB::transaction(function () use ($request, $task, $data, $isClarification, $resolved, $uploads) {
            $c = DesignTaskComment::create([
                'design_task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status_at_comment' => $isClarification ? 'need_clarification' : $task->status,
                'context' => $isClarification ? 'clarification' : 'comment',
                'comment' => trim($data['comment']),
            ]);

            if ($resolved !== []) {
                $uploads->attachToComment($c, $task, $resolved);
            }

            return $c;
        });

        $notifications->commentAdded($task->fresh(), $request->user(), trim($data['comment']), $isClarification);

        return response()->json(['comment' => $this->commentPayload($comment->load(['user:id,name,role', 'attachments']))], 201);
    }

    public function rework(Request $request, DesignTask $task, BdTaskReviewService $reviews): JsonResponse
    {
        $this->authorizeOwner($request, $task);

        $data = $request->validate([
            'number_of_creatives' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $task->total_creatives)],
            'comment' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:zip', 'max:'.(6 * 1024 * 1024)],
        ]);

        $updated = $reviews->requestRework($request->user(), $task, (int) $data['number_of_creatives'], $data['comment'], $request->file('attachment'));

        return response()->json(['task' => $this->summary($updated), 'message' => 'Task moved to Rework.']);
    }

    public function complete(Request $request, DesignTask $task, BdTaskReviewService $reviews): JsonResponse
    {
        $this->authorizeOwner($request, $task);

        $half = function (string $attribute, mixed $value, \Closure $fail): void {
            $n = (float) $value;
            if ($n < 0.5 || $n > 5 || abs(($n * 2) - round($n * 2)) > 0.0001) {
                $fail('Each rating must be between 0.5 and 5 stars in 0.5-star increments.');
            }
        };

        $data = $request->validate([
            'designer_attitude' => ['required', 'numeric', $half],
            'design_satisfaction' => ['required', 'numeric', $half],
            'rework_iteration' => ['required', 'numeric', $half],
            'meeting_deadline' => ['required', 'numeric', $half],
            'client_satisfaction' => ['required', 'numeric', $half],
            'rating_comment' => ['nullable', 'string', 'max:10000'],
            'completion_type' => ['required', 'in:completed,prepare_printing_file'],
        ]);

        $updated = $reviews->completeWithRating($request->user(), $task, $data, $data['rating_comment'] ?? null, $data['completion_type']);

        return response()->json(['task' => $this->summary($updated)]);
    }

    /** Confirm a Designer-created task (comment optional). */
    public function approve(Request $request, DesignTask $task, DesignTaskBdApprovalService $service, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);
        $comment = isset($data['comment']) && trim($data['comment']) !== '' ? trim($data['comment']) : null;

        $updated = $service->approve($task, $request->user(), $comment);
        $notifications->bdApprovalDecided($updated, 'approved', $request->user(), $comment);

        return response()->json(['task' => $this->summary($updated)]);
    }

    /** Reject a Designer-created task (comment mandatory). */
    public function reject(Request $request, DesignTask $task, DesignTaskBdApprovalService $service, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeOwner($request, $task);
        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']]);

        $updated = $service->reject($task, $request->user(), trim($data['comment']));
        $notifications->bdApprovalDecided($updated, 'rejected', $request->user(), trim($data['comment']));

        return response()->json(['task' => $this->summary($updated)]);
    }

    private function authorizeOwner(Request $request, DesignTask $task): void
    {
        abort_unless((int) $task->assigned_by === (int) $request->user()->id, 403, 'This is not your task.');
    }
}
