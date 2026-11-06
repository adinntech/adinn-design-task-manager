<?php

namespace App\Http\Controllers\Api\DesignerHead;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskCommentAttachment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\CommentReadStateService;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use App\Services\TaskNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Designer Head mobile API: team board, request approvals, task detail and chat. */
class HeadController extends Controller
{
    use SerializesTasks;

    public function board(Request $request, DesignerHeadTaskBoardService $boards): JsonResponse
    {
        $head = $request->user();
        $teamIds = $this->teamDesignerIds($head);

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:20'],
            'designer_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'in:current_month,last_month,custom'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // A designer filter outside the team would otherwise be silently empty; reject it explicitly.
        if (! empty($f['designer_id']) && ! in_array((int) $f['designer_id'], $teamIds, true)) {
            abort(403, 'That designer is not on your team.');
        }

        $overdue = ($f['priority'] ?? '') === 'overdue';

        $board = $boards->build([
            'search' => $f['search'] ?? '',
            'vertical' => $f['vertical'] ?? '',
            'priority' => $overdue ? '' : ($f['priority'] ?? ''),
            'designerId' => (string) ($f['designer_id'] ?? ''),
            'bdId' => '',
            'projectNumber' => '',
            'period' => $f['period'] ?? 'current_month',
            'dateFrom' => $f['date_from'] ?? now()->startOfMonth()->format('Y-m-d'),
            'dateTo' => $f['date_to'] ?? now()->endOfMonth()->format('Y-m-d'),
            'overdue' => $overdue,
            'headDesignerIds' => $teamIds,
        ]);

        $tasks = $board['visibleTasks']->loadMissing('designer:id,name');

        return response()->json([
            'statuses' => collect($board['statuses'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'tasks' => $tasks->map(fn (DesignTask $t) => $this->summary($t))->values(),
            'designers' => $this->team($head),
            'stats' => [
                'total' => $board['tasks']->count(),
                'active' => $board['tasks']->whereNotIn('status', ['completed'])->count(),
                'waiting' => $board['tasks']->where('status', 'waiting_confirmation')->count(),
                'completed' => $board['tasks']->where('status', 'completed')->count(),
            ],
            'pending_requests_count' => $this->pendingRequests($teamIds)->count(),
        ]);
    }

    public function requests(Request $request): JsonResponse
    {
        $teamIds = $this->teamDesignerIds($request->user());

        return response()->json([
            'data' => $this->pendingRequests($teamIds)->get()->map(fn ($r) => $this->requestPayload($r))->values(),
            'designers' => $this->team($request->user()),
        ]);
    }

    public function approve(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service, TaskNotificationService $notifications): JsonResponse
    {
        $rules = ['decision_comment' => ['nullable', 'string', 'max:5000']];

        if (in_array($taskRequest->request_type, ['decline', 'split', 'swap'], true)) {
            $rules['approved_designer_id'] = ['required', Rule::exists('users', 'id')->where(
                fn ($q) => $q->where('role', 'designer')->where('is_active', true)
            )];
        }

        if ($taskRequest->request_type === 'split') {
            $rules['approved_creative_count'] = ['required', 'integer', 'min:1'];
        }

        $data = $request->validate($rules);

        // Team scope and pending-state are enforced inside the service (403 / 422).
        $decided = $service->approve(
            $taskRequest,
            $request->user(),
            isset($data['approved_designer_id']) ? (int) $data['approved_designer_id'] : null,
            isset($data['approved_creative_count']) ? (int) $data['approved_creative_count'] : null,
            $data['decision_comment'] ?? null,
        );

        $notifications->requestDecided($decided, 'approved', $request->user());

        return response()->json(['request' => $this->requestPayload($decided->load(['task', 'requester', 'targetDesigner'])), 'message' => 'Request accepted successfully.']);
    }

    public function reject(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service, TaskNotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['decision_reason' => ['required', 'string', 'max:5000']]);

        $decided = $service->reject($taskRequest, $request->user(), $data['decision_reason']);
        $notifications->requestDecided($decided, 'rejected', $request->user());

        return response()->json(['request' => $this->requestPayload($decided->load(['task', 'requester', 'targetDesigner'])), 'message' => 'Request declined successfully.']);
    }

    public function show(Request $request, DesignTask $task, DesignTaskProgressService $progress): JsonResponse
    {
        $this->authorizeTeamTask($request, $task);
        $task->loadMissing(['designer:id,name', 'assigner:id,name']);

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
            'progress' => [
                'completed' => $progress->completed($task),
                'total' => (int) $task->total_creatives,
                'remaining' => $progress->remaining($task),
                'percentage' => $progress->percentage($task),
            ],
            'history' => DesignTaskStatusHistory::where('design_task_id', $task->id)->orderBy('id')
                ->get(['from_status', 'to_status', 'change_source', 'note', 'created_at']),
            'requests' => DesignTaskRequest::with(['requester:id,name', 'targetDesigner:id,name'])
                ->where('design_task_id', $task->id)->latest()->get()->map(fn ($r) => $this->requestPayload($r))->values(),
        ]);
    }

    public function comments(Request $request, DesignTask $task, CommentReadStateService $readState): JsonResponse
    {
        $this->authorizeTeamTask($request, $task);
        $afterId = (int) $request->query('after_id', 0);

        $comments = DesignTaskComment::query()
            ->with(['user:id,name,role', 'attachments'])
            ->where('design_task_id', $task->id)
            ->where(fn ($q) => $q->whereNull('context')->orWhere('context', '!=', 'clarification'))
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')->limit(200)->get();

        $readState->markReadFor($request->user(), $task);

        return response()->json(['data' => $comments->map(fn ($c) => $this->commentPayload($c))->values()]);
    }

    public function storeComment(Request $request, DesignTask $task, TaskNotificationService $notifications): JsonResponse
    {
        $this->authorizeTeamTask($request, $task);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:102400'],
        ]);

        $comment = DB::transaction(function () use ($request, $task, $data) {
            $new = DesignTaskComment::create([
                'design_task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status_at_comment' => $task->status,
                'comment' => trim($data['comment']),
            ]);

            foreach ($request->file('attachments', []) as $i => $file) {
                $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';
                $name = sprintf('%s__comment-%05d__%s__%s__%02d.%s', $task->task_id, $new->id, $base, now()->format('Ymd-His-v'), $i + 1, strtolower($file->getClientOriginalExtension()));
                $root = trim((string) env('DO_SPACES_ROOT', 'design_task_manager'), '/');
                $dir = implode('/', [$root, now()->format('Y'), $task->vertical, $task->task_id.'_'.Str::slug($task->task_name), Str::slug($task->task_nature), 'comments', 'comment-'.$new->id]);

                DesignTaskCommentAttachment::create([
                    'design_task_comment_id' => $new->id,
                    'disk' => 'spaces',
                    'path' => $file->storePubliclyAs($dir, $name, 'spaces'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $new;
        });

        $notifications->commentAdded($task->fresh(), $request->user(), trim($data['comment']));

        return response()->json(['comment' => $this->commentPayload($comment->load(['user:id,name,role', 'attachments']))], 201);
    }

    private function teamDesignerIds(User $head): array
    {
        return User::query()->where('role', 'designer')->where('designer_head_id', $head->id)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function team(User $head)
    {
        return User::query()->where('role', 'designer')->where('is_active', true)
            ->where('designer_head_id', $head->id)->orderBy('name')->get(['id', 'name']);
    }

    private function pendingRequests(array $teamIds)
    {
        return DesignTaskRequest::query()
            ->pending()
            ->whereIn('request_type', ['decline', 'split', 'swap', 'status_change'])
            ->whereIn('requested_by', $teamIds)
            ->with(['task.designer:id,name', 'requester:id,name', 'targetDesigner:id,name'])
            ->latest();
    }

    private function authorizeTeamTask(Request $request, DesignTask $task): void
    {
        $headId = User::query()->whereKey($task->designer_id)->value('designer_head_id');
        abort_unless((int) $headId === (int) $request->user()->id, 403, 'This task is not on your team.');
    }
}
