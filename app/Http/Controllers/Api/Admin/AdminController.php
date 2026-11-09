<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\SerializesTasks;
use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskComment;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use App\Services\TaskNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin mobile API (monitoring-focused): dashboard, task monitor, users, split/swap approvals. */
class AdminController extends Controller
{
    use SerializesTasks;

    public function dashboard(): JsonResponse
    {
        $now = now();

        $pipeline = collect(DesignTaskStatusService::STATUSES)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'count' => DesignTask::where('status', $key)->count(),
        ])->values();

        return response()->json([
            'stats' => [
                'total_tasks' => DesignTask::count(),
                'active_tasks' => DesignTask::where('status', '!=', 'completed')->count(),
                'waiting_confirmation' => DesignTask::where('status', 'waiting_confirmation')->count(),
                'rework' => DesignTask::where('status', 'rework')->count(),
                'overdue' => DesignTask::where('status', '!=', 'completed')->where('due_at', '<', $now)->count(),
                'completed' => DesignTask::where('status', 'completed')->count(),
                'active_designers' => User::where('role', 'designer')->where('is_active', true)->count(),
                'active_bd' => User::where('role', 'bd')->where('is_active', true)->count(),
            ],
            'pipeline' => $pipeline,
            'request_stats' => [
                'pending' => DesignTaskRequest::pending()->count(),
                'decline' => DesignTaskRequest::pending()->where('request_type', 'decline')->count(),
                'split' => DesignTaskRequest::pending()->where('request_type', 'split')->count(),
                'swap' => DesignTaskRequest::pending()->where('request_type', 'swap')->count(),
            ],
            'designer_workload' => User::where('role', 'designer')->where('is_active', true)
                ->withCount([
                    'assignedTasks as active_tasks_count' => fn ($q) => $q->where('status', '!=', 'completed'),
                    'assignedTasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
                ])->orderByDesc('active_tasks_count')->limit(8)->get(['id', 'name']),
            'recent_activity' => DesignTaskStatusHistory::with(['task:id,task_id,task_name', 'changedBy:id,name'])
                ->latest()->limit(10)->get()->map(fn ($h) => [
                    'task_id' => $h->task?->task_id, 'task_name' => $h->task?->task_name,
                    'from_status' => $h->from_status, 'to_status' => $h->to_status,
                    'by' => $h->changedBy?->name, 'note' => $h->note, 'at' => $h->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:20'],
            'designer_id' => ['nullable', 'integer'],
            'bd_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = DesignTask::query()
            ->with(['designer:id,name', 'assigner:id,name'])
            ->where('status', '!=', 'draft')
            ->when(! empty($f['search']), function ($q) use ($f) {
                $term = '%'.trim($f['search']).'%';
                $q->where(fn ($w) => $w->where('task_id', 'like', $term)->orWhere('zoho_project_number', 'like', $term)
                    ->orWhere('task_name', 'like', $term)->orWhere('party_name', 'like', $term));
            })
            ->when(! empty($f['vertical']), fn ($q) => $q->where('vertical', $f['vertical']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['priority']), fn ($q) => $q->where('priority', $f['priority']))
            ->when(! empty($f['designer_id']), fn ($q) => $q->where('designer_id', $f['designer_id']))
            ->when(! empty($f['bd_id']), fn ($q) => $q->where('assigned_by', $f['bd_id']))
            ->latest('assigned_at')
            ->paginate($f['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (DesignTask $t) => $this->summary($t) + [
                'bd' => $t->assigner ? ['id' => $t->assigner->id, 'name' => $t->assigner->name] : null,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'filters' => [
                'statuses' => collect(DesignTaskStatusService::STATUSES)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
                'designers' => User::where('role', 'designer')->orderBy('name')->get(['id', 'name']),
                'bds' => User::where('role', 'bd')->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function task(DesignTask $task, DesignTaskProgressService $progress): JsonResponse
    {
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
                'rework_count' => $progress->reworkCount($task),
            ],
            'history' => $this->timeline($task),
            'edit_history' => $this->editHistory($task),
            'requests' => DesignTaskRequest::with(['requester:id,name', 'targetDesigner:id,name'])
                ->where('design_task_id', $task->id)->latest()->get()->map(fn ($r) => $this->requestPayload($r))->values(),
            'comments' => DesignTaskComment::with(['user:id,name,role', 'attachments'])
                ->where('design_task_id', $task->id)->orderBy('id')->limit(200)->get()->map(fn ($c) => $this->commentPayload($c))->values(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,bd,designer,designer_head'],
        ]);

        $users = User::query()
            ->when(! empty($f['role']), fn ($q) => $q->where('role', $f['role']))
            ->when(! empty($f['search']), function ($q) use ($f) {
                $term = '%'.trim($f['search']).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('username', 'like', $term)->orWhere('employee_code', 'like', $term));
            })
            ->orderBy('name')->limit(200)
            ->get(['id', 'name', 'username', 'employee_code', 'email', 'role', 'is_active', 'last_login_at']);

        return response()->json(['data' => $users]);
    }

    public function toggleUser(Request $request, User $user): JsonResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own Admin account.');

        $user->update(['is_active' => ! $user->is_active]);

        // A deactivated account must not keep working on a previously issued mobile token.
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json(['user' => ['id' => $user->id, 'is_active' => (bool) $user->is_active]]);
    }

    /** Pending requests an Admin can decide (decline / status-change are the Designer Head's call). */
    public function requests(): JsonResponse
    {
        $pending = DesignTaskRequest::pending()
            ->whereIn('request_type', ['split', 'swap'])
            ->with(['task.designer:id,name', 'requester:id,name', 'targetDesigner:id,name'])
            ->latest()->limit(50)->get();

        return response()->json([
            'data' => $pending->map(fn ($r) => $this->requestPayload($r))->values(),
            'designers' => User::where('role', 'designer')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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

        $decided = $service->approve(
            $taskRequest, $request->user(),
            isset($data['approved_designer_id']) ? (int) $data['approved_designer_id'] : null,
            isset($data['approved_creative_count']) ? (int) $data['approved_creative_count'] : null,
            $data['decision_comment'] ?? null,
        );
        $notifications->requestDecided($decided, 'approved', $request->user());

        return response()->json(['request' => $this->requestPayload($decided->load(['task', 'requester', 'targetDesigner']))]);
    }

    public function reject(Request $request, DesignTaskRequest $taskRequest, DesignTaskRequestService $service, TaskNotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['decision_reason' => ['required', 'string', 'max:5000']]);

        $decided = $service->reject($taskRequest, $request->user(), $data['decision_reason']);
        $notifications->requestDecided($decided, 'rejected', $request->user());

        return response()->json(['request' => $this->requestPayload($decided->load(['task', 'requester', 'targetDesigner']))]);
    }
}
