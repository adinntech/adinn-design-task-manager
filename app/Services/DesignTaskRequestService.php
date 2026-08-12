<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskEodRecord;
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
        'assigned_tasks' => ['decline'],
        'review_analysis' => ['split', 'swap'],
        'need_clarification' => ['split', 'swap'],
        'yet_to_start' => ['split', 'swap'],
        'in_progress' => ['split', 'swap'],
    ];

    public function allowedTypes(string $status): array
    {
        return self::REQUESTABLE_TYPES[$status] ?? [];
    }

    public function allowedTypesForTask(DesignTask $task): array
    {
        $allowed = $this->allowedTypes($task->status);

        if (! empty(data_get($task->requirements, '_swapped_from_task_id'))) {
            $allowed = array_values(array_diff($allowed, ['swap']));
        }

        if (! empty(data_get($task->requirements, '_split_request_id')) || ! empty(data_get($task->requirements, '_split_from_task_id'))) {
            $allowed = array_values(array_diff($allowed, ['split']));
        }

        $approvedTypes = DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->whereIn('request_type', ['split', 'swap'])
            ->where('overall_status', 'approved')
            ->pluck('request_type')
            ->all();

        return array_values(array_filter(
            $allowed,
            fn (string $type) => ! in_array($type, $approvedTypes, true)
        ));
    }

    public function create(DesignTask $task, User $user, string $type, string $reason, array $data = []): DesignTaskRequest
    {
        return DB::transaction(function () use ($task, $user, $type, $reason, $data) {
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($task->id);
            $this->guardRequestCreation($lockedTask, $user, $type, $reason, $data);

            $requestData = $data;

            if ($type === 'swap') {
                $swapDetails = $requestData['split_details'] ?? [];
                $swapDetails['previous_status'] = $lockedTask->status;
                $requestData['split_details'] = $swapDetails;
            }

            $request = DesignTaskRequest::create([
                'design_task_id' => $lockedTask->id,
                'request_type' => $type,
                'requested_by' => $user->id,
                'overall_status' => 'pending_approval',
                'designer_head_status' => 'pending',
                'admin_status' => 'pending',
                'reason' => trim($reason),
                'decision_reason' => null,
                'target_designer_id' => $requestData['target_designer_id'] ?? null,
                'split_details' => $requestData['split_details'] ?? null,
                'attachments' => $requestData['attachments'] ?? null,
            ]);

            $preferredDesigner = ! empty($data['target_designer_id'])
                ? User::query()->whereKey($data['target_designer_id'])->value('name')
                : null;

            $summary = match ($type) {
                'split' => 'Split request created for '.data_get($data, 'split_details.creative_count', '—').' creatives'.($preferredDesigner ? '; preferred Designer: '.$preferredDesigner : '; no preferred Designer').'. Reason: '.trim($reason),
                'swap' => 'Swap request created; preferred Designer: '.($preferredDesigner ?: 'Not specified').'. Reason: '.trim($reason),
                'decline' => 'Decline request created. Reason: '.trim($reason),
                default => ucfirst($type).' request created.',
            };

            $this->recordHistory($lockedTask, $user, 'request_created', $summary.' Status: Pending Approval.');

            if ($type === 'swap') {
                $previousStatus = $lockedTask->status;
                $lockedTask->update(['status' => 'swap_tasks']);

                $this->recordStatusMovement(
                    $lockedTask,
                    $user,
                    $previousStatus,
                    'swap_tasks',
                    'swap_requested',
                    'Swap request initiated. Task moved to Swap Tasks and is waiting for approval.'
                );
            }

            return $request->fresh();
        });
    }

    public function approve(DesignTaskRequest $request, User $approver, ?int $approvedDesignerId = null, ?int $approvedSplitCount = null): DesignTaskRequest
    {
        $this->guardApprover($approver);

        return DB::transaction(function () use ($request, $approver, $approvedDesignerId, $approvedSplitCount) {
            $lockedRequest = DesignTaskRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardPending($lockedRequest);

            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($lockedRequest->design_task_id);
            $lockedRequest->setRelation('task', $lockedTask);

            // Race-safe one-successful-Split and one-successful-Swap rule.
            if (in_array($lockedRequest->request_type, ['split', 'swap'], true)) {
                $alreadyApproved = DesignTaskRequest::query()
                    ->where('design_task_id', $lockedTask->id)
                    ->where('request_type', $lockedRequest->request_type)
                    ->where('overall_status', 'approved')
                    ->where('id', '<>', $lockedRequest->id)
                    ->exists();

                if ($alreadyApproved) {
                    throw ValidationException::withMessages([
                        'status' => 'This task already has an approved '.ucfirst($lockedRequest->request_type).' request.',
                    ]);
                }

                $approvedDesigner = $this->resolveApprovedDesigner($lockedTask, $approvedDesignerId);
                $lockedRequest->approved_designer_id = $approvedDesigner->id;
                $lockedRequest->save();
                $lockedRequest->setRelation('approvedDesigner', $approvedDesigner);
            }

            if ($lockedRequest->request_type === 'split') {
                $this->validateApprovedSplitCount($lockedTask, $approvedSplitCount);
                $details = $lockedRequest->split_details ?? [];
                $details['approved_creative_count'] = $approvedSplitCount;
                $lockedRequest->split_details = $details;
                $lockedRequest->save();
            }

            $executionNote = match ($lockedRequest->request_type) {
                'swap' => $this->executeSwap($lockedRequest),
                'split' => $this->executeSplit($lockedRequest),
                'decline' => 'Decline approved. Task remains assigned until it is explicitly reassigned.',
                default => throw ValidationException::withMessages(['request' => 'Unsupported request type.']),
            };

            $lockedRequest->update(array_merge($this->decisionAuditFields($approver, 'approved'), [
                'overall_status' => 'approved',
                'decision_reason' => null,
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

    public function reject(DesignTaskRequest $request, User $approver, string $reason): DesignTaskRequest
    {
        $this->guardApprover($approver);
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['decision_reason' => 'A decline/rejection reason is required.']);
        }

        return DB::transaction(function () use ($request, $approver, $reason) {
            $lockedRequest = DesignTaskRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->guardPending($lockedRequest);
            $lockedTask = DesignTask::query()->lockForUpdate()->findOrFail($lockedRequest->design_task_id);

            $lockedRequest->update(array_merge($this->decisionAuditFields($approver, 'rejected'), [
                'overall_status' => 'rejected',
                'decision_reason' => $reason,
            ]));

            if ($lockedRequest->request_type === 'swap') {
                $details = $lockedRequest->split_details ?? [];
                $previousStatus = $details['previous_status'] ?? 'review_analysis';
                $fromStatus = $lockedTask->status;

                $lockedTask->update(['status' => $previousStatus]);

                $this->recordStatusMovement(
                    $lockedTask,
                    $approver,
                    $fromStatus,
                    $previousStatus,
                    'swap_rejected',
                    'Swap request declined. Task returned to '.ucwords(str_replace('_', ' ', $previousStatus)).'. Reason: '.$reason
                );
            } else {
                $this->recordHistory(
                    $lockedTask,
                    $approver,
                    'request_rejected',
                    ucfirst($lockedRequest->request_type).' request declined by '.ucwords(str_replace('_', ' ', $approver->role)).'. Reason: '.$reason
                );
            }

            return $lockedRequest->fresh();
        });
    }

    private function guardRequestCreation(DesignTask $task, User $user, string $type, string $reason, array $data): void
    {
        if ($user->role !== 'designer' || (int) $task->designer_id !== (int) $user->id) {
            throw new AuthorizationException('You are not allowed to raise a request for this task.');
        }

        if (! in_array($type, self::TYPES, true) || ! in_array($type, $this->allowedTypes($task->status), true)) {
            throw ValidationException::withMessages(['type' => 'This request type is not available for the task\'s current status.']);
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
            throw ValidationException::withMessages(['type' => 'A pending '.ucfirst($type).' request already exists for this task.']);
        }

        if (in_array($type, ['split', 'swap'], true)) {
            $alreadyApproved = DesignTaskRequest::query()
                ->where('design_task_id', $task->id)
                ->where('request_type', $type)
                ->where('overall_status', 'approved')
                ->exists();

            if ($alreadyApproved) {
                throw ValidationException::withMessages(['type' => 'This task has already used its one approved '.ucfirst($type).' operation.']);
            }
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
                throw ValidationException::withMessages(['creativeCount' => 'This task cannot be split because it has fewer than 2 creatives.']);
            }
            if ($count < 1 || $count >= $task->total_creatives) {
                throw ValidationException::withMessages(['creativeCount' => 'Creative count to split must be at least 1 and less than the task total of '.$task->total_creatives.'.']);
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
            throw ValidationException::withMessages(['status' => 'This request has already been decided.']);
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
            throw ValidationException::withMessages(['approved_designer_id' => 'Please select the Designer who should receive this request.']);
        }
        $designer = User::query()->whereKey($approvedDesignerId)->where('role', 'designer')->where('is_active', true)->first();
        if (! $designer) {
            throw ValidationException::withMessages(['approved_designer_id' => 'Please select an active Designer.']);
        }
        if ((int) $designer->id === (int) $task->designer_id) {
            throw ValidationException::withMessages(['approved_designer_id' => 'Please select a different Designer from the current assignee.']);
        }
        return $designer;
    }

    private function validateApprovedSplitCount(DesignTask $task, ?int $count): void
    {
        if (! $count || $count < 1 || $count >= (int) $task->total_creatives) {
            throw ValidationException::withMessages([
                'approved_creative_count' => 'Approved split quantity must be at least 1 and must leave at least 1 creative with the original task.',
            ]);
        }
    }

    private function executeSwap(DesignTaskRequest $request): string
    {
        $originalTask = $request->task;
        $oldDesigner = User::query()->find($originalTask->designer_id);
        $newDesigner = $request->approvedDesigner
            ?: User::query()->findOrFail($request->approved_designer_id);

        $originalRequirements = $originalTask->requirements ?? [];

        // The original task becomes the read-only Swap Tasks record for the Designer
        // who initiated the swap.
        $originalRequirements['_swap_shadow'] = true;
        $originalRequirements['_swap_request_id'] = $request->id;
        $originalRequirements['_swap_original_designer_id'] = $oldDesigner?->id;
        $originalRequirements['_swap_approved_designer_id'] = $newDesigner->id;

        $originalTask->update([
            'status' => 'swap_tasks',
            'requirements' => $originalRequirements,
        ]);

        // Create a separate active task for the approved Designer.
        // This lets Designer B receive it under Assigned Tasks while Designer A
        // keeps the original read-only Swap Tasks record.
        $activeRequirements = $originalRequirements;
        unset($activeRequirements['_swap_shadow']);
        $activeRequirements['_swapped_from_task_id'] = $originalTask->task_id;
        $activeRequirements['_swap_request_id'] = $request->id;
        $activeRequirements['_swap_previous_designer_id'] = $oldDesigner?->id;

        $activeTask = DesignTask::create([
            'task_id' => 'PENDING-'.Str::uuid(),
            'assigned_at' => now(),
            'assigned_by' => $originalTask->assigned_by,
            'task_name' => $originalTask->display_task_name ?? $originalTask->task_name,
            'vertical' => $originalTask->vertical,
            'task_nature' => $originalTask->task_nature,
            'party_type' => $originalTask->party_type,
            'party_name' => $originalTask->party_name,
            'contact_person' => $originalTask->contact_person,
            'mobile_number' => $originalTask->mobile_number,
            'priority' => $originalTask->priority,
            'due_at' => $originalTask->due_at,
            'designer_id' => $newDesigner->id,
            'total_creatives' => $originalTask->total_creatives,
            'status' => 'assigned_tasks',
            'requirements' => $activeRequirements,
        ]);

        $activeTask->update([
            'task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $activeTask->id),
        ]);

        // Carry existing Task Updation progress into the logical active task so
        // the approved swap does not reset completed creative progress.
        DesignTaskEodRecord::query()
            ->where('design_task_id', $originalTask->id)
            ->orderBy('id')
            ->get()
            ->each(function (DesignTaskEodRecord $record) use ($activeTask): void {
                DesignTaskEodRecord::create([
                    'design_task_id' => $activeTask->id,
                    'designer_id' => $record->designer_id,
                    'update_type' => $record->update_type ?? 'progress',
                    'completed_count' => $record->completed_count,
                    'total_creatives_snapshot' => $record->total_creatives_snapshot,
                    'cumulative_completed' => $record->cumulative_completed,
                    'remaining_creatives' => $record->remaining_creatives,
                    'rework_count_snapshot' => $record->rework_count_snapshot,
                    'attachment_disk' => $record->attachment_disk,
                    'attachment_path' => $record->attachment_path,
                    'attachment_original_name' => $record->attachment_original_name,
                    'submitted_at' => $record->submitted_at,
                ]);
            });

        $originalRequirements['_swap_active_task_id'] = $activeTask->id;
        $originalRequirements['_swap_active_task_code'] = $activeTask->task_id;
        $originalTask->update(['requirements' => $originalRequirements]);

        $details = $request->split_details ?? [];
        $details['active_task_id'] = $activeTask->id;
        $details['active_task_code'] = $activeTask->task_id;
        $details['previous_designer_id'] = $oldDesigner?->id;
        $request->update(['split_details' => $details]);

        $this->recordHistory(
            $activeTask,
            $newDesigner,
            'task_assigned',
            'Swapped task assigned to '.$newDesigner->name.'.'
        );

        return 'Swap approved. Original task stays in Swap Tasks for '
            .($oldDesigner?->name ?? 'previous Designer')
            .'; active task '.$activeTask->task_id
            .' assigned to '.$newDesigner->name.' under Assigned Tasks.';
    }

    private function executeSplit(DesignTaskRequest $request): string
    {
        $original = $request->task;
        $details = $request->split_details ?? [];
        $requestedCount = (int) ($details['creative_count'] ?? 0);
        $splitCount = (int) ($details['approved_creative_count'] ?? 0);
        $this->validateApprovedSplitCount($original, $splitCount);
        $remainingCount = $original->total_creatives - $splitCount;
        $approvedDesigner = $request->approvedDesigner ?: User::query()->findOrFail($request->approved_designer_id);

        $cleanName = trim((string) preg_replace('/\s*\((?:split|swap|swapped)\)\s*$/i', '', (string) $original->task_name));
        $task = DesignTask::create([
            'task_id' => 'PENDING-'.Str::uuid(),
            'assigned_at' => now(),
            'assigned_by' => $original->assigned_by,
            'task_name' => $cleanName,
            'vertical' => $original->vertical,
            'task_nature' => $original->task_nature,
            'party_type' => $original->party_type,
            'party_name' => $original->party_name,
            'contact_person' => $original->contact_person,
            'mobile_number' => $original->mobile_number,
            'priority' => $original->priority,
            'due_at' => $original->due_at,
            'designer_id' => $approvedDesigner->id,
            'total_creatives' => $splitCount,
            'status' => 'assigned_tasks',
            'requirements' => array_merge($original->requirements ?? [], [
                '_split_from_task_id' => $original->task_id,
                '_split_request_id' => $request->id,
            ]),
        ]);
        $task->update(['task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id)]);
        $original->update(['total_creatives' => $remainingCount]);
        $request->update(['split_details' => array_merge($details, [
            'requested_creative_count' => $requestedCount,
            'approved_creative_count' => $splitCount,
            'created_task_id' => $task->id,
            'created_task_code' => $task->task_id,
            'original_remaining_creatives' => $remainingCount,
        ])]);

        return 'Split approved: requested '.$requestedCount.', approved '.$splitCount.' creatives. Split task '.$task->task_id.' assigned to '.$approvedDesigner->name.'; original task now has '.$remainingCount.'.';
    }

    private function recordStatusMovement(
        DesignTask $task,
        User $user,
        string $fromStatus,
        string $toStatus,
        string $source,
        string $note
    ): void {
        DesignTaskStatusHistory::create([
            'design_task_id' => $task->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $user->id,
            'change_source' => $source,
            'note' => $note,
        ]);
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
