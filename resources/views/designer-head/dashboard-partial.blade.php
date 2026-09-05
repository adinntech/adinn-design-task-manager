{{-- Designer Head analytics fragment — swapped in/out by the dashboard filter controls. No <style>/<script>, safe to AJAX-swap. --}}
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

    {{-- 1. Top KPI summary --}}
    <div class="dh-kpis">
        <div class="dh-kpi"><div class="dh-kpi-icon">▤</div><div class="dh-kpi-label">Total Designers</div><div class="dh-kpi-value">{{ $stats['total_designers'] }}</div><div class="dh-kpi-note">All designer accounts</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">◉</div><div class="dh-kpi-label">Active Designers</div><div class="dh-kpi-value">{{ $stats['active_designers'] }}</div><div class="dh-kpi-note">Currently active</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">▦</div><div class="dh-kpi-label">Assigned Tasks</div><div class="dh-kpi-value">{{ $stats['total_tasks'] }}</div><div class="dh-kpi-note">{{ $selectedDesignerName ?? 'Across all Designers' }}</div></div>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'in_progress']) }}"><div class="dh-kpi-icon">➤</div><div class="dh-kpi-label">In Progress</div><div class="dh-kpi-value">{{ $stats['in_progress'] }}</div><div class="dh-kpi-note">Being worked now</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'assigned_tasks']) }}"><div class="dh-kpi-icon">◷</div><div class="dh-kpi-label">Pending</div><div class="dh-kpi-value">{{ $stats['pending'] }}</div><div class="dh-kpi-note">Not yet started</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'yet_to_start']) }}"><div class="dh-kpi-icon">◔</div><div class="dh-kpi-label">Ready to Start</div><div class="dh-kpi-value">{{ $stats['ready_to_start'] }}</div><div class="dh-kpi-note">{{ $selectedDesignerName ?? 'Across all Designers' }}</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'waiting_confirmation']) }}"><div class="dh-kpi-icon">↗</div><div class="dh-kpi-label">Waiting BD Review</div><div class="dh-kpi-value">{{ $stats['waiting'] }}</div><div class="dh-kpi-note">Waiting for confirmation</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'completed']) }}"><div class="dh-kpi-icon">✓</div><div class="dh-kpi-label">Completed</div><div class="dh-kpi-value">{{ $stats['completed'] }}</div><div class="dh-kpi-note">Finished tasks</div></a>
        <div class="dh-kpi"><div class="dh-kpi-icon">!</div><div class="dh-kpi-label">Overdue</div><div class="dh-kpi-value">{{ $stats['overdue'] }}</div><div class="dh-kpi-note">Past deadline</div></div>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'decline_tasks']) }}"><div class="dh-kpi-icon">✕</div><div class="dh-kpi-label">Declined</div><div class="dh-kpi-value">{{ $stats['declined'] }}</div><div class="dh-kpi-note">Approved declines</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'requests']) }}"><div class="dh-kpi-icon">✂</div><div class="dh-kpi-label">Split</div><div class="dh-kpi-value">{{ $stats['split'] }}</div><div class="dh-kpi-note">Approved splits</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'swap_tasks']) }}"><div class="dh-kpi-icon">⇄</div><div class="dh-kpi-label">Swapped</div><div class="dh-kpi-value">{{ $stats['swapped'] }}</div><div class="dh-kpi-note">Approved transfers</div></a>
        <a class="dh-kpi dh-kpi-link" href="{{ route('designer-head.assigned-tasks', ['focus' => 'rework']) }}"><div class="dh-kpi-icon">↻</div><div class="dh-kpi-label">Rework Tasks</div><div class="dh-kpi-value">{{ $stats['rework_tasks'] }}</div><div class="dh-kpi-note">In rework now</div></a>
        <a class="dh-kpi dh-kpi-link dh-kpi-accent" href="{{ route('designer-head.assigned-tasks', ['focus' => 'requests']) }}"><div class="dh-kpi-icon">◇</div><div class="dh-kpi-label">Pending Approval</div><div class="dh-kpi-value">{{ $stats['approval_pending'] }}</div><div class="dh-kpi-note">Requests awaiting you</div></a>
    </div>

    {{-- 2. Designer workload --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Designer Analytics</div>
                <div class="dh-card-sub">Click a Designer to filter the dashboard · <span class="dh-strong">Viewing: {{ $selectedDesignerName ?? 'All Designers' }} • {{ $selectedMonthLabel }}</span></div>
            </div>
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Designer</th><th>Current Tasks</th><th>In Progress</th><th>Pending</th><th>Ready to Start</th><th>Overdue</th><th>Completed</th>
                    <th>Rework</th><th>Rework Creatives</th><th>Split</th><th>Swap</th><th>Decline</th><th>Avg Rating</th>
                </tr>
                </thead>
                <tbody>
                @forelse($workload as $row)
                    <tr class="dh-click {{ (int) $selectedDesigner === (int) $row['designer']->id ? 'dh-row-selected' : '' }}" data-dh-design="{{ $row['designer']->id }}">
                        <td><span class="dh-designer-link">{{ $row['designer']->name }} @if($row['designer']->id === $selectedDesigner)<span class="dh-now">viewing</span>@endif</span></td>
                        <td class="dh-strong">{{ $row['assigned'] }}</td>
                        <td>{{ $row['in_progress'] }}</td>
                        <td>{{ $row['pending'] }}</td>
                        <td>{{ $row['ready_to_start'] }}</td>
                        <td class="{{ $row['overdue'] ? 'dh-danger' : '' }}">{{ $row['overdue'] }}</td>
                        <td>{{ $row['completed'] }}</td>
                        <td>{{ $row['rework_count'] }}</td>
                        <td>{{ $row['rework_creatives'] }}</td>
                        <td>{{ $row['split'] }}</td>
                        <td>{{ $row['swap'] }}</td>
                        <td>{{ $row['decline'] }}</td>
                        <td class="dh-strong">{{ $row['rating'] !== null ? $fmt($row['rating']).' / 5' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13"><div class="dh-empty">No active Designers found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 3. Team Designer Last Login --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Team Designer Last Login</div>
            </div>
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Designer</th><th>Username</th><th>Last Login Date</th><th>Last Login Time</th></tr>
                </thead>
                <tbody>
                @forelse($teamDesignerLogins as $designerLogin)
                    <tr>
                        <td><span class="dh-designer-link">{{ $designerLogin->name }}</span></td>
                        <td>{{ $designerLogin->username ?? '—' }}</td>
                        <td>{{ $designerLogin->last_login_at?->format('d M Y') ?? 'Never Logged In' }}</td>
                        <td>{{ $designerLogin->last_login_at?->format('h:i A') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="dh-empty">No active Designers found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 4. Monthly charts --}}
    <div class="dh-grid dh-grid-2">
        <section class="dh-card">
            <div class="dh-card-head">
                <div>
                    <div class="dh-card-title">{{ $selectedMonthLabel }} — Designer Analytics</div>
                    <div class="dh-card-sub">{{ $selectedDesignerName ?? 'All Designers' }}</div>
                </div>
            </div>
            <div class="dh-card-body dh-bar">
                @foreach($bar as $b)
                    <div class="dh-bar-row">
                        <div class="dh-bar-label">{{ $b['label'] }}</div>
                        <div class="dh-bar-track"><div class="dh-bar-fill" style="width:{{ $b['value'] > 0 ? max(3, round(($b['value'] / $barMax) * 100)) : 0 }}%;background:{{ $b['color'] }}"></div></div>
                        <div class="dh-bar-value">{{ $b['value'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        @include('shared.performance-trend', [
            'trendCards' => $trendCards,
            'trendData' => $line,
            'trendContext' => $trendContext,
            'expandable' => true,
        ])
    </div>

    {{-- 10. Overdue tracking --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Overdue Tasks</div>
                <div class="dh-card-sub">Not completed and past the deadline · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($overdue->isNotEmpty())<div class="dh-card-badge">{{ $overdue->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Task</th><th>Designer</th><th>BD</th><th>Deadline</th><th>Days Overdue</th><th>Progress</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse($overdue as $row)
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $row['task']->status, 'task' => $row['task']->task_id]) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
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
                    <tr><td colspan="7"><div class="dh-empty">{{ $selectedDesignerName ?? 'All Designers' }} — {{ $selectedMonthLabel }}: No overdue tasks.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 9. Approval control --}}
    <div class="dh-grid dh-grid-2">
        <section class="dh-card">
            <div class="dh-card-head">
                <div>
                    <div class="dh-card-title">Pending Approval Requests</div>
                    <div class="dh-card-sub">Split, Transfer &amp; Decline requests awaiting your decision</div>
                </div>
                @if($pendingRequests->isNotEmpty())<div class="dh-card-badge">{{ $pendingRequests->count() }}</div>@endif
            </div>
            <div class="dh-card-body dh-scroll" style="max-height:520px">
                @forelse($pendingRequests as $request)
                    @php
                        $typeLabel = ucfirst($request->request_type);
                        $isSwap = $request->request_type === 'swap';
                        $isSplit = $request->request_type === 'split';
                        // Same fallback chain as the Split Details tab (designer-head/tasks/show.blade.php)
                        // so both places always agree on the Designer-requested quantity.
                        $requestedSplit = data_get($request, 'split_count')
                            ?? data_get($request, 'split_details.requested_count')
                            ?? data_get($request, 'split_details.creative_count');
                    @endphp
                    <div class="dh-req">
                        <div class="dh-req-top">
                            <div>
                                <strong>{{ $typeLabel }} Request</strong>
                                <div class="dh-cell-sub">
                                    @if($request->task)
                                        <a class="dh-task-link" href="{{ route('designer-head.tasks.show', ['task' => $request->task, 'tab' => $isSplit ? 'split-details' : ($isSwap ? 'swap-details' : 'decline-details')]) }}">{{ $request->task->task_id }}</a>
                                    @else Task unavailable @endif
                                    · requested by {{ $request->requester?->name ?? '—' }} · {{ $request->created_at?->format('d M, h:i A') }}
                                </div>
                            </div>
                            <span class="dh-pill dh-pill-waiting">Pending</span>
                        </div>
                        @if($request->reason)<div class="dh-req-reason">“{{ $request->reason }}”</div>@endif
                        @if(! $isSwap && $request->targetDesigner)<div class="dh-cell-sub">Preferred Designer: <strong>{{ $request->targetDesigner->name }}</strong></div>@endif
                        <div class="dh-decision">
                            <form class="dh-decision-box" method="POST" action="{{ route('designer-head.requests.approve', $request) }}">
                                @csrf
                                <div class="dh-decision-title">Approve Request</div>
                                <label class="dh-label">Final Designer *</label>
                                <select class="dh-select-field" name="approved_designer_id" required>
                                    <option value="">Select Designer</option>
                                    @foreach($designers as $designer)
                                        @continue((int) $designer->id === (int) ($request->task?->designer_id))
                                        <option value="{{ $designer->id }}" @selected((int) old('approved_designer_id') === (int) $designer->id)>{{ $designer->name }}</option>
                                    @endforeach
                                </select>
                                @if($isSplit)
                                    <label class="dh-label">Split Quantity *</label>
                                    <input class="dh-select-field" type="number" name="approved_creative_count" min="1" max="{{ max(1, ((int) ($request->task?->total_creatives ?? 1)) - 1) }}" value="{{ old('approved_creative_count', $requestedSplit ?: 1) }}" required>
                                    <div class="dh-hint">Designer requested {{ $requestedSplit ?? '—' }} · at least 1 creative must remain with the original task.</div>
                                    <div class="dh-hint">Overall Creative Count: {{ (int) ($request->task?->total_creatives ?? 0) }}</div>
                                @endif
                                <label class="dh-label" style="margin-top:8px">Comment</label>
                                <textarea class="dh-select-field" name="decision_comment" placeholder="Optional approval comment">{{ old('decision_comment') }}</textarea>
                                <div class="dh-decision-actions"><button class="dh-btn dh-btn-accept" type="submit">Approve</button></div>
                            </form>
                            <form class="dh-decision-box" method="POST" action="{{ route('designer-head.requests.reject', $request) }}">
                                @csrf
                                <div class="dh-decision-title">Reject Request</div>
                                <label class="dh-label">Rejection Reason *</label>
                                <textarea class="dh-select-field" name="decision_reason" placeholder="Enter the reason for rejecting" required>{{ old('decision_reason') }}</textarea>
                                <div class="dh-decision-actions"><button class="dh-btn dh-btn-decline" type="submit">Reject</button></div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="dh-empty">No pending approval requests.</div>
                @endforelse
            </div>
        </section>

        <section class="dh-card">
            <div class="dh-card-head">
                <div>
                    <div class="dh-card-title">Recent Decisions</div>
                    <div class="dh-card-sub">Latest approved &amp; rejected requests</div>
                </div>
            </div>
            <div class="dh-table-wrap">
                <table class="dh-table">
                    <thead>
                    <tr><th>Type</th><th>Task</th><th>Requested By</th><th>Requested At</th><th>Result</th><th>Response By</th><th>Responded</th><th>Current Designer</th></tr>
                    </thead>
                    <tbody>
                    @forelse($recentDecisions as $request)
                        @php
                            $approved = $request->overall_status === 'approved';
                            $finalDesigner = $approved
                                ? ($request->approvedDesigner?->name ?? '—')
                                : ($request->task?->designer?->name ?? '—');
                        @endphp
                        <tr>
                            <td>{{ ucfirst($request->request_type) }}</td>
                            <td>
                                @if($request->task)
                                    <a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $request->task->status, 'task' => $request->task->task_id]) }}">{{ $request->task->task_id }}</a>
                                @else — @endif
                            </td>
                            <td>{{ $request->requester?->name ?? '—' }}</td>
                            <td>{{ optional($request->created_at)->format('d M, h:i A') }}</td>
                            <td><span class="dh-pill {{ $approved ? 'dh-pill-completed' : 'dh-pill-overdue' }}">{{ $approved ? 'Approved' : 'Rejected' }}</span></td>
                            <td>{{ ($request->adminActor ?: $request->designerHeadActor)?->name ?? 'System' }}</td>
                            <td>{{ optional($request->responded_at)->format('d M, h:i A') }}</td>
                            <td>{{ $finalDesigner }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="dh-empty">No decisions recorded yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- 5. Task details table --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Designer Task Details</div>
                <div class="dh-card-sub">Current assignment, progress, rework and rating per task · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            <div class="dh-card-badge">{{ $taskRows->count() }}</div>
        </div>
        <div class="dh-table-wrap dh-scroll" style="max-height:560px">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Task ID</th><th>Task Name</th><th>Designer</th><th>Assigned By (BD)</th><th>Assigned At</th><th>Deadline</th>
                    <th>Progress</th><th>Creatives</th><th>Status</th><th>Completed At</th><th>Overdue</th><th>Rework</th><th>Rating</th>
                </tr>
                </thead>
                <tbody>
                @forelse($taskRows as $row)
                    @php $task = $row['task']; @endphp
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
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
                    <tr><td colspan="13"><div class="dh-empty">No Designer tasks found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 6. Rework analytics --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Rework Analytics</div>
                <div class="dh-card-sub">Every task that entered rework, from the recorded rework history · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($reworkRows->isNotEmpty())<div class="dh-card-badge">{{ $reworkRows->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Designer</th><th>Task</th><th>Rework #</th><th>Rework Assigned At</th><th>Rework Creative Count</th><th>Sent By (BD)</th><th>Current Status</th></tr>
                </thead>
                <tbody>
                @forelse($reworkRows as $row)
                    <tr>
                        <td>{{ $row['task']->designer?->name ?? '—' }}</td>
                        <td><a class="dh-task-link" href="{{ route('designer-head.tasks.show', ['task' => $row['task'], 'tab' => 'eod']) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
                        <td><span class="dh-strong">Rework {{ $row['rework_number'] }}</span></td>
                        <td>{{ optional($row['rework_assigned_at'])->format('d M Y · h:i A') ?? '—' }}</td>
                        <td>{{ $row['rework_creatives'] ?? '—' }}</td>
                        <td>{{ $row['bd'] ?? '—' }}</td>
                        <td>{!! $statusPill($row['task']->status) !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="dh-empty">No rework records found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- 7. Ratings --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Completed Task Ratings</div>
                <div class="dh-card-sub">BD feedback on completed work</div>
            </div>
            <select class="dh-select" id="dhr-designer" aria-label="Filter ratings by Designer">
                <option value="all" @selected($selectedDesigner === null)>All Designers</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected((int) $selectedDesigner === (int) $designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>
        </div>
        <div id="dhr-root">
            @include('designer-head.ratings-rows', $completedRatings)
        </div>
    </section>

    {{-- 8. Completion activity --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">Recent Completion Activity</div>
                <div class="dh-card-sub">Latest finished tasks with real completion timestamps</div>
            </div>
        </div>
        <div class="dh-table-wrap">
            <table class="dh-table">
                <thead>
                <tr><th>Task</th><th>Designer</th><th>Assigned BD</th><th>Assigned At</th><th>Due Date</th><th>Completed At</th><th>Duration</th><th>Rating</th><th>Rework</th></tr>
                </thead>
                <tbody>
                @forelse($completions as $row)
                    <tr>
                        <td><a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $row['task']->status, 'task' => $row['task']->task_id]) }}">{{ $row['task']->task_id }}</a><div class="dh-cell-sub">{{ $row['task']->display_task_name ?? $row['task']->task_name }}</div></td>
                        <td>{{ $row['task']->designer?->name ?? '—' }}</td>
                        <td>{{ $row['task']->assigner?->name ?? '—' }}</td>
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

    {{-- 9. BD review turnaround --}}
    <section class="dh-card">
        <div class="dh-card-head">
            <div>
                <div class="dh-card-title">BD Review Turnaround</div>
                <div class="dh-card-sub">Time BD takes to review each submission — kept separate from Designer's own timeliness · Data for {{ $selectedMonthLabel }} • {{ $selectedDesignerName ?? 'All Designers' }}</div>
            </div>
            @if($bdReviewRows->isNotEmpty())<div class="dh-card-badge">{{ $bdReviewRows->count() }}</div>@endif
        </div>
        <div class="dh-table-wrap dh-scroll" style="max-height:560px">
            <table class="dh-table">
                <thead>
                <tr>
                    <th>Task</th><th>Task Created At</th><th>Task Deadline</th><th>Moved to BD Review At</th>
                    <th>BD Review Duration</th><th>BD Decision</th><th>Designer Submission</th>
                </tr>
                </thead>
                <tbody>
                @forelse($bdReviewRows as $row)
                    @php $task = $row['task']; @endphp
                    <tr>
                        <td>
                            <a class="dh-task-link" href="{{ route('designer-head.assigned-tasks', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a>
                            <div class="dh-cell-sub">{{ $task->display_task_name ?? $task->task_name }}</div>
                            <div class="dh-cell-sub">{{ $task->designer?->name ?? '—' }} · {{ $task->assigner?->name ?? '—' }}</div>
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
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="dh-empty">No BD review activity recorded yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>