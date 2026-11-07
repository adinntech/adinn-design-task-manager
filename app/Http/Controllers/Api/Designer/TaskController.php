<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskBdReview;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\CloudMultipartUploadService;
use App\Services\CommentReadStateService;
use App\Services\DesignerBoardService;
use App\Services\DesignerTaskAccessService;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use App\Services\TaskNotificationService;
use App\Services\TaskProgressSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    use SerializesTasks;

    /**
     * "My Tasks" board as JSON. Built by DesignerBoardService — the same code as the web board — so the app
     * shows the same columns, cards, request tags, split log and counts.
     */
    public function index(Request $request, DesignerBoardService $boards): JsonResponse
    {
        $user = $request->user();

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:20'],
            'bd_id' => ['nullable', 'integer'],
            'project_number' => ['nullable', 'string', 'max:60'],
            'period' => ['nullable', 'in:current_month,last_month,custom'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $isOverdue = ($f['priority'] ?? '') === 'overdue';
        $period = $f['period'] ?? 'current_month';

        $data = $boards->build($user, [
            'search' => $f['search'] ?? '',
            'vertical' => $f['vertical'] ?? '',
            'priority' => $isOverdue ? '' : ($f['priority'] ?? ''),
            'designerId' => (string) $user->id,
            'bdId' => (string) ($f['bd_id'] ?? ''),
            'projectNumber' => $f['project_number'] ?? '',
            'period' => $period,
            'dateFrom' => $f['date_from'] ?? now()->startOfMonth()->format('Y-m-d'),
            'dateTo' => $f['date_to'] ?? now()->endOfMonth()->format('Y-m-d'),
            'overdue' => $isOverdue,
        ]);

        // The web renders a card only under a column; a status with no column (e.g. another designer's
        // approved-decline record) is simply not shown, so it must not be sent either.
        $tasks = $data['tasks']->filter(fn (DesignTask $t) => array_key_exists($t->status, $data['statuses']))->values();
        $tasks->each->loadMissing(['assigner:id,name', 'designer:id,name', 'bdReview']);

        // Web column order: the Split Tasks log sits right after Completed.
        $columns = [];
        foreach ($data['statuses'] as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label, 'type' => 'status'];

            if ($key === 'completed') {
                $columns[] = ['key' => 'split_log', 'label' => 'Split Tasks ('.$data['periodLabel'].')', 'type' => 'split_log'];
            }
        }

        return response()->json([
            'columns' => $columns,
            'statuses' => collect($columns)->where('type', 'status')->map(fn ($c) => ['key' => $c['key'], 'label' => $c['label']])->values(),
            'tasks' => $tasks->map(fn (DesignTask $t) => $this->card($t, $data['taskTags']->get($t->id, [])[0] ?? null))->values(),
            // Every split ticket with its own Status, as the web's Split Tasks column: approved splits (child task
            // attached) plus pending, rejected and "completed all before approval" ones (these open the original task).
            'split_log' => $data['splitBoardRows']
                ->map(function (array $row) use ($user) {
                    $shown = $row['childTask'] ?? $row['task'];

                    return $shown === null ? null : [
                        'id' => $shown->id,
                        'request_id' => $row['request']->id,
                        'task_id' => $shown->task_id,
                        'name' => $shown->display_task_name ?? $shown->task_name,
                        'has_child' => $row['childTask'] !== null,
                        'status' => $row['request']->splitStatus($user),
                        'split_from' => $row['request']->task?->task_id,
                        'designer' => $row['childTask']?->designer?->name,
                        'creatives' => (int) $shown->total_creatives,
                        'requested_split' => $row['request']->splitCreativeSummary()['requested'],
                        'approved_at' => $row['childTask'] ? $row['request']->responded_at?->toIso8601String() : null,
                        'requested_at' => $row['request']->created_at?->toIso8601String(),
                    ];
                })
                ->filter()
                ->values(),
            'stats' => $data['stats'],
            'active_breakdown' => $data['activeBreakdown'],
            'period_label' => $data['periodLabel'],
            'bds' => $data['bds']->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
        ]);
    }

    /** A board card: the summary plus everything the web card shows. */
    private function card(DesignTask $task, ?array $tag): array
    {
        $showsProgress = in_array($task->status, ['in_progress', 'waiting_confirmation', 'rework'], true);
        $progress = $showsProgress ? app(DesignTaskProgressService::class) : null;
        $rating = $task->status === 'completed' ? $task->bdReview?->overall_rating : null;

        // Where this card may go, straight from the state machine (never re-implemented in the app).
        $states = app(DesignTaskStatusService::class);
        $targets = array_keys(array_diff_key(DesignTaskStatusService::STATUSES, ['swap_tasks' => true]));

        return $this->summary($task) + [
            'forward_to' => array_values(array_filter($targets, fn ($to) => $states->designerCanMove($task->status, $to))),
            'backward_to' => array_values(array_filter($targets, fn ($to) => $states->isBackwardMove($task->status, $to))),
            'party_type' => $task->party_type,
            'assigner' => $task->assigner?->name,
            'created_at' => $task->created_at?->toIso8601String(),
            'completed_at' => $task->status === 'completed' ? $task->bdReview?->created_at?->toIso8601String() : null,
            'rating' => $rating === null ? null : DesignTaskBdReview::roundToHalfStar($rating),
            'now_with' => $task->status === 'self_declined' ? $task->designer?->name : null,
            'progress' => $progress ? [
                'completed' => $progress->completed($task),
                'remaining' => $progress->remaining($task),
                'percentage' => $progress->percentage($task),
            ] : null,
            // Lets the app show the live "BD Review Time" the web card shows.
            'waiting_since' => $task->status === 'waiting_confirmation'
                ? DesignTaskStatusHistory::where('design_task_id', $task->id)->where('to_status', 'waiting_confirmation')->latest('created_at')->value('created_at')?->toIso8601String()
                : null,
            'continuation_label' => $task->continuation_label ?? null,
            'continuation_event_label' => $task->continuation_event_label ?? null,
            'previous_month_label' => ($task->is_previous_month_task ?? false) ? $task->previous_month_label : null,
            'tag' => $tag === null ? null : [
                'label' => $tag['label'],
                'title' => $tag['title'] ?? $tag['label'],
                'tone' => str_contains($tag['class'], 'approved') ? 'approved' : (str_contains($tag['class'], 'declined') ? 'declined' : 'pending'),
            ],
        ];
    }

    public function show(Request $request, DesignTask $task, DesignerTaskAccessService $access, DesignTaskProgressService $progress, DesignTaskStatusService $statusService): JsonResponse
    {
        $access = $this->authorizeTask($request, $task, $access);

        $task->loadMissing(['assigner:id,name,email', 'designer:id,name']);

        $nextStatus = ! $access['read_only'] ? $statusService->nextDesignerStatus($task->status) : null;

        return response()->json([
            'task' => $this->summary($task) + [
                'party_type' => $task->party_type,
                'contact_person' => $task->contact_person,
                'mobile_number' => $task->mobile_number,
                'zoho_project_number' => $task->zoho_project_number,
                'bd' => $task->assigner ? ['id' => $task->assigner->id, 'name' => $task->assigner->name] : null,
                'requirements' => $task->requirements,
                'assigned_at' => $task->assigned_at?->toIso8601String(),
            ],
            'access' => $access,
            'progress' => [
                'completed' => $progress->completed($task),
                'total' => (int) $task->total_creatives,
                'remaining' => $progress->remaining($task),
                'percentage' => $progress->percentage($task),
            ],
            'next_status' => $nextStatus,
            'history' => $this->timeline($task),
            'requests' => DesignTaskRequest::where('design_task_id', $task->id)->latest()
                ->get(['id', 'request_type', 'from_status', 'to_status', 'reason', 'overall_status', 'created_at']),
        ]);
    }

    /** Status move through the same state machine as the kanban drag. */
    public function move(Request $request, DesignTask $task, DesignerTaskAccessService $access, DesignTaskStatusService $statusService, DesignTaskProgressService $progress): JsonResponse
    {
        $this->authorizeTask($request, $task, $access, interact: true);
        $target = $request->validate(['status' => ['required', 'string']])['status'];

        try {
            $updated = $statusService->moveAsDesigner($task, $request->user(), $target, 'mobile_app');
        } catch (ValidationException $e) {
            if ($task->status === 'in_progress' && $target === 'waiting_confirmation' && $progress->percentage($task) < 100) {
                throw ValidationException::withMessages(['status' => sprintf(
                    'Creative Work Incomplete: Complete all creatives before BD Review. %d of %d completed • %d remaining.',
                    $progress->completed($task), $task->total_creatives, $progress->remaining($task)
                )]);
            }

            throw $e;
        }

        return response()->json(['task' => $this->summary($updated)]);
    }

    /** A designer can never move backward directly; they raise a status_change request instead. */
    public function backwardRequest(Request $request, DesignTask $task, DesignerTaskAccessService $access, DesignTaskStatusService $statusService, DesignTaskRequestService $requests, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeTask($request, $task, $access, interact: true);
        $data = $request->validate([
            'to_status' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        if (! $statusService->isBackwardMove($task->status, $data['to_status'])) {
            throw ValidationException::withMessages(['to_status' => 'That is not a backward move.']);
        }

        $created = $requests->create($task, $request->user(), 'status_change', $data['reason'], ['to_status' => $data['to_status']]);
        $notifications->requestSubmitted($created);

        return response()->json(['request_id' => $created->id, 'message' => 'Request submitted — waiting for Designer Head approval.'], 201);
    }

    /** Task Updation (EOD): creatives added to progress + finished `progress_update` upload id(s). */
    public function eod(Request $request, DesignTask $task, DesignerTaskAccessService $access, TaskProgressSubmissionService $submissions): JsonResponse
    {
        $this->authorizeTask($request, $task, $access, interact: true);
        $data = $request->validate([
            'completed_count' => ['required', 'integer', 'min:1'],
            'upload_ids' => ['required', 'string', 'max:200'],
        ]);

        $result = $submissions->submitEod($request->user(), $task, (int) $data['completed_count'], $data['upload_ids']);

        return response()->json([
            'task' => $this->summary($result['task']),
            'auto_completed' => $result['auto_completed'],
            'message' => $result['auto_completed']
                ? 'All creatives completed. Ticket moved to Waiting for BD Review.'
                : 'Task Updation submitted successfully.',
        ], 201);
    }

    /** Rework submission with finished `rework` upload id(s). */
    public function rework(Request $request, DesignTask $task, DesignerTaskAccessService $access, TaskProgressSubmissionService $submissions): JsonResponse
    {
        $this->authorizeTask($request, $task, $access, interact: true);
        $data = $request->validate([
            'completed_count' => ['required', 'integer', 'min:1'],
            'upload_ids' => ['required', 'string', 'max:200'],
        ]);

        $result = $submissions->submitRework($request->user(), $task, (int) $data['completed_count'], $data['upload_ids']);

        return response()->json([
            'task' => $this->summary($result['task']),
            'next_stage' => $result['next_stage'],
            'pending_after' => $result['pending_after'],
        ], 201);
    }

    public function comments(Request $request, DesignTask $task, DesignerTaskAccessService $access, CommentReadStateService $readState): JsonResponse
    {
        $this->authorizeTask($request, $task, $access);

        $afterId = (int) $request->query('after_id', 0);

        $comments = DesignTaskComment::query()
            ->with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $task->id)
            ->where(fn ($q) => $q->whereNull('context')->orWhere('context', '!=', 'clarification'))
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $readState->markReadFor($request->user(), $task);

        return response()->json(['data' => $comments->map(fn ($c) => $this->commentPayload($c))->values()]);
    }

    public function storeComment(Request $request, DesignTask $task, DesignerTaskAccessService $access, CloudMultipartUploadService $uploads, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeTask($request, $task, $access, interact: false, writable: true);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'upload_ids' => ['nullable', 'string', 'max:200'], // comma-separated completed `comment` uploads
        ]);

        $resolved = $uploads->resolveCommentUploads($request->user(), $task, $data['upload_ids'] ?? null);

        $comment = DB::transaction(function () use ($request, $task, $data, $resolved, $uploads) {
            $comment = DesignTaskComment::create([
                'design_task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status_at_comment' => $task->status,
                'context' => 'comment',
                'comment' => trim($data['comment']),
            ]);

            if ($resolved !== []) {
                $uploads->attachToComment($comment, $task, $resolved);
            }

            return $comment;
        });

        $notifications->commentAdded($task->fresh(), $request->user(), trim($data['comment']), false);

        return response()->json(['comment' => $this->commentPayload($comment->load(['user:id,name,role', 'attachments']))], 201);
    }

    /** @return array{current:bool,read_only:bool,reason:?string} */
    private function authorizeTask(Request $request, DesignTask $task, DesignerTaskAccessService $service, bool $interact = false, bool $writable = false): array
    {
        $access = $service->resolve($request->user(), $task);
        abort_if($access === null, 403, 'You do not have access to this task.');

        // Moves/requests need full ownership; comments are blocked only for declined/split viewers
        // (a swapped-away Designer keeps comment access, as on the web).
        if ($interact) {
            abort_if($access['read_only'], 403, 'This task is read-only for you.');
        }
        if ($writable) {
            abort_if(in_array($access['reason'], ['declined', 'split'], true), 403, 'This task is read-only for you.');
        }

        return $access;
    }
}
