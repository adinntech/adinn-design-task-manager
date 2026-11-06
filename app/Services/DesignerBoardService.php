<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Everything the Designer "My Tasks" board shows, in one place. Extracted from
 * Livewire\Designer\TaskKanban::render() (and its helpers) so the web board and the mobile API are built
 * by the same code. $filters is the component's filterArray():
 * search, vertical, priority, designerId, bdId, projectNumber, period, dateFrom, dateTo, overdue.
 */
class DesignerBoardService
{
    /** @return array<string, mixed> the variables the `livewire.designer.task-kanban` view receives (minus appliedFilters) */
    public function build(User $designer, array $filters): array
    {
        $bds = $this->relatedBds($designer);
        $board = app(DesignerHeadTaskBoardService::class)->build($filters);

        $ownTasks = $board['tasks'];
        $visibleOwnTasks = $board['visibleTasks'];
        $periodStart = $board['periodStart'];

        $periodLabel = $filters['period'] === 'custom'
            ? $periodStart->format('d M Y').' – '.$board['periodEnd']->format('d M Y')
            : $periodStart->format('M Y');

        $statuses = $board['statuses'];
        unset($statuses['decline_tasks']);
        $statuses['self_declined'] = 'Self Declined';

        // Tasks this Designer created themselves and submitted for BD confirmation — deliberately excluded from
        // the shared board query (see DesignerHeadTaskBoardService) so they only ever show here, as the first
        // Kanban columns, until the BD decides.
        $ownPendingConfirmations = DesignTask::query()
            ->with(['assigner:id,name'])
            ->whereIn('status', ['pending_bd_approval', 'bd_rejected'])
            ->where('designer_id', $designer->id)
            ->orderByDesc('created_at')
            ->get();

        $statuses = [
            'pending_bd_approval' => 'Awaiting BD Approval',
            'bd_rejected' => 'Rejected by BD',
        ] + $statuses;

        $tasks = $visibleOwnTasks->concat($this->selfDeclinedTasks($designer, $filters))->concat($ownPendingConfirmations);

        return [
            'statuses' => $statuses,
            'tasks' => $tasks,
            'splitLogRows' => $board['splitLogRows'],
            'splitBoardRows' => $board['splitBoardRows'],
            'taskTags' => $this->buildTaskTags($designer, $tasks),
            'bds' => $bds,
            'periodLabel' => $periodLabel,
            'activeBreakdown' => $board['activeBreakdown'],
            'stats' => [
                'total' => $ownTasks->count() + $ownPendingConfirmations->count(),
                'active' => $ownTasks->whereNotIn('status', ['completed'])->count(),
                'waiting' => $ownTasks->where('status', 'waiting_confirmation')->count(),
                'completed' => $ownTasks->where('status', 'completed')->count(),
            ],
        ];
    }

    /**
     * Tasks this Designer declined that Designer Head approved for reassignment. Shown as a read-only
     * "Self Declined" column; the real designer_id no longer points at this Designer, so status is
     * overridden in-memory only.
     */
    public function selfDeclinedTasks(User $designer, array $filters): Collection
    {
        $search = (string) ($filters['search'] ?? '');
        $vertical = (string) ($filters['vertical'] ?? '');
        $priority = (string) ($filters['priority'] ?? '');
        $bdId = (string) ($filters['bdId'] ?? '');

        return DesignTask::query()
            ->with(['designer:id,name'])
            ->where('designer_id', '!=', $designer->id)
            ->whereHas('requests', function ($query) use ($designer) {
                $query->where('request_type', 'decline')
                    ->where('requested_by', $designer->id)
                    ->where('overall_status', 'approved');
            })
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.trim($search).'%';

                $query->where(function ($query) use ($term) {
                    $query->where('task_id', 'like', $term)
                        ->orWhere('task_name', 'like', $term)
                        ->orWhere('party_name', 'like', $term)
                        ->orWhere('vertical', 'like', $term)
                        ->orWhere('task_nature', 'like', $term)
                        ->orWhereHas('designer', fn ($designerQuery) => $designerQuery->where('name', 'like', $term))
                        ->orWhereHas('assigner', fn ($assignerQuery) => $assignerQuery->where('name', 'like', $term));
                });
            })
            ->when($vertical !== '', fn ($query) => $query->where('vertical', $vertical))
            ->when(! in_array($priority, ['', 'overdue'], true), fn ($query) => $query->where('priority', $priority))
            ->when($bdId !== '', fn ($query) => $query->where('assigned_by', $bdId))
            ->orderBy('due_at')
            ->get()
            ->each(fn (DesignTask $task) => $task->status = 'self_declined');
    }

    /**
     * One compact request-status badge per visible card, from the latest Decline / Split / Swap / status-change
     * request, so the card stays clean while still showing where the request stands.
     *
     * @return SupportCollection<int, array<int, array<string, string>>> keyed by task id
     */
    public function buildTaskTags(User $designer, Collection|SupportCollection $tasks): SupportCollection
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

        return $tasks->mapWithKeys(function (DesignTask $task) use ($requests, $designer) {
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

            if ($latestRequest->request_type === 'split' && $latestRequest->overall_status === 'approved') {
                return [$task->id => [[
                    'key' => 'latest-request',
                    'label' => 'Split Task',
                    'class' => 'task-request-status task-request-approved',
                ]]];
            }

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

            $typeLabel = match ($latestRequest->request_type) {
                'split' => 'Split',
                'swap' => 'Swap',
                'decline' => 'Decline',
                'status_change' => 'Status Change',
                default => 'Request',
            };

            $isDecline = $latestRequest->request_type === 'decline';

            if ($isDecline && $latestRequest->overall_status === 'approved' && (int) $latestRequest->requested_by !== (int) $designer->id) {
                return [
                    $task->id => [[
                        'key' => 'latest-request',
                        'label' => 'Transferred',
                        'class' => 'task-request-status task-request-approved',
                    ]],
                ];
            }

            $statusLabel = $isPending
                ? 'Pending'
                : ($latestRequest->overall_status === 'approved' ? 'Approved' : ($isDecline ? 'Rejected' : 'Declined'));

            $statusClass = $isPending
                ? 'task-request-pending'
                : ($latestRequest->overall_status === 'approved'
                    ? 'task-request-approved'
                    : 'task-request-declined');

            $separator = ($isDecline && ! $isPending) ? ' ' : ' · ';

            return [
                $task->id => [[
                    'key' => 'latest-request',
                    'label' => $typeLabel.$separator.$statusLabel,
                    'class' => 'task-request-status '.$statusClass,
                ]],
            ];
        });
    }

    /**
     * BD users who actually assigned/own a task related to this Designer — currently assigned to them, or
     * self-declined away from them — never the full BD user list.
     */
    public function relatedBds(User $designer): SupportCollection
    {
        return User::query()
            ->where('role', 'bd')
            ->where('is_active', true)
            ->where(function ($query) use ($designer) {
                $query->whereHas('createdTasks', fn ($q) => $q->where('designer_id', $designer->id))
                    ->orWhereHas('createdTasks', function ($q) use ($designer) {
                        $q->whereHas('requests', function ($requestQuery) use ($designer) {
                            $requestQuery->where('request_type', 'decline')
                                ->where('requested_by', $designer->id)
                                ->where('overall_status', 'approved');
                        });
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
