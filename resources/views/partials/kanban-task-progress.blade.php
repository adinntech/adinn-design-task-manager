@if(in_array($task->status, ['in_progress', 'waiting_confirmation', 'rework'], true))
    @php
        $cardProgressService = app(\App\Services\DesignTaskProgressService::class);
        $cardCompleted = $cardProgressService->completed($task);
        $cardPercentage = $cardProgressService->percentage($task);
        $cardRemaining = $cardProgressService->remaining($task);
        $cardTint = match ($task->status) {
            'rework' => '#fff7f7',
            'waiting_confirmation' => '#f5f3ff',
            default => '#f8fafc',
        };
    @endphp
    <div class="kanban-progress" data-completed="{{ $cardCompleted }}" data-total="{{ $task->total_creatives }}" data-remaining="{{ $cardRemaining }}" style="margin-top:10px;padding:9px 10px;border:1px solid #e5e7eb;border-radius:10px;background:{{ $cardTint }}">
        <div class="kanban-progress-head" style="display:flex;justify-content:space-between;gap:8px;align-items:center">
            <span style="font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#667085">Creative Progress</span>
            <strong style="font-size:11px;font-weight:950">{{ $cardPercentage }}%</strong>
        </div>
        <div class="kanban-progress-track" style="height:7px;background:#e9edf3;border-radius:999px;overflow:hidden;margin-top:6px">
            <x-progress-fill :percentage="$cardPercentage" />
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;margin-top:5px;font-size:10px;font-weight:800;color:#98a2b3">
            <span>{{ $task->status === 'rework' ? 'Rework active' : ($task->status === 'waiting_confirmation' ? 'Awaiting BD' : 'In progress') }}</span>
        </div>
    </div>
@endif
