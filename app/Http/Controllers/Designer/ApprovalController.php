<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DesignerHead\RequestActionController;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Approvals" for Designers the Admin has granted permission to approve:
 * Swap / Split requests raised by teammates on the same Designer Head's team,
 * never their own request. The decision itself goes through the same
 * DesignTaskRequestService as the Designer Head, whose guard enforces every
 * rule again server-side (permission, team scope, own request, type).
 */
class ApprovalController extends Controller
{
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
        abort_unless($user && $user->isRequestApprover() && $user->designer_head_id, 403);

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

    public function index(Request $request): View
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

        return view('designer.approvals.index', ['pendingRequests' => $pending]);
    }

    public function show(Request $request, DesignTaskRequest $taskRequest): View
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

        return view('designer.approvals.show', [
            'taskRequest' => $taskRequest,
            'task' => $task,
            'isPending' => in_array($taskRequest->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true),
            'completedCreatives' => app(DesignTaskProgressService::class)->completed($task),
            'designers' => $this->teamDesigners($approver, (int) $task->designer_id),
        ]);
    }

    public function approve(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        $this->approver($request);

        $response = app(RequestActionController::class)->approve($request, $taskRequest, $service);

        return session()->has('error')
            ? $response
            : redirect()->route('designer.approvals.index')->with('success', session('success'));
    }

    public function reject(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service): RedirectResponse
    {
        $this->approver($request);

        $response = app(RequestActionController::class)->reject($request, $taskRequest, $service);

        return session()->has('error')
            ? $response
            : redirect()->route('designer.approvals.index')->with('success', session('success'));
    }

    /** Active team designers who can receive the work (everyone but the current assignee). */
    private function teamDesigners(User $approver, int $assigneeId): Collection
    {
        return User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->where('designer_head_id', $approver->designer_head_id)
            ->where('id', '<>', $assigneeId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
