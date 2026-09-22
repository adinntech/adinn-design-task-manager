@forelse($taskRows as $row)
    @php
        $task = $row['task'];
        $rowRatingValue = $row['rating'] !== null ? max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($row['rating']))) : null;
    @endphp
    <tr>
        <td><a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
        <td>{{ $task->zoho_project_number ?: '-' }}</td>
        <td>{{ $task->display_task_name ?? $task->task_name }}</td>
        <td>{{ $task->assigned_at?->format('d M Y') ?? '—' }}</td>
        <td>
            @if($row['overdue'])
                <span class="bd-pill pill-overdue">Overdue</span>
            @else
                <span class="bd-pill pill-{{ $task->status === 'rework' ? 'rework' : ($task->status === 'completed' ? 'completed' : ($task->status === 'waiting_confirmation' ? 'waiting' : ($task->status === 'in_progress' ? 'progress' : 'default'))) }}">{{ ucwords(str_replace('_',' ',$task->status)) }}</span>
            @endif
        </td>
        <td>{{ $row['percentage'] }}%</td>
        <td><span style="font-weight:850">{{ $row['done'] }} / {{ $task->total_creatives }}</span><div style="color:#98a2b3">{{ $row['remaining'] }} remaining</div></td>
        <td style="{{ $row['overdue'] ? 'color:#c01048;font-weight:850' : '' }}">{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
        <td>{{ $row['completed_at']?->format('d M Y') ?? '—' }}</td>
        <td>
            @if($row['completion']['status'] === 'overdue')
                <span class="bd-pill pill-overdue">{{ $row['completion']['days'] }}d overdue</span>
            @elseif($row['completion']['status'] === 'late')
                <span class="bd-pill pill-rework">Completed {{ $row['completion']['days'] }}d after due</span>
            @elseif($row['completion']['status'] === 'on_time')
                <span class="bd-pill pill-completed">On time</span>
            @else
                <span style="color:#98a2b3">—</span>
            @endif
        </td>
        <td>{{ $row['rework_count'] }}@if($row['rework_count'] > 0)<span style="color:#98a2b3"> · {{ $row['rework_creatives'] }} creatives</span>@endif</td>
        <td>
            @if($rowRatingValue !== null)
                <span aria-label="{{ number_format($rowRatingValue, 1) }} out of 5 stars">
                    @for($i = 1; $i <= 5; $i++)
                        @php $fill = $rowRatingValue >= $i ? 100 : ($rowRatingValue >= $i - 0.5 ? 50 : 0); @endphp
                        <span class="bd-review-star" style="--star-fill:{{ $fill }}%">★</span>
                    @endfor
                </span>
            @else
                <span style="color:#98a2b3">—</span>
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="12"><div class="bd-empty">{{ ($search ?? '') !== '' ? 'No matching tasks found.' : 'No tasks assigned yet.' }}</div></td></tr>
@endforelse
