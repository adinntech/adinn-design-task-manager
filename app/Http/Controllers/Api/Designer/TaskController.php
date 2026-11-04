<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Services\CloudMultipartUploadService;
use App\Services\CommentReadStateService;
use App\Services\DesignerHeadTaskBoardService;
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

    /** Kanban board as JSON: same data source as the web board (DesignerHeadTaskBoardService). */
    public function index(Request $request, DesignerHeadTaskBoardService $boards): JsonResponse
    {
        $user = $request->user();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:20'],
            'period' => ['nullable', 'in:current_month,last_month,custom'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $isOverdue = ($filters['priority'] ?? '') === 'overdue';

        $board = $boards->build([
            'search' => $filters['search'] ?? '',
            'vertical' => $filters['vertical'] ?? '',
            'priority' => $isOverdue ? '' : ($filters['priority'] ?? ''),
            'designerId' => (string) $user->id,
            'bdId' => '',
            'projectNumber' => '',
            'period' => $filters['period'] ?? 'current_month',
            'dateFrom' => $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d'),
            'dateTo' => $filters['date_to'] ?? now()->endOfMonth()->format('Y-m-d'),
            'overdue' => $isOverdue,
        ]);

        // Tasks the Designer created themselves and that await BD confirmation show here only.
        $pending = DesignTask::query()
            ->whereIn('status', ['pending_bd_approval', 'bd_rejected'])
            ->where('designer_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $statuses = $board['statuses'];
        unset($statuses['decline_tasks']);
        $statuses = ['pending_bd_approval' => 'Awaiting BD Approval', 'bd_rejected' => 'Rejected by BD'] + $statuses;

        $tasks = $board['visibleTasks']->concat($pending)->map(fn (DesignTask $t) => $this->summary($t))->values();

        return response()->json([
            'statuses' => collect($statuses)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'tasks' => $tasks,
            'stats' => [
                'total' => $board['tasks']->count() + $pending->count(),
                'active' => $board['tasks']->whereNotIn('status', ['completed'])->count(),
                'waiting' => $board['tasks']->where('status', 'waiting_confirmation')->count(),
                'completed' => $board['tasks']->where('status', 'completed')->count(),
            ],
        ]);
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
            'history' => DesignTaskStatusHistory::where('design_task_id', $task->id)->orderBy('id')
                ->get(['from_status', 'to_status', 'change_source', 'note', 'created_at']),
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
