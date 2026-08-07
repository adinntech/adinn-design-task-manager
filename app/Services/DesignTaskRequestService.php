<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;
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
            return DesignTaskRequest::create([
                'design_task_id' => $task->id,
                'request_type' => $type,
                'requested_by' => $user->id,
                'overall_status' => 'pending_designer_head',
                'designer_head_status' => 'pending',
                'admin_status' => 'pending',
                'reason' => $reason,
                'target_designer_id' => $data['target_designer_id'] ?? null,
                'split_details' => $data['split_details'] ?? null,
                'attachments' => $data['attachments'] ?? null,
            ]);
        });
    }

    public function approve(DesignTaskRequest $request, User $head): DesignTaskRequest
    {
        $this->guardPending($request);

        return DB::transaction(function () use ($request, $head) {
            if ($request->request_type === 'swap') {
                $this->executeSwap($request);
            }

            if ($request->request_type === 'split') {
                $this->executeSplit($request);
            }

            $request->update([
                'designer_head_status' => 'approved',
                'designer_head_action_by' => $head->id,
                'designer_head_action_at' => now(),
                'overall_status' => 'approved',
            ]);

            return $request->fresh();
        });
    }

    public function reject(DesignTaskRequest $request, User $head): DesignTaskRequest
    {
        $this->guardPending($request);

        $request->update([
            'designer_head_status' => 'rejected',
            'designer_head_action_by' => $head->id,
            'designer_head_action_at' => now(),
            'overall_status' => 'rejected',
        ]);

        return $request->fresh();
    }

    private function guardPending(DesignTaskRequest $request): void
    {
        if ($request->overall_status !== 'pending_designer_head') {
            throw ValidationException::withMessages([
                'status' => 'This request has already been decided.',
            ]);
        }

        if ($request->request_type === 'swap' && ! $request->target_designer_id) {
            throw ValidationException::withMessages([
                'status' => 'This swap request has no target designer to reassign to.',
            ]);
        }
    }

    private function executeSwap(DesignTaskRequest $request): void
    {
        $request->task->update(['designer_id' => $request->target_designer_id]);
    }

    private function executeSplit(DesignTaskRequest $request): DesignTask
    {
        $original = $request->task;
        $details = $request->split_details ?? [];

        $task = DesignTask::create([
            'task_id' => 'PENDING-'.Str::uuid(),
            'assigned_at' => now(),
            'assigned_by' => $original->assigned_by,
            'task_name' => $original->task_name.' (Split)',
            'vertical' => $original->vertical,
            'task_nature' => $original->task_nature,
            'party_type' => $original->party_type,
            'party_name' => $original->party_name,
            'contact_person' => $original->contact_person,
            'mobile_number' => $original->mobile_number,
            'priority' => $original->priority,
            'due_at' => $original->due_at,
            'designer_id' => $request->target_designer_id ?: $original->designer_id,
            'total_creatives' => max(1, (int) ($details['creative_count'] ?? 1)),
            'status' => 'assigned_tasks',
            'requirements' => $original->requirements,
        ]);

        $task->update([
            'task_id' => sprintf('DT-%s-%05d', now()->format('Y'), $task->id),
        ]);

        $request->update(['split_details' => [...$details, 'created_task_id' => $task->id]]);

        return $task->fresh();
    }
}
