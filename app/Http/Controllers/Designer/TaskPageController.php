<?php

namespace App\Http\Controllers\Designer;

use App\Http\Controllers\Controller;
use App\Models\DesignTask;
use App\Models\DesignTaskRequest;
use Illuminate\Http\Request;

class TaskPageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'designer', 403);

        return view('designer.tasks.index');
    }

    public function show(Request $request, DesignTask $task)
    {
        $user = $request->user();

        abort_unless($user?->role === 'designer', 403);

        $isCurrentDesigner = (int) $task->designer_id === (int) $user->id;

        $requirements = $task->requirements ?? [];

        $swapRequestId = (int) ($requirements['_swap_request_id'] ?? 0);
        $swapOriginalDesignerId = (int) ($requirements['_swap_original_designer_id'] ?? 0);
        $swapPreviousDesignerId = (int) ($requirements['_swap_previous_designer_id'] ?? 0);

        $isApprovedSwapInitiator = false;

        // New swap-shadow metadata.
        if (
            $swapOriginalDesignerId === (int) $user->id
            || $swapPreviousDesignerId === (int) $user->id
        ) {
            $isApprovedSwapInitiator = true;
        }

        // Approved swap request referenced through requirements metadata.
        if (! $isApprovedSwapInitiator && $swapRequestId > 0) {
            $isApprovedSwapInitiator = DesignTaskRequest::query()
                ->whereKey($swapRequestId)
                ->where('request_type', 'swap')
                ->where('requested_by', $user->id)
                ->where('overall_status', 'approved')
                ->exists();
        }

        // Backward compatibility for swap records created before shadow metadata.
        if (! $isApprovedSwapInitiator) {
            $isApprovedSwapInitiator = DesignTaskRequest::query()
                ->where('design_task_id', $task->id)
                ->where('request_type', 'swap')
                ->where('requested_by', $user->id)
                ->where('overall_status', 'approved')
                ->exists();
        }

        $isSelfDeclinedViewer = ! $isCurrentDesigner && DesignTaskRequest::query()
            ->where('design_task_id', $task->id)
            ->where('request_type', 'decline')
            ->where('requested_by', $user->id)
            ->where('overall_status', 'approved')
            ->exists();

        abort_unless($isCurrentDesigner || $isApprovedSwapInitiator || $isSelfDeclinedViewer, 403);

        return view('designer.tasks.show', compact('task'));
    }
}
