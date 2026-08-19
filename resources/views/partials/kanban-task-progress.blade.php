@if(in_array($task->status, ['in_progress', 'waiting_confirmation', 'rework'], true))
    @php
        $cardProgressService = app(\App\Services\DesignTaskProgressService::class);
        $cardCompleted = $cardProgressService->completed($task);
        $cardPercentage = $cardProgressService->percentage($task);
        $cardRemaining = $cardProgressService->remaining($task);
        $cardProgressColor = match (true) {
            $cardPercentage >= 100 => '#16a34a',
            $cardPercentage >= 75 => '#7c3aed',
            $cardPercentage >= 50 => '#2563eb',
            $cardPercentage >= 25 => '#f59e0b',
            default => '#94a3b8',
        };
        $cardTint = match ($task->status) {
            'rework' => '#fff7f7',
            'waiting_confirmation' => '#f5f3ff',
            default => '#f8fafc',
        };
    @endphp
    <div class="kanban-progress" style="margin-top:10px;padding:9px 10px;border:1px solid #e5e7eb;border-radius:10px;background:{{ $cardTint }}">
        <div class="kanban-progress-head" style="display:flex;justify-content:space-between;gap:8px;align-items:center">
            <span style="font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:#667085">Creative Progress</span>
            <strong style="font-size:9px;font-weight:950;color:{{ $cardProgressColor }}">{{ $cardCompleted }} / {{ $task->total_creatives }} · {{ $cardPercentage }}%</strong>
        </div>
        <div class="kanban-progress-track" style="height:7px;background:#e9edf3;border-radius:999px;overflow:hidden;margin-top:6px">
            <div class="kanban-progress-fill" style="height:100%;width:{{ $cardPercentage }}%;background:{{ $cardProgressColor }};border-radius:999px;transition:width .25s ease"></div>
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px;margin-top:5px;font-size:7px;font-weight:800;color:#98a2b3">
            <span>{{ $task->status === 'rework' ? 'Rework active' : ($task->status === 'waiting_confirmation' ? 'Awaiting BD' : 'In progress') }}</span>
            <span>{{ $cardRemaining }} remaining</span>
        </div>
    </div>
@endif
