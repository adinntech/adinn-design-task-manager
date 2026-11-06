<?php

namespace App\Livewire\Designer;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use App\Services\DesignerBoardService;
use App\Services\DesignTaskProgressService;
use App\Services\DesignTaskRequestService;
use App\Services\DesignTaskStatusService;
use App\Services\TaskNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class TaskKanban extends Component
{
    public string $search = '';

    public string $vertical = '';

    public string $priority = '';

    public string $bdId = '';

    public string $projectNumber = '';

    /** current_month | last_month | custom — scopes only the historical/final columns below. */
    public string $period = 'current_month';

    /** 'Y-m-d', used only when period === 'custom'. */
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $needsRefresh = false;

    public bool $backwardModalOpen = false;

    public ?int $backwardTaskId = null;

    public string $backwardFromStatus = '';

    public string $backwardToStatus = '';

    public string $backwardTaskLabel = '';

    public string $backwardReason = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'designer', 403);
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

    public function moveTask(int $taskId, string $targetStatus): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('designer_id', Auth::id())
            ->firstOrFail();

        try {
            app(DesignTaskStatusService::class)
                ->moveAsDesigner($task, Auth::user(), $targetStatus, 'kanban_drag');
        } catch (ValidationException $e) {
            $progressService = app(DesignTaskProgressService::class);

            if (
                $task->status === 'in_progress'
                && $targetStatus === 'waiting_confirmation'
                && $progressService->percentage($task) < 100
            ) {
                $completed = $progressService->completed($task);
                $remaining = $progressService->remaining($task);

                $this->dispatch('task-move-blocked', message: 'Creative Work Incomplete: Complete all creatives before BD Review. '
                    .$completed.' of '.$task->total_creatives.' completed • '.$remaining.' remaining.');

                return;
            }

            throw $e;
        }

        $this->dispatch('kanban-updated');
        $this->dispatch('task-status-changed', message: 'Task status updated successfully.');
    }

    /**
     * Called instead of moveTask() when a drag targets an earlier column — a
     * Designer can never move a task backward directly, only request it. Opens
     * the confirmation modal; the actual request is only created once the
     * Designer submits a mandatory reason via submitBackwardRequest().
     */
    public function confirmBackwardMove(int $taskId, string $targetStatus): void
    {
        $task = DesignTask::query()
            ->whereKey($taskId)
            ->where('designer_id', Auth::id())
            ->firstOrFail();

        if (! app(DesignTaskStatusService::class)->isBackwardMove($task->status, $targetStatus)) {
            return;
        }

        $this->backwardTaskId = $task->id;
        $this->backwardFromStatus = $task->status;
        $this->backwardToStatus = $targetStatus;
        $this->backwardTaskLabel = $task->task_id.' — '.$task->task_name;
        $this->backwardReason = '';
        $this->backwardModalOpen = true;
    }

    public function cancelBackwardMove(): void
    {
        $this->reset(['backwardModalOpen', 'backwardTaskId', 'backwardFromStatus', 'backwardToStatus', 'backwardTaskLabel', 'backwardReason']);
    }

    public function submitBackwardRequest(): void
    {
        $this->validate(['backwardReason' => ['required', 'string', 'max:5000']], [], ['backwardReason' => 'reason']);

        $task = DesignTask::query()
            ->whereKey($this->backwardTaskId)
            ->where('designer_id', Auth::id())
            ->firstOrFail();

        $createdRequest = app(DesignTaskRequestService::class)->create(
            $task,
            Auth::user(),
            'status_change',
            $this->backwardReason,
            ['to_status' => $this->backwardToStatus]
        );

        app(TaskNotificationService::class)->requestSubmitted($createdRequest);

        $this->cancelBackwardMove();
        $this->dispatch('kanban-updated');
        $this->dispatch('task-status-changed', message: 'Backward status request submitted — waiting for Designer Head approval.');
    }

    private function filterArray(): array
    {
        $isOverdue = $this->priority === 'overdue';

        return [
            'search' => $this->search,
            'vertical' => $this->vertical,
            'priority' => $isOverdue ? '' : $this->priority,
            'designerId' => (string) Auth::id(),
            'bdId' => $this->bdId,
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
        $this->bdId = '';
        $this->projectNumber = '';
        $this->period = 'current_month';
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Human-readable "Filter: value" chips for whichever filters are
     * currently non-default, same idea as the Designer Head board.
     */
    private function appliedFilters(SupportCollection $bds, string $periodLabel): SupportCollection
    {
        $chips = collect();

        if ($this->search !== '') {
            $chips->push(['label' => 'Search', 'value' => $this->search]);
        }
        if ($this->bdId !== '') {
            $chips->push(['label' => 'BD', 'value' => $bds->firstWhere('id', (int) $this->bdId)?->name ?? '—']);
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

    public function render()
    {
        // The board itself — columns, cards, request tags, split log, stats — is built by DesignerBoardService
        // (shared with the mobile API); only the filter chips are web-specific.
        $data = app(DesignerBoardService::class)->build(Auth::user(), $this->filterArray());

        return view('livewire.designer.task-kanban', $data + [
            'appliedFilters' => $this->appliedFilters($data['bds'], $data['periodLabel']),
        ]);
    }
}
