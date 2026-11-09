@php
    // Lucide-style inline icons for the "Your Task Details" side drawer.
    $tdrIc = [
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'loader' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
        'dot' => '<circle cx="12" cy="12" r="5" fill="currentColor"/>',
        'list' => '<path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/>',
    ];
    $tdrSvg = fn (string $n) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$tdrIc[$n].'</svg>';
    $tdrRows = $taskRows->keyBy(fn ($r) => $r['task']->id);
@endphp

<div class="tdr-overlay" id="tdr-overlay" hidden></div>
<aside class="tdr" id="tdr" role="dialog" aria-modal="true" aria-label="Task details" aria-hidden="true">
    <button type="button" class="tdr-hide" id="tdr-hide" title="Hide details" aria-label="Hide details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
    <div class="tdr-body">
        @foreach($recentTasks as $t)
            @php
                $row = $tdrRows->get($t->id);
                if (! $row) { continue; }
                $task = $row['task'];
                $ratingValue = $row['rating'] !== null ? max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($row['rating']))) : null;
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
            <div class="tdr-pane" data-tdr-pane="{{ $task->id }}" hidden>
                <div class="tdr-title">
                    <span class="tdr-tid">{{ $task->task_id }}</span>
                    <span class="tov-pill tov-st-{{ $stTone }}">{!! $tdrSvg($stIcon) !!}{{ $stLabel }}</span>
                </div>
                <p class="tdr-name">{{ $task->display_task_name ?? $task->task_name }}</p>

                <dl class="tdr-grid">
                    <div class="tdr-cell"><dt>Project Number</dt><dd>{{ $task->zoho_project_number ?: '-' }}</dd></div>
                    <div class="tdr-cell"><dt>Assigned At</dt><dd>@if($task->assigned_at)<span class="tov-by">{!! $tdrSvg('calendar') !!}{{ $task->assigned_at->format('d M Y') }}</span>@else — @endif</dd></div>
                    <div class="tdr-cell is-wide">
                        <dt>Progress</dt>
                        <dd>
                            <div class="ytd-pct">{{ $row['percentage'] }}%</div>
                            <div class="ytd-bar" style="width:100%"><i style="width:{{ $pct }}%;background:{{ $barColor }}"></i></div>
                        </dd>
                    </div>
                    <div class="tdr-cell"><dt>Creatives</dt><dd><div class="ytd-count">{{ $row['done'] }} / {{ $task->total_creatives }}</div><div class="ytd-sub">{{ $row['remaining'] }} remaining</div></dd></div>
                    <div class="tdr-cell"><dt>Rework</dt><dd>{{ $row['rework_count'] }}@if($row['rework_count'] > 0)<span class="ytd-sub"> · {{ $row['rework_creatives'] }} creatives</span>@endif</dd></div>
                    <div class="tdr-cell"><dt>Deadline</dt><dd class="tov-due{{ $row['overdue'] ? ' is-overdue' : '' }}"><span>@if($task->due_at){!! $tdrSvg('calendar') !!}{{ $task->due_at->format('d M Y · h:i A') }}@else — @endif</span></dd></div>
                    <div class="tdr-cell"><dt>Completed At</dt><dd>@if($row['completed_at'])<span class="tov-by ytd-done">{!! $tdrSvg('calendar') !!}{{ $row['completed_at']->format('d M Y') }}</span>@else<span class="ytd-dash">—</span>@endif</dd></div>
                    <div class="tdr-cell">
                        <dt>Overdue</dt>
                        <dd>
                            @if($row['completion']['status'] === 'overdue')
                                <span class="ytd-od ytd-od-over">{{ $row['completion']['days'] }}d overdue</span>
                            @elseif($row['completion']['status'] === 'late')
                                <span class="ytd-od ytd-od-late">Completed {{ $row['completion']['days'] }}d after due</span>
                            @elseif($row['completion']['status'] === 'on_time')
                                <span class="ytd-od ytd-od-ok">On time</span>
                            @else
                                <span class="ytd-dash">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="tdr-cell">
                        <dt>Rating</dt>
                        <dd>
                            @if($ratingValue !== null)
                                <span class="ytd-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                    @for($i = 1; $i <= 5; $i++)<i style="--f:{{ $ratingValue >= $i ? 100 : ($ratingValue >= $i - 0.5 ? 50 : 0) }}%"></i>@endfor
                                </span>
                            @else
                                <span class="ytd-dash">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <a class="tdr-open" href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">Open task on board &rarr;</a>
            </div>
        @endforeach
    </div>
</aside>

<script>
(function () {
    var drawer = document.getElementById('tdr');
    var overlay = document.getElementById('tdr-overlay');
    if (!drawer || !overlay) return;
    // Move to <body> so position:fixed is never trapped by a transformed ancestor.
    document.body.appendChild(overlay);
    document.body.appendChild(drawer);

    var lastTrigger = null;

    function open(id, trigger) {
        var found = false;
        drawer.querySelectorAll('[data-tdr-pane]').forEach(function (p) {
            var match = p.getAttribute('data-tdr-pane') === String(id);
            p.hidden = !match;
            if (match) found = true;
        });
        if (!found) return;
        lastTrigger = trigger || null;
        overlay.hidden = false;
        requestAnimationFrame(function () {
            overlay.classList.add('is-open');
            drawer.classList.add('is-open');
        });
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tdr-lock');
        drawer.querySelector('.tdr-body').scrollTop = 0;
        document.getElementById('tdr-hide').focus();
    }

    function close() {
        overlay.classList.remove('is-open');
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('tdr-lock');
        setTimeout(function () { if (!overlay.classList.contains('is-open')) overlay.hidden = true; }, 300);
        if (lastTrigger) lastTrigger.focus();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tdr-open]');
        if (btn) { open(btn.getAttribute('data-tdr-open'), btn); }
    });
    overlay.addEventListener('click', close);
    document.getElementById('tdr-hide').addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });
})();
</script>
