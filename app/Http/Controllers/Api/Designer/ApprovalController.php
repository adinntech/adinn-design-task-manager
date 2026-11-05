<?php

namespace App\Http\Controllers\Api\Designer;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Api\DesignerHead\HeadController;
use App\Http\Controllers\Controller;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use App\Services\TaskNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * "Approvals" for Designers the Admin allowed to approve (mobile twin of Designer\ApprovalController):
 * Swap / Split requests raised by teammates on the same Designer Head's team — never their own request.
 *
 * The decision itself goes through DesignTaskRequestService (via the Designer Head API action), whose guard
 * re-checks permission, team scope, own request and type on every call, so this controller adds no rules of its own.
 */
class ApprovalController extends Controller
{
    use SerializesTasks;

    private const RELATIONS = [
        'task:id,task_id,task_name,status,priority,due_at,designer_id,party_name,vertical,task_nature,total_creatives',
        'task.designer:id,name',
        'requester:id,name,designer_head_id',
        'targetDesigner:id,name',
        'approvedDesigner:id,name',
        'designerHeadActor:id,name,role',
        'adminActor:id,name,role',
    ];

    private function approver(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->isRequestApprover() && $user->designer_head_id, 403, 'You do not have permission to approve requests.');

        return $user;
    }

    /** Other designers on the approver's team (never the approver). */
    private function teammateIds(User $approver): array
    {
        return User::query()
            ->where('role', 'designer')
            ->where('designer_head_id', $approver->designer_head_id)
            ->whereKeyNot($approver->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function index(Request $request): JsonResponse
    {
        $approver = $this->approver($request);

        $pending = DesignTaskRequest::query()
            ->pending()
            ->whereIn('request_type', ['split', 'swap'])
            ->whereIn('requested_by', $this->teammateIds($approver))
            ->with(self::RELATIONS)
            ->latest()
            ->get()
            ->filter(fn (DesignTaskRequest $r) => $r->task && (int) $r->task->designer_id !== (int) $approver->id)
            ->values();

        return response()->json([
            'data' => $pending->map(fn (DesignTaskRequest $r) => $this->requestPayload($r))->values(),
            // Active team designers the work can be handed to (the approver included, as on the web form).
            'designers' => User::query()
                ->where('role', 'designer')->where('is_active', true)
                ->where('designer_head_id', $approver->designer_head_id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, DesignTaskRequest $taskRequest, DesignTaskProgressService $progress, DesignTaskRequestService $requests): JsonResponse
    {
        $approver = $this->approver($request);

        abort_unless(
            in_array($taskRequest->request_type, ['split', 'swap'], true)
                && in_array((int) $taskRequest->requested_by, $this->teammateIds($approver), true),
            403
        );

        $taskRequest->load(self::RELATIONS);
        abort_unless($taskRequest->task && (int) $taskRequest->task->designer_id !== (int) $approver->id, 403);

        $task = $taskRequest->task;

        return response()->json([
            'request' => $this->requestPayload($taskRequest) + [
                'status_label' => $taskRequest->status_label,
                'approved_designer' => $taskRequest->approvedDesigner?->name,
                'responded_by' => ($taskRequest->designerHeadActor ?? $taskRequest->adminActor)?->name,
                'responded_at' => ($taskRequest->designer_head_action_at ?? $taskRequest->admin_action_at)?->toIso8601String(),
                'notes' => data_get($taskRequest->split_details, 'notes'),
                'attachments' => collect($taskRequest->attachments ?? [])->map(fn ($path) => [
                    'name' => basename((string) $path),
                    'url' => Storage::disk('spaces')->url((string) $path),
                ])->values(),
            ],
            'task' => $this->summary($task) + [
                'client' => $task->party_name,
                'assigned_designer' => $task->designer?->name,
                'completed_creatives' => $progress->completed($task),
            ],
            'is_pending' => in_array($taskRequest->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true),
            'split' => $taskRequest->request_type === 'split' ? $requests->splitApprovalContext($task) : null,
            // Active teammates who can receive the work (everyone but the current assignee).
            'designers' => User::query()
                ->where('role', 'designer')->where('is_active', true)
                ->where('designer_head_id', $approver->designer_head_id)
                ->where('id', '<>', $task->designer_id)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function approve(Request $request, DesignTaskRequest $taskRequest, HeadController $head, DesignTaskRequestService $service, TaskNotificationService $notifications): JsonResponse
    {
        $this->approver($request);

        return $head->approve($request, $taskRequest, $service, $notifications);
    }

    public function reject(Request $request, DesignTaskRequest $taskRequest, HeadController $head, DesignTaskRequestService $service, TaskNotificationService $notifications): JsonResponse
    {
        $this->approver($request);

        return $head->reject($request, $taskRequest, $service, $notifications);
    }
}
