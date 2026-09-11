<?php

namespace App\Livewire\Bd;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use App\Services\DesignerHeadTaskBoardService;
use App\Services\DesignTaskBdApprovalService;
use App\Services\TaskNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';

    public string $vertical = '';

    public string $priority = '';

    public string $designerId = '';

    public string $projectNumber = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $needsRefresh = false;

    public bool $confirmModalOpen = false;

    public ?int $confirmTaskId = null;

    /** 'approve' | 'reject' */
    public string $confirmAction = '';

    public string $confirmComment = '';

    public string $confirmTaskLabel = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'bd', 403);
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');

        // The shake state is a temporary in-page attention cue, not restored
        // history — a full page load (including a browser reload) always
        // starts with the Refresh button un-shaken; only a live WebSocket
        // event received while this page stays open shakes it again.
        $this->needsRefresh = false;
    }

    /** Fired only by an explicit Refresh-button click (see refresh-button component). */
    #[On('refresh-tasks')]
    public function refreshBoard(): void
    {
        $this->needsRefresh = false;
    }

    public function markRework(int $taskId): void
    {
        $this->moveBdOwnedStatus($taskId, 'rework');
    }

    public function markCompleted(int $taskId): void
    {
        $this->moveBdOwnedStatus($taskId, 'completed');
    }

    private function moveBdOwnedStatus(int $taskId, string $targetStatus): void
    {
        abort_unless(in_array($targetStatus, ['rework', 'completed'], true), 403);

        DB::transaction(function () use ($taskId, $targetStatus) {
            $task = DesignTask::query()
                ->lockForUpdate()
                ->whereKey($taskId)
                ->where('assigned_by', Auth::id())
                ->firstOrFail();

            if ($task->status !== 'waiting_confirmation') {
                throw ValidationException::withMessages([
                    'status' => 'BD can select Rework or Completed only from Waiting for Confirmation.',
                ]);
            }

            $fromStatus = $task->status;

            $task->update([
                'status' => $targetStatus,
            ]);

            DesignTaskStatusHistory::create([
                'design_task_id' => $task->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by' => Auth::id(),
                'change_source' => 'bd_action',
                'note' => $targetStatus === 'completed'
                    ? 'Task marked completed by BD.'
                    : 'Task sent for rework by BD.',
            ]);
        });

        $this->dispatch(
            'bd-task-updated',
            message: $targetStatus === 'completed'
                ? 'Task marked as Completed.'
                : 'Task moved to Rework.'
        );
    }

    /**
     * A Designer-created task's confirmation is decided from this same "New
     * Task Requests" Kanban column — approve (comment optional) or reject
     * (comment mandatory). Follows the exact hand-rolled lock/update/history
     * pattern moveBdOwnedStatus() already uses for rework/complete, rather
     * than routing through DesignTaskStatusService (which never governs
     * pre-pipeline statuses — see DesignTaskStatusService::designerCanMove()).
     */
    public function openApproveConfirmation(int $taskId): void
    {
        $this->openConfirmModal($taskId, 'approve');
    }

    public function openRejectConfirmation(int $taskId): void
    {
        $this->openConfirmModal($taskId, 'reject');
    }

    private function openConfirmModal(int $taskId, string $action): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('assigned_by', Auth::id())
            ->where('status', 'pending_bd_approval')
            ->firstOrFail();

        $this->confirmTaskId = $task->id;
        $this->confirmAction = $action;
        $this->confirmComment = '';
        $this->confirmTaskLabel = $task->task_id.' — '.$task->task_name;
        $this->confirmModalOpen = true;
    }

    public function cancelConfirmModal(): void
    {
        $this->reset(['confirmModalOpen', 'confirmTaskId', 'confirmAction', 'confirmComment', 'confirmTaskLabel']);
    }

    public function submitConfirmDecision(): void
    {
        $this->validate([
            'confirmComment' => [$this->confirmAction === 'reject' ? 'required' : 'nullable', 'string', 'max:5000'],
        ], [], ['confirmComment' => 'comment']);

        $taskId = (int) $this->confirmTaskId;
        $comment = trim($this->confirmComment) !== '' ? trim($this->confirmComment) : null;

        if ($this->confirmAction === 'reject') {
            $this->rejectConfirmation($taskId, (string) $comment);
        } else {
            $this->approveConfirmation($taskId, $comment);
        }

        $this->cancelConfirmModal();
    }

    private function approveConfirmation(int $taskId, ?string $comment): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('assigned_by', Auth::id())
            ->where('status', 'pending_bd_approval')
            ->firstOrFail();

        $task = app(DesignTaskBdApprovalService::class)->approve($task, Auth::user(), $comment);

        app(TaskNotificationService::class)->bdApprovalDecided($task, 'approved', Auth::user(), $comment);

        $this->dispatch('bd-task-updated', message: 'Task confirmed and moved to New Assignments.');
    }

    private function rejectConfirmation(int $taskId, string $comment): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('assigned_by', Auth::id())
            ->where('status', 'pending_bd_approval')
            ->firstOrFail();

        $task = app(DesignTaskBdApprovalService::class)->reject($task, Auth::user(), $comment);

        app(TaskNotificationService::class)->bdApprovalDecided($task, 'rejected', Auth::user(), $comment);

        $this->dispatch('bd-task-updated', message: 'Task rejected.');
    }

    private function filterArray(): array
    {
        $isOverdue = $this->priority === 'overdue';

        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $isOverdue ? '' : $this->priority,
            'designerId' => $this->designerId,
            'bdId' => (string) Auth::id(),
            'projectNumber' => $this->projectNumber,
            'period' => $this->period,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'overdue' => $isOverdue,
        ];
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->vertical = '';
        $this->priority = '';
        $this->designerId = '';
        $this->projectNumber = '';
        $this->period = 'current_month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Human-readable "Filter: value" chips for whichever filters are
     * currently non-default, same idea as the Designer Head board.
     */
    private function appliedFilters(SupportCollection $designers, string $periodLabel): SupportCollection
    {
        $chips = collect();

        if ($this->search !== '') {
            $chips->push(['label' => 'Search', 'value' => $this->search]);
        }
        if ($this->designerId !== '') {
            $chips->push(['label' => 'Designer', 'value' => $designers->firstWhere('id', (int) $this->designerId)?->name ?? '—']);
        }
        if ($this->projectNumber !== '') {
            $chips->push(['label' => 'Zoho Project Number', 'value' => $this->projectNumber]);
        }
        if ($this->vertical !== '') {
            $chips->push(['label' => 'Vertical', 'value' => ucwords(str_replace('_', ' ', $this->vertical))]);
        }
        if ($this->priority !== '') {
            $chips->push(['label' => 'Priority', 'value' => ucfirst($this->priority)]);
        }
        if ($this->period !== 'current_month') {
            $chips->push(['label' => 'Period', 'value' => $periodLabel]);
        }

        return $chips;
    }

    /**
     * Build one compact request-status badge for each visible Kanban card.
     *
     * Only the latest request is shown so the card stays clean while still
     * exposing the current state of Decline, Task Split and Task Transfer requests.
     */
    private function buildTaskTags(Collection $tasks): SupportCollection
    {
        if ($tasks->isEmpty()) {
            return collect();
        }

        $requests = DesignTaskRequest::query()
            ->whereIn('design_task_id', $tasks->pluck('id'))
            ->whereIn('request_type', ['decline', 'split', 'swap', 'status_change'])
            ->latest('created_at')
            ->get()
            ->groupBy('design_task_id');

        return $tasks->mapWithKeys(function (DesignTask $task) use ($requests) {
            if (data_get($task->requirements, '_split_from_task_id')) {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
            }

            $latestRequest = $requests->get($task->id, collect())->first();

            if (! $latestRequest) {
                return [$task->id => []];
            }

            $typeLabel = match ($latestRequest->request_type) {
                'split' => 'Split',
                'swap' => 'Swap',
                'decline' => 'Decline',
                'status_change' => 'Status Change',
                default => 'Request',
            };

            $isPending = in_array(
                $latestRequest->overall_status,
                ['pending_approval', 'pending_designer_head', 'pending_admin'],
                true
            );

            if ($latestRequest->request_type === 'status_change' && $isPending) {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => '⏳ Approval Pending',
                    'title' => 'Waiting for Status Change Approval',
                    'class' => 'task-request-status task-request-pending',
                ]]];
            }

            if ($latestRequest->request_type === 'decline' && $latestRequest->overall_status === 'approved') {
                return [
                    $task->id => [[
                        'key' => 'latest-request',
                        'label' => 'Task Transferred',
                        'class' => 'task-request-status task-request-approved',
                    ]],
                ];
            }

            if ($latestRequest->request_type === 'split' && $latestRequest->overall_status === 'approved') {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
            }

            $statusLabel = $isPending
                ? 'Pending'
                : ($latestRequest->overall_status === 'approved' ? 'Approved' : 'Declined');

            $statusClass = $isPending
                ? 'task-request-pending'
                : ($latestRequest->overall_status === 'approved'
                    ? 'task-request-approved'
                    : 'task-request-declined');

            return [
                $task->id => [[
                    'key' => 'latest-request',
                    'label' => $typeLabel.' · '.$statusLabel,
                    'class' => 'task-request-status '.$statusClass,
                ]],
            ];
        });
    }

    public function render()
    {
        $board = app(DesignerHeadTaskBoardService::class)->build($this->filterArray());

        $ownTasks = $board['tasks'];
        $tasks = $board['visibleTasks'];
        $periodStart = $board['periodStart'];

        // Drafts are the BD's own work-in-progress records only and never flow
        // through the shared board service (that one is used by Designer/
        // Designer Head too). They are queried here directly, prepended as the
        // first column, and appended to the tasks so the column renders them.
        $drafts = DesignTask::query()
            ->with(['assigner:id,name'])
            ->where('status', 'draft')
            ->where('assigned_by', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        // Designer-created tasks awaiting this BD's confirmation — same
        // "queried directly, prepended as its own column" treatment as drafts,
        // since pending_bd_approval is deliberately excluded from the shared
        // board query (see DesignerHeadTaskBoardService).
        $pendingConfirmations = DesignTask::query()
            ->with(['designer:id,name'])
            ->where('status', 'pending_bd_approval')
            ->where('assigned_by', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        $statuses = $board['statuses'];
        $statuses = ['pending_bd_approval' => 'New Task Requests', 'draft' => 'Draft'] + $statuses;

        $tasks = $tasks->concat($drafts)->concat($pendingConfirmations);

        $periodLabel = $this->period === 'custom'
            ? $periodStart->format('d M Y').' – '.$board['periodEnd']->format('d M Y')
            : $periodStart->format('M Y');

        $designers = User::query()
            ->where('role', 'designer')
            ->where('is_active', true)
            ->whereHas('assignedTasks', fn ($query) => $query->where('assigned_by', Auth::id()))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.bd.task-kanban', [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'splitLogRows' => $board['splitLogRows'],
            'taskTags' => $this->buildTaskTags($tasks),
            'designers' => $designers,
            'periodLabel' => $periodLabel,
            'appliedFilters' => $this->appliedFilters($designers, $periodLabel),
            'activeBreakdown' => $board['activeBreakdown'],
            'stats' => [
                'total' => $ownTasks->count() + $drafts->count() + $pendingConfirmations->count(),
                'active' => $ownTasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $ownTasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $ownTasks->where('status', 'completed')->count(),
            ],
        ]);
    }
}
