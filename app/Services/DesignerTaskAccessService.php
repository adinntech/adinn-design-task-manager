<?php

namespace App\Services;

use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use App\Models\User;

/**
 * Who may open a task as a Designer and whether it is read-only for them.
 * Same rules as Designer\TaskPageController::show() / Livewire\Designer\TaskDetail::mount(),
 * kept here so the mobile API does not depend on a Livewire component.
 */
class DesignerTaskAccessService
{
    /**
     * @return array{current:bool,read_only:bool,reason:?string}|null null = no access
     */
    public function resolve(User $user, DesignTask $task): ?array
    {
        if ($user->role !== 'designer') {
            return null;
        }

        $current = (int) $task->designer_id === (int) $user->id;
        $requirements = $task->requirements ?? [];
        $shadow = (bool) data_get($requirements, '_swap_shadow', false);

        $swapInitiator = (int) ($requirements['_swap_original_designer_id'] ?? 0) === (int) $user->id
            || (int) ($requirements['_swap_previous_designer_id'] ?? 0) === (int) $user->id;

        if (! $swapInitiator) {
            $swapRequestId = (int) ($requirements['_swap_request_id'] ?? 0);
            $query = DesignTaskRequest::query()
                ->where('request_type', 'swap')
                ->where('requested_by', $user->id)
                ->where('overall_status', 'approved');

            $swapInitiator = $swapRequestId > 0
                ? (clone $query)->whereKey($swapRequestId)->exists() || (clone $query)->where('design_task_id', $task->id)->exists()
                : $query->where('design_task_id', $task->id)->exists();
        }

        $selfDeclined = ! $current && DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->where('request_type', 'decline')
            ->where('requested_by', $user->id)
            ->where('overall_status', 'approved')
            ->exists();

        $splitRequester = ! $current && DesignTaskRequest::query()
            ->where('request_type', 'split')
            ->where('overall_status', 'approved')
            ->where('requested_by', $user->id)
            ->where('split_details->created_task_id', $task->id)
            ->exists();

        if (! ($current || $swapInitiator || $selfDeclined || $splitRequester)) {
            return null;
        }

        $reason = null;
        if ($swapInitiator && (! $current || $shadow)) {
            $reason = 'swapped';
        } elseif ($selfDeclined) {
            $reason = 'declined';
        } elseif ($splitRequester) {
            $reason = 'split';
        }

        // Full interaction needs ownership and not being a swap-shadow viewer.
        $canInteract = $current && ! $shadow && ! $swapInitiator;

        return ['current' => $current, 'read_only' => ! $canInteract, 'reason' => $reason];
    }
}
