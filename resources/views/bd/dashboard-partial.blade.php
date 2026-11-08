{{-- BD Dashboard analytics fragment — swapped in/out by the dashboard filter controls. No <style>/<script>, safe to AJAX-swap. --}}
@php
    $fmt = function ($value) {
        return ($value === null || $value === '') ? '—' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    };
    $statusLabels = \App\Services\DesignTaskStatusService::STATUSES;
    $statusPill = function (string $status, bool $overdue = false) use ($statusLabels) {
        if ($overdue) {
            return '<span class="dh-pill dh-pill-overdue">Overdue</span>';
        }
        $map = [
            'assigned_tasks' => 'dh-pill-assigned', 'review_analysis' => 'dh-pill-review',
            'need_clarification' => 'dh-pill-clarify', 'yet_to_start' => 'dh-pill-ready',
            'in_progress' => 'dh-pill-progress', 'waiting_confirmation' => 'dh-pill-waiting',
            'rework' => 'dh-pill-rework', 'completed' => 'dh-pill-completed',
            'swap_tasks' => 'dh-pill-swap', 'default' => 'dh-pill-default',
        ];
        return '<span class="dh-pill '.($map[$status] ?? $map['default']).'">'
            .e($statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status))).'</span>';
    };
    $star = function ($value) {
        $rounded = max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($value)));
        $html = '<span class="dh-stars" aria-label="'.number_format($rounded, 1).' out of 5">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $rounded >= $i ? 100 : ($rounded >= $i - 0.5 ? 50 : 0);
            $html .= '<span class="dh-star" style="--star-fill:'.$fill.'%">★</span>';
        }
        return $html.'</span>';
    };
    $barMax = max(1, collect($bar)->max('value'));
@endphp

<div class="dh-zone-inner">

    {{-- 1. KPI summary — grouped cards (same visual system as the Designer dashboard) --}}
    @php
        $ic = [
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'user' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
            'loader' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
            'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'printer' => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'shuffle' => '<path d="M2 18h1.4c1.3 0 2.5-.6 3.3-1.7l6.1-8.6c.7-1.1 2-1.7 3.3-1.7H22"/><path d="m18 2 4 4-4 4"/><path d="M2 6h1.9c1.5 0 2.9.9 3.6 2.2"/><path d="M22 18h-5.9c-1.3 0-2.6-.7-3.3-1.8l-.5-.8"/><path d="m18 14 4 4-4 4"/>',
            'x' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
            'split' => '<path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/>',
            'tasks' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
            'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
            'trend' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'bar' => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
            'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'list' => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
            'timer' => '<line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/>',
        ];
        $svg = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($ic[$name] ?? $ic['list']).'</svg>';
        $cardIcon = fn (string $name, string $tone = 'blue') => '<span class="dh-card-icon dsh-'.$tone.'">'.$svg($name).'</span>';
        $focus = fn (string $key) => route('bd.tasks.index', ['focus' => $key]);
        $ctx = ($selectedMonthLabel ?? '').' · '.($selectedDesignerName ?? 'All Designers');
        $completionRate = $stats['total_tasks'] > 0 ? (int) round(($stats['completed'] / $stats['total_tasks']) * 100) : 0;

        // [label, value, href|null, icon, tone, note]
        $primaryCards = [
            ['Pending', $stats['pending'], $focus('assigned_tasks'), 'inbox', 'orange', 'Not yet started'],
            ['Ready to Start', $stats['ready_to_start'], $focus('yet_to_start'), 'play', 'green', 'Designer can begin'],
            ['In Progress', $stats['in_progress'], $focus('in_progress'), 'loader', 'purple', 'Being worked on now'],
            ['Waiting for BD Review', $stats['waiting'], $focus('waiting_confirmation'), 'clock', 'blue', 'Submitted, awaiting you'],
            ['Completed', $stats['completed'], $focus('completed'), 'check', 'teal', 'Finished and closed'],
        ];
        $secondaryCards = [
            ['Waiting for Your Confirmation', $stats['pending_bd_approval'], $focus('pending_bd_approval'), 'shield', 'blue', 'New tickets created by Designers'],
            ['Clarification Needed', $stats['clarification_needed'], $focus('need_clarification'), 'help', 'amber', 'Designer is waiting for your answer'],
            ['Rework Tasks', $stats['rework_tasks'], $focus('rework'), 'refresh', 'pink', 'Sent back, in rework now'],
            ['Prepare Printing File', $stats['prepare_printing_file'], $focus('prepare_printing_file'), 'printer', 'purple', 'Moved by you for printing'],
            ['Overdue', $stats['overdue'], null, 'alert', 'red', 'Past deadline, not completed'],
        ];
        $extraCards = [
            ['Declined', $stats['declined'], $focus('decline_tasks'), 'x', 'red', 'Approved declines'],
            ['Split', $stats['split'], $focus('split_log'), 'split', 'blue', 'Approved splits'],
            ['Swapped', $stats['swapped'], $focus('swap_tasks'), 'shuffle', 'blue', 'Approved transfers'],
            ['Designers Assigned', $stats['total_designers'], null, 'users', 'teal', 'All-time unique Designers on your tickets'],
        ];
    @endphp

    <div class="dsh-grid dsh-primary">
        <div class="dsh-card dsh-total">
            <span class="dsh-icon">{!! $svg('layers') !!}</span>
            <div><div class="dsh-label">Assigned Tasks</div><div class="dsh-value">{{ $stats['total_tasks'] }}</div><div class="dsh-note">{{ $ctx }}</div></div>
        </div>
        @foreach($primaryCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div><div class="dsh-sub-note">{{ $note }}</div></div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="dsh-grid dsh-secondary">
        @foreach($secondaryCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-flat dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}{{ $label === 'Overdue' && (int) $value > 0 ? ' is-alert' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div><div class="dsh-sub-note">{{ $note }}</div></div>
            </{{ $tag }}>
        @endforeach
    </div>

    <section class="dsh-completion">
        <div class="dsh-completion-label">Completion Rate <span>{{ $stats['completed'] }} of {{ $stats['total_tasks'] }} tasks</span></div>
        <div class="dsh-progress-track"><x-progress-fill :percentage="$completionRate" /></div>
        <div class="dsh-completion-value">{{ $completionRate }}%</div>
    </section>

    <div class="dsh-grid dsh-extra">
        @foreach($extraCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-flat dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div><div class="dsh-sub-note">{{ $note }}</div></div>
            </{{ $tag }}>
        @endforeach
    </div>

    {{-- 2. Designer workload --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('users', 'blue') !!}
            <div>
                <div class="dh-card-title">Designer Analytics</div>
                <div class="dh-card-sub">Click a Designer to filter the dashboard · <span class="dh-strong">Viewing: {{ $selectedDesignerName ?? 'All Designers' }} • {{ $selectedMonthLabel }}</span></div>
            </div>
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Designer</th><th>Assigned Tasks</th><th>Pending</th><th>Ready to Start</th><th>In Progress</th><th>Waiting for Review</th><th>Completed</th>
                    <th>Overdue</th><th>Rework Count</th><th>Rework Creatives</th><th>Split</th><th>Swap</th><th>Decline</th><th>Avg Rating</th>
                </tr>
                </thead>
                <tbody>
                @forelse($workload as $row)
                    <tr class="dh-click {{ (int) $selectedDesigner === (int) $row['designer']->id ? 'dh-row-selected' : '' }}" data-dh-design="{{ $row['designer']->id }}">
                        <td><span class="dh-designer-link">{{ $row['designer']->name }} @if($row['designer']->id === $selectedDesigner)<span class="dh-now">viewing</span>@endif</span></td>
                        <td class="dh-strong">{{ $row['assigned'] }}</td>
                        <td>{{ $row['pending'] }}</td>
                        <td>{{ $row['ready_to_start'] }}</td>
                        <td>{{ $row['in_progress'] }}</td>
                        <td>{{ $row['waiting'] }}</td>
                        <td>{{ $row['completed'] }}</td>
                        <td class="{{ $row['overdue'] ? 'dh-danger' : '' }}">{{ $row['overdue'] }}</td>
                        <td>{{ $row['rework_count'] }}</td>
                        <td>{{ $row['rework_creatives'] }}</td>
                        <td>{{ $row['split'] }}</td>
                        <td>{{ $row['swap'] }}</td>
                        <td>{{ $row['decline'] }}</td>
                        <td class="dh-strong">{{ $row['rating'] !== null ? $fmt($row['rating']).' / 5' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="14"><div class="dh-empty">No Designers found for your tickets in this period.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 3. BD Tickets section --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('tasks', 'purple') !!}
            <div>
                <div class="dh-card-title">BD Tickets</div>
                <div class="dh-card-sub">Your tickets assigned within this period · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            <div class="dh-card-badge">{{ $taskRows->count() }}</div>
        </div>
        <div class="dh-table-wrap dh-scroll" style="max-height:560px">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Task ID</th><th>Project Number</th><th>Task Name</th><th>Designer</th><th>Assigned By (BD)</th><th>Assigned At</th><th>Deadline</th>
                    <th>Progress</th><th>Creatives</th><th>Status</th><th>Completed At</th><th>Overdue</th><th>Rework</th><th>Rating</th>
                </tr>
                </thead>
                <tbody>
                @forelse($taskRows as $row)
                    @php $task = $row['task']; @endphp
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('bd.tasks.show', $task) }}">{{ $task->task_id }}</a></td>
                        <td>{{ $task->zoho_project_number ?: '-' }}</td>
                        <td class="dh-cell-main">{{ $task->display_task_name ?? $task->task_name }}</td>
                        <td>{{ $task->designer?->name ?? '—' }}</td>
                        <td>{{ $task->assigner?->name ?? '—' }}</td>
                        <td>{{ $task->assigned_at?->format('d M Y') ?? '—' }}</td>
                        <td class="{{ $row['overdue'] ? 'dh-danger' : '' }}">{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>
                            <div class="dh-progress"><div class="dh-progress-track"><x-progress-fill :percentage="$row['percentage']" /></div><div class="dh-progress-note">{{ $row['percentage'] }}%</div></div>
                        </td>
                        <td><span class="dh-strong">{{ $row['done'] }} / {{ $task->total_creatives }}</span><div class="dh-cell-sub">{{ $row['remaining'] }} remaining</div></td>
                        <td>{!! $statusPill($task->status, $row['overdue']) !!}</td>
                        <td>{{ $row['completed_at']?->format('d M Y') ?? '—' }}</td>
                        <td>
                            @if($row['completion']['status'] === 'overdue')
                                <span class="dh-pill dh-pill-overdue">{{ $row['completion']['days'] }}d overdue</span>
                            @elseif($row['completion']['status'] === 'late')
                                <span class="dh-pill dh-pill-clarify">Completed {{ $row['completion']['days'] }}d after due</span>
                            @elseif($row['completion']['status'] === 'on_time')
                                <span class="dh-pill dh-pill-completed">On time</span>
                            @else
                                <span class="dh-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row['rework_count'] > 0)
                                <span class="dh-strong">{{ $row['rework_count'] }} Rework{{ $row['rework_count'] > 1 ? 's' : '' }}</span>
                                <div class="dh-cell-sub">{{ $row['rework_creatives'] }} Creatives @if($row['rework_spent_text']) · {{ $row['rework_spent_text'] }} @endif</div>
                            @else
                                <span class="dh-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row['rating'] !== null)
                                {!! $star($row['rating']) !!}
                                <div class="dh-cell-sub">{{ $fmt($row['rating']) }} / 5</div>
                            @else
                                <span class="dh-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14"><div class="dh-empty">{{ ($search ?? '') !== '' ? 'No matching tasks found.' : 'No tickets found for this period.' }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 5. Performance Trend --}}
    @php
        $mixTotal = max(1, (int) $stats['total_tasks']);
        $mix = [
            ['Assigned / In Review', max(0, $stats['pending'] - $stats['ready_to_start'] - $stats['clarification_needed']), '#98a2b3', 'assigned_tasks'],
            ['Clarification', $stats['clarification_needed'], '#f79009', 'need_clarification'],
            ['Ready to Start', $stats['ready_to_start'], '#12b76a', 'yet_to_start'],
            ['In Progress', $stats['in_progress'], '#7c3aed', 'in_progress'],
            ['Waiting for Review', $stats['waiting'], '#2970ff', 'waiting_confirmation'],
            ['Rework', $stats['rework_tasks'], '#e11d48', 'rework'],
            ['Printing File', $stats['prepare_printing_file'], '#0e7490', 'prepare_printing_file'],
            ['Completed', $stats['completed'], '#027a48', 'completed'],
        ];
        // Drafts / awaiting-confirmation tickets sit outside the pipeline statuses above — keep the donut summing to the KPI total.
        $mix[] = ['Draft / Confirmation', max(0, (int) $stats['total_tasks'] - array_sum(array_column($mix, 1))), '#cbd5e1', 'pending_bd_approval'];
        $mixSum = array_sum(array_column($mix, 1));
        $acc = 0;
        $stops = [];
        $segs = [];
        foreach ($mix as $mi => [$l, $v, $c, $fk]) {
            if ($v <= 0) { continue; }
            $from = $acc / max(1, $mixSum) * 100;
            $acc += $v;
            $to = $acc / max(1, $mixSum) * 100;
            $stops[] = $c.' '.round($from, 2).'% '.round($to, 2).'%';
            $segs[] = ['i' => $mi, 'label' => $l, 'value' => $v, 'color' => $c, 'from' => round($from, 3), 'to' => round($to, 3), 'pct' => round($v / max(1, $mixSum) * 100)];
        }
        $donut = $stops ? 'conic-gradient('.implode(',', $stops).')' : 'conic-gradient(#eaecf0 0 100%)';
    @endphp
    <div class="dh-grid dh-grid-2 dh-grid-fill">
        <section class="dh-card dh-card-fill">
            <div class="dh-card-head">
                {!! $cardIcon('bar', 'orange') !!}
                <div>
                    <div class="dh-card-title">{{ $selectedMonthLabel }} — Monthly Analytics</div>
                    <div class="dh-card-sub">{{ $selectedDesignerName ?? 'All Designers' }}</div>
                </div>
            </div>
            <div class="dh-card-body dh-bar">
                @foreach($bar as $b)
                    <div class="dh-bar-row" data-hover="bar" data-label="{{ $b['label'] }}" data-value="{{ $b['value'] }}" data-share="{{ $bar[0]['value'] > 0 ? round($b['value'] / $bar[0]['value'] * 100) : 0 }}" data-color="{{ $b['color'] }}">
                        <div class="dh-bar-label">{{ $b['label'] }}</div>
                        <div class="dh-bar-track"><div class="dh-bar-fill" style="width:{{ $b['value'] > 0 ? max(3, round(($b['value'] / $barMax) * 100)) : 0 }}%;background:{{ $b['color'] }}"></div></div>
                        <div class="dh-bar-value">{{ $b['value'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="dh-mix">
                <div class="dh-mix-title">Current status mix <span>{{ $stats['total_tasks'] }} tasks</span></div>
                <div class="dh-mix-body">
                    <div class="dh-donut" data-hover="donut" data-total="{{ $mixSum }}" data-segs='@json($segs)' style="background:{{ $donut }}"><div class="dh-donut-hole"><strong>{{ $stats['total_tasks'] }}</strong><span>Tasks</span></div></div>
                    <div class="dh-mix-legend">
                        @foreach($mix as $mi => [$l, $v, $c, $fk])
                            <a class="dh-leg" href="{{ $focus($fk) }}" data-seg-idx="{{ $mi }}" title="Open {{ $l }} tasks"><i style="background:{{ $c }}"></i><span>{{ $l }}</span><b>{{ $v }}</b></a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        @include('shared.performance-trend', [
            'trendCards' => $trendCards,
            'trendData' => $line,
            'trendContext' => $trendContext,
            'expandable' => true,
        ])
    </div>

    {{-- 6. Overdue Tasks --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('alert', 'red') !!}
            <div>
                <div class="dh-card-title">Overdue Tasks</div>
                <div class="dh-card-sub">Not completed and past the deadline · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($overdue->isNotEmpty())<div class="dh-card-badge">{{ $overdue->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Task</th><th>Project Number</th><th>Designer</th><th>BD</th><th>Deadline</th><th>Days Overdue</th><th>Progress</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse($overdue as $row)
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('bd.tasks.show', $row['task']) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
                        <td>{{ $row['task']->zoho_project_number ?: '-' }}</td>
                        <td>{{ $row['task']->designer?->name ?? '—' }}</td>
                        <td>{{ $row['task']->assigner?->name ?? '—' }}</td>
                        <td class="dh-danger">{{ $row['task']->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
                        <td><span class="dh-pill dh-pill-overdue">{{ $row['days'] }}d</span></td>
                        <td>
                            <div class="dh-progress"><div class="dh-progress-track"><x-progress-fill :percentage="$row['percentage']" /></div><div class="dh-progress-note">{{ $row['percentage'] }}% · {{ $row['done'] }}/{{ $row['total'] }}</div></div>
                        </td>
                        <td>{!! $statusPill($row['task']->status) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="dh-empty">{{ $selectedDesignerName ?? 'All Designers' }} — {{ $selectedMonthLabel }}: No overdue tasks.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 9. Rework Analytics --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('refresh', 'pink') !!}
            <div>
                <div class="dh-card-title">Rework Analytics</div>
                <div class="dh-card-sub">Every one of your tickets that entered rework · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($reworkRows->isNotEmpty())<div class="dh-card-badge">{{ $reworkRows->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Task</th><th>Project Number</th><th>Designer</th><th>Rework #</th><th>Rework Assigned At</th><th>Rework Creative Count</th><th>Designer Rework Time</th><th>Current Status</th></tr>
                </thead>
                <tbody>
                @forelse($reworkRows as $row)
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('bd.tasks.show', $row['task']) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
                        <td>{{ $row['task']->zoho_project_number ?: '-' }}</td>
                        <td>{{ $row['task']->designer?->name ?? '—' }}</td>
                        <td><span class="dh-strong">Rework {{ $row['rework_number'] }}</span></td>
                        <td>{{ optional($row['rework_assigned_at'])->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $row['rework_creatives'] ?? '—' }}</td>
                        <td>{{ $row['rework_spent_text'] ?? '—' }}</td>
                        <td>{!! $statusPill($row['task']->status) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="dh-empty">No rework records found for your tickets.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 10. Completed Task Ratings --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('star', 'amber') !!}
            <div>
                <div class="dh-card-title">Completed Task Ratings</div>
                <div class="dh-card-sub">Your feedback on completed work</div>
            </div>
            <select class="dh-select" id="dhr-designer" aria-label="Filter ratings by Designer">
                <option value="all" @selected($selectedDesigner === null)>All Designers</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected((int) $selectedDesigner === (int) $designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>
        </div>
        <div id="dhr-root">
            @include('bd.ratings-rows', $completedRatings)
        </div>
    </section>

    {{-- 11. Recent Completion Activity --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('check', 'teal') !!}
            <div>
                <div class="dh-card-title">Recent Completion Activity</div>
                <div class="dh-card-sub">Your latest finished tickets with real completion timestamps</div>
            </div>
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Task</th><th>Project Number</th><th>Designer</th><th>Assigned At</th><th>Due Date</th><th>Completed At</th><th>Duration</th><th>Rating</th><th>Rework</th></tr>
                </thead>
                <tbody>
                @forelse($completions as $row)
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('bd.tasks.show', $row['task']) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
                        <td>{{ $row['task']->zoho_project_number ?: '-' }}</td>
                        <td>{{ $row['task']->designer?->name ?? '—' }}</td>
                        <td>{{ $row['task']->assigned_at?->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $row['task']->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $row['completed_at']->format('d M Y · h:i A') }}</td>
                        <td>{{ $row['duration_text'] ?? '—' }}</td>
                        <td>
                            @if($row['rating'] !== null)
                                {!! $star($row['rating']) !!}<div class="dh-cell-sub">{{ $fmt($row['rating']) }} / 5</div>
                            @else <span class="dh-muted">—</span> @endif
                        </td>
                        <td>{{ $row['rework_count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="dh-empty">No completions recorded yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 12. BD Review Turnaround --}}
    <section class="dh-card">
        <div class="dh-card-head">
            {!! $cardIcon('timer', 'purple') !!}
            <div>
                <div class="dh-card-title">BD Review Turnaround</div>
                <div class="dh-card-sub">Time you took to review each submission · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($bdReviewRows->isNotEmpty())<div class="dh-card-badge">{{ $bdReviewRows->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap dh-scroll" style="max-height:560px">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Task</th><th>Task Created At</th><th>Task Deadline</th><th>Moved to BD Review At</th>
                    <th>BD Review Duration</th><th>BD Decision</th><th>Designer Submission</th><th>Rework</th>
                </tr>
                </thead>
                <tbody>
                @forelse($bdReviewRows as $row)
                    @php $task = $row['task']; @endphp
                    <tr>
                        <td>
                            <a class="dh-task-link" href="{{ route('bd.tasks.show', $task) }}">{{ $task->task_id }}</a>
                            @if($task->zoho_project_number)<div class="dh-cell-sub">{{ $task->zoho_project_number }}</div>@endif
                            <div class="dh-cell-sub">{{ $task->display_task_name ?? $task->task_name }}</div>
                            <div class="dh-cell-sub">{{ $task->designer?->name ?? '—' }}</div>
                        </td>
                        <td>{{ optional($task->created_at)->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $row['submitted_at']->format('d M Y · h:i A') }}</td>
                        <td class="{{ $row['decision_status'] === 'pending' ? 'dh-danger' : '' }}">{{ $row['duration_text'] ?? '—' }}</td>
                        <td>
                            @if($row['decision_status'] === 'completed')
                                <span class="dh-pill dh-pill-completed">Completed</span>
                                <div class="dh-cell-sub">{{ optional($row['decision_at'])->format('d M Y, h:i A') }}</div>
                            @elseif($row['decision_status'] === 'rework')
                                <span class="dh-pill dh-pill-rework">Rework</span>
                                <div class="dh-cell-sub">{{ optional($row['decision_at'])->format('d M Y, h:i A') }}</div>
                                @if($row['rework'])
                                    <div class="dh-cell-sub dh-strong" style="margin-top:5px">Rework {{ $row['rework']['ordinal'] }} of {{ $row['rework']['total'] }} · {{ $row['rework']['creatives'] }} creatives</div>
                                    <div class="dh-cell-sub">Started {{ $row['rework']['started_at']->format('d M Y, h:i A') }}</div>
                                    <div class="dh-cell-sub">
                                        @if($row['rework']['moved_back_at'])
                                            Back to review {{ $row['rework']['moved_back_at']->format('d M Y, h:i A') }}
                                        @else
                                            Still in rework
                                        @endif
                                    </div>
                                    <div class="dh-cell-sub dh-strong">Designer rework duration: {{ $row['rework']['duration_text'] }}</div>
                                @endif
                            @else
                                <span class="dh-pill dh-pill-waiting">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($row['designer_on_time_text'] === 'On Time')
                                <span class="dh-pill dh-pill-completed">On Time</span>
                            @elseif(str_starts_with($row['designer_on_time_text'], 'Late'))
                                <span class="dh-pill dh-pill-overdue">{{ $row['designer_on_time_text'] }}</span>
                            @else
                                <span class="dh-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row['rework'])
                                <span class="dh-strong">{{ $row['rework']['creatives'] }} creatives</span>
                            @else <span class="dh-muted">—</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="dh-empty">No BD review activity recorded yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Requests & decisions --}}
    <div class="dh-grid dh-grid-2">
        <section class="dh-card">
            <div class="dh-card-head">
                {!! $cardIcon('shield', 'blue') !!}
            <div>
                    <div class="dh-card-title">Pending Approval Requests</div>
                    <div class="dh-card-sub">Split, Transfer &amp; Decline requests on your tickets</div>
                </div>
                @if($pendingRequests->isNotEmpty())<div class="dh-card-badge">{{ $pendingRequests->count() }}</div>@endif
            </div>
            <div class="dh-card-body dh-scroll" style="max-height:520px">
                @forelse($pendingRequests as $request)
                    @php $isStatusChange = $request->request_type === 'status_change'; @endphp
                    <div class="dh-req">
                        <div class="dh-req-top">
                            <div>
                                <strong>{{ $isStatusChange ? 'Backward Status' : ucfirst($request->request_type) }} Request</strong>
                                <div class="dh-cell-sub">
                                    @if($request->task)
                                        <a class="dh-task-link" href="{{ route('bd.tasks.show', $request->task) }}">{{ $request->task->task_id }}</a>
                                    @else Task unavailable @endif
                                    · requested by {{ $request->requester?->name ?? '—' }} · {{ $request->created_at?->format('d M, h:i A') }}
                                </div>
                            </div>
                            <span class="dh-pill dh-pill-waiting">Pending</span>
                        </div>
                        @if($isStatusChange)
                            <div class="dh-cell-sub">Current: <strong>{{ \App\Services\DesignTaskStatusService::STATUSES[$request->from_status] ?? $request->from_status }}</strong> → Requested: <strong>{{ \App\Services\DesignTaskStatusService::STATUSES[$request->to_status] ?? $request->to_status }}</strong></div>
                        @endif
                        @if($request->reason)<div class="dh-req-reason">“{{ $request->reason }}”</div>@endif
                        @if($request->targetDesigner)<div class="dh-cell-sub">Preferred Designer: <strong>{{ $request->targetDesigner->name }}</strong></div>@endif
                    </div>
                @empty
                    <div class="dh-empty">No pending approval requests on your tickets.</div>
                @endforelse
            </div>
        </section>

        <section class="dh-card">
            <div class="dh-card-head">
                {!! $cardIcon('list', 'green') !!}
            <div>
                    <div class="dh-card-title">Recent Decisions</div>
                    <div class="dh-card-sub">Latest approved &amp; rejected requests on your tickets</div>
                </div>
            </div>
            <div class="dh-table-wrap">
                <table class="dh-table">
                    <thead>
                    <tr><th>Type</th><th>Task</th><th>Requested By</th><th>Requested At</th><th>Result</th><th>Responded</th></tr>
                    </thead>
                    <tbody>
                    @forelse($recentDecisions as $request)
                        @php $approved = $request->overall_status === 'approved'; @endphp
                        <tr>
                            <td>{{ $request->request_type === 'status_change' ? 'Backward Status' : ucfirst($request->request_type) }}</td>
                            <td>
                                @if($request->task)
                                    <a class="dh-task-link" href="{{ route('bd.tasks.show', $request->task) }}">{{ $request->task->task_id }}</a>
                                @else — @endif
                            </td>
                            <td>{{ $request->requester?->name ?? '—' }}</td>
                            <td>{{ optional($request->created_at)->format('d M, h:i A') }}</td>
                            <td><span class="dh-pill {{ $approved ? 'dh-pill-completed' : 'dh-pill-overdue' }}">{{ $approved ? 'Approved' : 'Rejected' }}</span></td>
                            <td>{{ optional($request->responded_at)->format('d M, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="dh-empty">No decisions recorded yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
