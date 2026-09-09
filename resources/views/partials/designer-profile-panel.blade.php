{{-- Read-only Designer profile snapshot for Ticket Overview. Expects $task. --}}
@if($task->designer)
    @php
        $designerVerticalLabels = collect($task->designer->experienced_verticals ?? [])
            ->map(fn ($v) => \App\Http\Controllers\Bd\TaskController::VERTICALS[$v] ?? $v);
        $designerSkillList = $task->designer->skills ?? [];
    @endphp
    <details class="collapse-panel"><summary>Designer Information</summary><div class="collapse-body"><div class="info-grid">
        <div class="info-item"><span>Name</span><strong>{{ $task->designer->name }}</strong></div>
        <div class="info-item"><span>Experienced Verticals</span><strong>{{ $designerVerticalLabels->isNotEmpty() ? $designerVerticalLabels->implode(', ') : '—' }}</strong></div>
        <div class="info-item"><span>Skills</span><strong>{{ !empty($designerSkillList) ? implode(', ', $designerSkillList) : '—' }}</strong></div>
    </div></div></details>
@endif
