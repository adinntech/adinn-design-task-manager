<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\DesignTaskStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DesignTaskRequestService
{
    public const TYPES = ['decline', 'split', 'swap'];

    private const REQUESTABLE_TYPES = [
        'review_analysis' => ['decline', 'split', 'swap'],
        'need_clarification' => ['decline', 'split', 'swap'],
        'yet_to_start' => ['split', 'swap'],
        'in_progress' => ['split', 'swap'],
    ];

    public function allowedTypes(string $status): array
    {
        return self::REQUESTABLE_TYPES[$status] ?? [];
    }

    public function create(DesignTask $task, User $user, string $type, string $reason, array $data = []): DesignTaskRequest
    {
        return DB::transaction(function () use ($task, $user, $type, $reason, $data) {
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($task->id);
            $this->guardRequestCreation($lockedTask, $user, $type, $reason, $data);

            $request = DesignTaskRequest::create([
                'design_task_id' => $lockedTask->id,
                'request_type' => $type,
                'requested_by' => $user->id,
                'overall_status' => 'pending_approval',
                'designer_head_status' => 'pending',
                'admin_status' => 'pending',
                'reason' => trim($reason),
                'target_designer_id' => $data['target_designer_id'] ?? null,
                'split_details' => $data['split_details'] ?? null,
                'attachments' => $data['attachments'] ?? null,
            ]);

            $preferredDesigner = ! empty($data['target_designer_id'])
                ? User::query()->whereKey($data['target_designer_id'])->value('name')
                : null;

            $requestSummary = match ($type) {
                'split' => 'Split request created for '.data_get($data, 'split_details.creative_count', '—').' creatives'
                    .($preferredDesigner ? '; preferred Designer: '.$preferredDesigner : '; no preferred Designer')
                    .'. Reason: '.trim($reason),
                'swap' => 'Swap request created; preferred Designer: '.($preferredDesigner ?: 'Not specified')
                    .'. Reason: '.trim($reason),
                'decline' => 'Decline request created. Reason: '.trim($reason),
                default => ucfirst($type).' request created.',
            };

            $this->recordHistory(
                $lockedTask,
                $user,
                'request_created',
                $requestSummary.' Status: Pending Approval.'
            );

            return $request->fresh();
        });
    }

    /**
     * Either Admin OR Designer Head can make the final decision.
     * The first valid decision finalizes the request.
     */
    public function approve(DesignTaskRequest $request, User $approver, ?int $approvedDesignerId = null): DesignTaskRequest
    {
        $this->guardApprover($approver);

        return DB::transaction(function () use ($request, $approver, $approvedDesignerId) {
            $lockedRequest = DesignTaskRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardPending($lockedRequest);

            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($lockedRequest->design_task_id);
            $lockedRequest->setRelation('task', $lockedTask);

            if (in_array($lockedRequest->request_type, ['split', 'swap'], true)) {
                $approvedDesigner = $this->resolveApprovedDesigner($lockedTask, $approvedDesignerId);
                $lockedRequest->approved_designer_id = $approvedDesigner->id;
                $lockedRequest->save();
                $lockedRequest->setRelation('approvedDesigner', $approvedDesigner);
            }

            $executionNote = match ($lockedRequest->request_type) {
                'swap' => $this->executeSwap($lockedRequest),
                'split' => $this->executeSplit($lockedRequest),
                'decline' => 'Decline approved. Task remains assigned until it is explicitly reassigned.',
                default => throw ValidationException::withMessages(['request' => 'Unsupported request type.']),
            };

            $audit = $this->decisionAuditFields($approver, 'approved');

            $lockedRequest->update(array_merge($audit, [
                'overall_status' => 'approved',
            ]));

            $this->recordHistory(
                $lockedTask->fresh(),
                $approver,
                'request_approved',
                ucfirst($lockedRequest->request_type).' request approved by '.ucwords(str_replace('_', ' ', $approver->role)).'. '.$executionNote
            );

            return $lockedRequest->fresh();
        });
    }

    public function reject(DesignTaskRequest $request, User $approver): DesignTaskRequest
    {
        $this->guardApprover($approver);

        return DB::transaction(function () use ($request, $approver) {
            $lockedRequest = DesignTaskRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardPending($lockedRequest);

            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($lockedRequest->design_task_id);
            $lockedRequest->setRelation('task', $lockedTask);

            $audit = $this->decisionAuditFields($approver, 'rejected');

            $lockedRequest->update(array_merge($audit, [
                'overall_status' => 'rejected',
            ]));

            $this->recordHistory(
                $lockedTask,
                $approver,
                'request_rejected',
                ucfirst($lockedRequest->request_type).' request rejected by '.ucwords(str_replace('_', ' ', $approver->role)).'.'
            );

            return $lockedRequest->fresh();
        });
    }

    private function guardRequestCreation(DesignTask $task, User $user, string $type, string $reason, array $data): void
    {
        if ($user->role !== 'designer' || (int) $task->designer_id !== (int) $user->id) {
            throw new AuthorizationException('You are not allowed to raise a request for this task.');
        }

        if (! in_array($type, self::TYPES, true) || ! in_array($type, $this->allowedTypes($task->status), true)) {
            throw ValidationException::withMessages([
                'type' => 'This request type is not available for the task\'s current status.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        $hasPendingSameType = DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->where('request_type', $type)
            ->whereIn('overall_status', ['pending_approval', 'pending_designer_head', 'pending_admin'])
            ->exists();

        if ($hasPendingSameType) {
            throw ValidationException::withMessages([
                'type' => 'A pending '.ucfirst($type).' request already exists for this task.',
            ]);
        }

        $targetDesignerId = $data['target_designer_id'] ?? null;

        if ($type === 'swap' && ! $targetDesignerId) {
            throw ValidationException::withMessages(['targetDesignerId' => 'Please select a designer to swap with.']);
        }

        if ($targetDesignerId) {
            $target = User::query()->find($targetDesignerId);

            if (! $target || $target->role !== 'designer' || ! $target->is_active) {
                throw ValidationException::withMessages(['targetDesignerId' => 'Please select an active Designer.']);
            }

            if ((int) $target->id === (int) $task->designer_id) {
                throw ValidationException::withMessages(['targetDesignerId' => 'Please select a different Designer.']);
            }
        }

        if ($type === 'split') {
            $count = (int) data_get($data, 'split_details.creative_count', 0);

            if ($task->total_creatives < 2) {
                throw ValidationException::withMessages([
                    'creativeCount' => 'This task cannot be split because it has fewer than 2 creatives.',
                ]);
            }

            if ($count < 1 || $count >= $task->total_creatives) {
                throw ValidationException::withMessages([
                    'creativeCount' => 'Creative count to split must be at least 1 and less than the task total of '.$task->total_creatives.'.',
                ]);
            }
        }
    }

    private function guardApprover(User $approver): void
    {
        if (! in_array($approver->role, ['admin', 'designer_head'], true)) {
            throw new AuthorizationException('Only Admin or Designer Head can decide Designer requests.');
        }
    }

    private function guardPending(DesignTaskRequest $request): void
    {
        if (! in_array($request->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This request has already been decided.',
            ]);
        }

    }

    private function decisionAuditFields(User $approver, string $decision): array
    {
        if ($approver->role === 'admin') {
            return [
                'admin_status' => $decision,
                'admin_action_by' => $approver->id,
                'admin_action_at' => now(),
                'designer_head_status' => 'not_required',
            ];
        }

        return [
            'designer_head_status' => $decision,
            'designer_head_action_by' => $approver->id,
            'designer_head_action_at' => now(),
            'admin_status' => 'not_required',
        ];
    }

    private function resolveApprovedDesigner(DesignTask $task, ?int $approvedDesignerId): User
    {
        if (! $approvedDesignerId) {
            throw ValidationException::withMessages([
                'approved_designer_id' => 'Please select the Designer who should receive this request.',
            ]);
        }

        $designer = User::query()
            ->whereKey($approvedDesignerId)
            ->where('role', 'designer')
            ->where('is_active', true)
            ->first();

        if (! $designer) {
            throw ValidationException::withMessages([
                'approved_designer_id' => 'Please select an active Designer.',
            ]);
        }

        if ((int) $designer->id === (int) $task->designer_id) {
            throw ValidationException::withMessages([
                'approved_designer_id' => 'Please select a different Designer from the current assignee.',
            ]);
        }

        return $designer;
    }

    private function executeSwap(DesignTaskRequest $request): string
    {
        $task = $request->task;
        $oldDesigner = User::query()->find($task->designer_id);
        $newDesigner = $request->approvedDesigner ?: User::query()->findOrFail($request->approved_designer_id);
        $task->update(['designer_id' => $newDesigner->id]);

        return 'Designer reassigned from '.($oldDesigner?->name ?? 'previous Designer').' to '.$newDesigner->name.'.';
    }

    private function executeSplit(DesignTaskRequest $request): string
    {
        $original = $request->task;
        $details = $request->split_details ?? [];
        $splitCount = (int) ($details['creative_count'] ?? 0);

        if ($splitCount < 1 || $splitCount >= $original->total_creatives) {
            throw ValidationException::withMessages([
                'status' => 'The split creative count is no longer valid for this task.',
            ]);
        }

        $remainingCount = $original->total_creatives - $splitCount;

        $task = DesignTask::create([
            'task_id' => 'PENDING-'.Str::uuid(),
            'assigned_at' => now(),
            'assigned_by' => $original->assigned_by,
            'task_name' => $original->display_task_name,
            'vertical' => $original->vertical,
            'task_nature' => $original->task_nature,
            'party_type' => $original->party_type,
            'party_name' => $original->party_name,
            'contact_person' => $original->contact_person,
            'mobile_number' => $original->mobile_number,
            'priority' => $original->priority,
            'due_at' => $original->due_at,
            'designer_id' => $request->approved_designer_id,
            'total_creatives' => $splitCount,
            'status' => 'assigned_tasks',
            'requirements' => array_merge($original->requirements ?? [], [
                '_split_from_task_id' => $original->task_id,
                '_split_request_id' => $request->id,
            ]),
        ]);

        $task->update([
            'task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id),
        ]);

        $original->update(['total_creatives' => $remainingCount]);

        $request->update([
            'split_details' => array_merge($details, [
                'created_task_id' => $task->id,
                'created_task_code' => $task->task_id,
                'original_remaining_creatives' => $remainingCount,
            ]),
        ]);

        return 'Split task '.$task->task_id.' created with '.$splitCount.' creatives and assigned to '.($approvedDesigner?->name ?? 'Designer').'; original task now has '.$remainingCount.'.';
    }

    private function recordHistory(DesignTask $task, User $user, string $source, string $note): void
    {
        DesignTaskStatusHistory::create([
            'design_task_id' => $task->id,
            'from_status' => $task->status,
            'to_status' => $task->status,
            'changed_by' => $user->id,
            'change_source' => $source,
            'note' => $note,
        ]);
    }
}
