@php
    // Lucide-style inline icons (this partial is also returned alone by the live-search endpoint).
    $ytdIc = [
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'loader' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
        'dot' => '<circle cx="12" cy="12" r="5" fill="currentColor"/>',
    ];
    $ytdSvg = fn (string $n) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ytdIc[$n].'</svg>';
@endphp
@forelse($taskRows as $row)
    @php
        $task = $row['task'];
        $rowRatingValue = $row['rating'] !== null ? max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($row['rating']))) : null;
        [$stLabel, $stIcon, $stTone] = $row['overdue']
            ? ['Overdue', 'alert', 'overdue']
            : match ($task->status) {
                'rework' => [ucwords(str_replace('_', ' ', $task->status)), 'refresh', 'rework'],
                'completed' => ['Completed', 'check', 'completed'],
                'waiting_confirmation' => [ucwords(str_replace('_', ' ', $task->status)), 'clock', 'waiting'],
                'in_progress' => [ucwords(str_replace('_', ' ', $task->status)), 'loader', 'progress'],
                'assigned_tasks' => ['Assigned Tasks', 'dot', 'assigned'],
                default => [ucwords(str_replace('_', ' ', $task->status)), 'dot', 'default'],
            };
        $pct = max(0, min(100, (int) $row['percentage']));
        $barColor = $pct >= 100 ? '#16a34a' : ($pct <= 0 ? '#d0d5dd' : '#ef4444');
    @endphp
    <tr>
        <td class="tov-id"><a href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
        <td>{{ $task->zoho_project_number ?: '-' }}</td>
        <td>{{ $task->display_task_name ?? $task->task_name }}</td>
        <td>@if($task->assigned_at)<span class="tov-by">{!! $ytdSvg('calendar') !!}{{ $task->assigned_at->format('d M Y') }}</span>@else — @endif</td>
        <td><span class="tov-pill tov-st-{{ $stTone }}">{!! $ytdSvg($stIcon) !!}{{ $stLabel }}</span></td>
        <td>
            <div class="ytd-pct">{{ $row['percentage'] }}%</div>
            <div class="ytd-bar"><i style="width:{{ $pct }}%;background:{{ $barColor }}"></i></div>
        </td>
        <td><div class="ytd-count">{{ $row['done'] }} / {{ $task->total_creatives }}</div><div class="ytd-sub">{{ $row['remaining'] }} remaining</div></td>
        <td class="tov-due{{ $row['overdue'] ? ' is-overdue' : '' }}"><span>@if($task->due_at){!! $ytdSvg('calendar') !!}{{ $task->due_at->format('d M Y · h:i A') }}@else — @endif</span></td>
        <td>@if($row['completed_at'])<span class="tov-by ytd-done">{!! $ytdSvg('calendar') !!}{{ $row['completed_at']->format('d M Y') }}</span>@else<span class="ytd-dash">—</span>@endif</td>
        <td>
            @if($row['completion']['status'] === 'overdue')
                <span class="ytd-od ytd-od-over">{{ $row['completion']['days'] }}d overdue</span>
            @elseif($row['completion']['status'] === 'late')
                <span class="ytd-od ytd-od-late">Completed {{ $row['completion']['days'] }}d after due</span>
            @elseif($row['completion']['status'] === 'on_time')
                <span class="ytd-od ytd-od-ok">On time</span>
            @else
                <span class="ytd-dash">—</span>
            @endif
        </td>
        <td>{{ $row['rework_count'] }}@if($row['rework_count'] > 0)<span class="ytd-sub"> · {{ $row['rework_creatives'] }} creatives</span>@endif</td>
        <td>
            @if($rowRatingValue !== null)
                <span class="ytd-stars" aria-label="{{ number_format($rowRatingValue, 1) }} out of 5 stars">
                    @for($i = 1; $i <= 5; $i++)<i style="--f:{{ $rowRatingValue >= $i ? 100 : ($rowRatingValue >= $i - 0.5 ? 50 : 0) }}%"></i>@endfor
                </span>
            @else
                <span class="ytd-dash">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="12"><div class="bd-empty">{{ ($search ?? '') !== '' ? 'No matching tasks found.' : 'No tasks assigned yet.' }}</div></td></tr>
@endforelse
