@extends('layouts.app')

@section('title','Designer Dashboard')
@section('workspace-title','Designer Dashboard')
@section('workspace-subtitle','A simple overview of your tasks, requests and deadlines')

@section('content')
<style>
    .bd-dashboard{display:flex;flex-direction:column;gap:14px}
    .bd-dash-head{display:flex;align-items:center;justify-content:space-between;gap:14px}
    .bd-dash-head h1{margin:0;font-size:21px;font-weight:900;color:#101828}
    .bd-dash-head p{margin:4px 0 0;font-size:10px;color:#667085}
    .bd-dash-actions{display:flex;gap:8px}
    .bd-dash-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:9px;font-weight:900}
    .bd-dash-btn.primary{background:#e30613;color:#fff}
    .bd-dash-btn.secondary{background:#fff;color:#344054;border:1px solid #d0d5dd}

    .bd-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px}
    .bd-kpis.bd-kpis-8{grid-template-columns:repeat(8,minmax(0,1fr))}
    .bd-kpis.bd-kpis-9{grid-template-columns:repeat(9,minmax(0,1fr))}
    .bd-kpi{background:#fff;border:1px solid #e4e7ec;border-radius:12px;padding:12px 13px}
    .bd-kpi-link{display:block;text-decoration:none;color:inherit;cursor:pointer;transition:.15s}
    .bd-kpi-link:hover{border-color:#e30613;box-shadow:0 4px 14px rgba(227,6,19,.1);transform:translateY(-1px)}
    .bd-kpi-label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.045em;color:#667085}
    .bd-kpi-value{font-size:22px;font-weight:950;color:#101828;margin-top:5px}
    .bd-kpi-note{font-size:8px;color:#98a2b3;margin-top:3px}

    .bd-card{background:#fff;border:1px solid #e4e7ec;border-radius:13px;overflow:hidden}
    .bd-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .bd-card-title{font-size:11px;font-weight:900;color:#101828}
    .bd-card-link{font-size:8px;font-weight:900;text-decoration:none;color:#e30613}
    .bd-card-body{padding:12px}

    .bd-table-wrap{overflow:auto;max-height:480px}
    .bd-table{width:100%;border-collapse:collapse;min-width:900px}
    .bd-table th{padding:8px 9px;text-align:left;border-bottom:1px solid #eaecf0;font-size:8px;color:#667085;text-transform:uppercase;letter-spacing:.035em;font-weight:900;position:sticky;top:0;background:#fff;z-index:1}
    .bd-table td{padding:9px;border-bottom:1px solid #f1f2f4;font-size:9px;color:#344054;vertical-align:middle}
    .bd-table tbody tr:last-child td{border-bottom:0}
    .bd-task-link{font-weight:900;color:#101828;text-decoration:none}
    .bd-task-link:hover{color:#e30613}

    .bd-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;white-space:nowrap}
    .pill-assigned{background:#f2f4f7;color:#475467}
    .pill-progress{background:#eef4ff;color:#3538cd}
    .pill-completed{background:#ecfdf3;color:#027a48}
    .pill-waiting{background:#f4f0ff;color:#6938ef}
    .pill-rework{background:#fff6ed;color:#b54708}
    .pill-overdue{background:#fff1f3;color:#c01048}
    .pill-approved{background:#ecfdf3;color:#027a48}
    .pill-rejected{background:#fff1f3;color:#c01048}
    .pill-default{background:#f9fafb;color:#475467;border:1px solid #eaecf0}

    .bd-lower{display:grid;grid-template-columns:.8fr 1fr 1.1fr;gap:10px}
    .bd-req-history{padding:11px 0;border-bottom:1px solid #f2f4f7}
    .bd-req-history:last-child{border-bottom:0}
    .bd-req-history-top{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
    .bd-req-history-title{font-size:10px;font-weight:900;color:#101828}
    .bd-req-history-type{font-size:8px;color:#667085;margin-top:2px}
    .bd-req-history-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3px 14px;margin-top:7px}
    .bd-req-history-line{font-size:8px;color:#667085}
    .bd-req-history-line b{color:#344054;font-weight:800}
    .bd-request-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #f2f4f7}
    .bd-request-row:last-child{border-bottom:0}
    .bd-request-name{font-size:9px;font-weight:850;color:#344054}
    .bd-request-count{font-size:16px;font-weight:950;color:#101828}
    .bd-request-meta{font-size:8px;color:#98a2b3;margin-top:2px}

    .bd-status-row{display:grid;grid-template-columns:135px 36px 1fr;align-items:center;gap:8px;padding:5px 0}
    .bd-status-label{font-size:8px;color:#475467;font-weight:750}
    .bd-status-count{font-size:8px;font-weight:900;text-align:right;color:#101828}
    .bd-bar{height:6px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .bd-bar > span{display:block;height:100%;border-radius:999px;background:#e30613}

    .bd-recent-request{padding:9px 0;border-bottom:1px solid #f2f4f7}
    .bd-recent-request:last-child{border-bottom:0}
    .bd-recent-request-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
    .bd-recent-request-title{font-size:9px;font-weight:900;color:#101828}
    .bd-recent-request-sub{font-size:8px;color:#667085;margin-top:3px}
    .bd-request-state{font-size:7px;font-weight:900;padding:3px 6px;border-radius:999px;background:#fffaeb;color:#b54708}

    .bd-empty{text-align:center;color:#98a2b3;font-size:9px;padding:20px 8px}

    .bd-scroll-y{max-height:520px;overflow-y:auto}

    .bd-progress-card{display:flex;align-items:center;gap:14px;padding:14px 16px}
    .bd-progress-label{font-size:9px;font-weight:900;color:#344054;white-space:nowrap}
    .bd-progress-track{flex:1;height:10px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .bd-progress-track > span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#e30613,#ff6b7d)}
    .bd-progress-value{font-size:13px;font-weight:950;color:#101828;min-width:34px;text-align:right}

    .bd-donut-wrap{display:flex;align-items:center;gap:14px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f2f4f7;flex-wrap:wrap}
    .bd-donut-legend{display:flex;flex-direction:column;gap:4px;flex:1;min-width:120px}
    .bd-donut-legend-row{display:flex;align-items:center;gap:6px;font-size:8px;color:#475467}
    .bd-donut-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
    .bd-donut-legend-count{margin-left:auto;font-weight:900;color:#101828}

    .bd-line-chart{width:100%;height:auto;display:block}
    .bd-line-chart .grid-line{stroke:#f1f2f4;stroke-width:1}
    .bd-line-chart .trend-line{fill:none;stroke:#e30613;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .bd-line-chart .trend-point{fill:#e30613;stroke:#fff;stroke-width:1.5}
    .bd-line-chart .trend-label{font-size:7px;fill:#98a2b3;font-weight:700}
    .bd-line-chart .trend-value{font-size:7px;fill:#344054;font-weight:900}

    .bd-hist-row{margin-bottom:10px}
    .bd-hist-row:last-child{margin-bottom:0}
    .bd-hist-top{display:flex;justify-content:space-between;font-size:8px;font-weight:850;color:#344054;margin-bottom:4px}
    .bd-hist-track{display:flex;height:10px;border-radius:999px;overflow:hidden;background:#f2f4f7}
    .bd-hist-track span{height:100%}
    .bd-hist-legend{display:flex;gap:10px;margin-top:10px;font-size:7px;color:#667085}
    .bd-hist-legend span{display:inline-flex;align-items:center;gap:4px}
    .bd-hist-legend i{width:7px;height:7px;border-radius:2px;display:inline-block}

    .bd-reviews-wrap{overflow:hidden}
    .bd-reviews-track{display:flex;gap:12px;width:max-content;animation:bd-reviews-scroll linear infinite}
    .bd-reviews-track.bd-reviews-static{animation:none}
    .bd-reviews-wrap:hover .bd-reviews-track{animation-play-state:paused}
    @keyframes bd-reviews-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .bd-review-card{flex:0 0 260px;background:#fcfcfd;border:1px solid #eaecf0;border-radius:10px;padding:12px}
    .bd-review-stars{display:flex;gap:2px;margin-bottom:6px}
    .bd-review-star{--star-fill:0%;display:inline-block;width:13px;height:13px;flex:0 0 13px;font-size:13px;line-height:13px;font-family:Arial,"Segoe UI Symbol",sans-serif;background:linear-gradient(90deg,#f5b301 0%,#f5b301 var(--star-fill),#d8dee8 var(--star-fill),#d8dee8 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
    .bd-review-comment{font-size:9px;color:#344054;line-height:1.5;margin-bottom:8px;min-height:36px}
    .bd-review-meta{font-size:7px;color:#98a2b3;margin-top:2px}

    @media(max-width:1200px){.bd-kpis,.bd-kpis.bd-kpis-8,.bd-kpis.bd-kpis-9{grid-template-columns:repeat(3,1fr)}.bd-lower{grid-template-columns:1fr 1fr}}
    @media(max-width:760px){.bd-dash-head{align-items:flex-start;flex-direction:column}.bd-kpis,.bd-kpis.bd-kpis-8,.bd-kpis.bd-kpis-9{grid-template-columns:repeat(2,1fr)}.bd-lower{grid-template-columns:1fr}}
</style>

<div class="bd-dashboard">
    <div class="bd-dash-head">
        <div>
            <h1>Welcome back, {{ auth()->user()->name }}</h1>
            <p>All important task and request information in one place.</p>
        </div>
        <div class="bd-dash-actions">
            <a class="bd-dash-btn secondary" href="{{ route('designer.tasks.index') }}">View All Tasks</a>
        </div>
    </div>

    <div class="bd-kpis bd-kpis-9">
        <div class="bd-kpi"><div class="bd-kpi-label">Total Tasks</div><div class="bd-kpi-value">{{ $stats['total'] }}</div><div class="bd-kpi-note">Tasks assigned to you</div></div>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'assigned_tasks']) }}"><div class="bd-kpi-label">Assigned</div><div class="bd-kpi-value">{{ $stats['assigned'] }}</div><div class="bd-kpi-note">Awaiting your action</div></a>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'yet_to_start']) }}"><div class="bd-kpi-label">Ready to Start</div><div class="bd-kpi-value">{{ $stats['ready_to_start'] }}</div><div class="bd-kpi-note">Ready to begin work</div></a>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'in_progress']) }}"><div class="bd-kpi-label">In Progress</div><div class="bd-kpi-value">{{ $stats['in_progress'] }}</div><div class="bd-kpi-note">Currently being worked on</div></a>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'rework']) }}"><div class="bd-kpi-label">Rework</div><div class="bd-kpi-value">{{ $stats['rework'] }}</div><div class="bd-kpi-note">Tasks currently in rework</div></a>
        <div class="bd-kpi"><div class="bd-kpi-label">Rework Creatives</div><div class="bd-kpi-value">{{ $stats['rework_creatives'] }}</div><div class="bd-kpi-note">Creatives pending resubmission</div></div>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'completed']) }}"><div class="bd-kpi-label">Completed</div><div class="bd-kpi-value">{{ $stats['completed'] }}</div><div class="bd-kpi-note">Finished tasks</div></a>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'waiting_confirmation']) }}"><div class="bd-kpi-label">Waiting for BD Review</div><div class="bd-kpi-value">{{ $stats['waiting_bd_review'] }}</div><div class="bd-kpi-note">Completed by you, awaiting BD</div></a>
        <div class="bd-kpi">
            <div class="bd-kpi-label">Overall Rating</div>
            <div class="bd-kpi-value">{{ $overallRating['average'] !== null ? '★ '.$overallRating['average'] : '—' }}</div>
            <div class="bd-kpi-note">
                @if($overallRating['average'] !== null)
                    Rated {{ $overallRating['rated'] }} / {{ $overallRating['total'] }} completed
                @else
                    No ratings yet
                @endif
            </div>
        </div>
    </div>

    <section class="bd-card">
        <div class="bd-card-body bd-progress-card">
            <div class="bd-progress-label">Completion Rate</div>
            <div class="bd-progress-track"><span style="width:{{ $completionRate }}%"></span></div>
            <div class="bd-progress-value">{{ $completionRate }}%</div>
        </div>
    </section>

    <div class="bd-kpis">
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'swap_tasks']) }}"><div class="bd-kpi-label">Swapped Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['swap'] }}</div><div class="bd-kpi-note">Swap requests raised by you</div></a>
        <a class="bd-kpi bd-kpi-link" href="{{ route('designer.tasks.index', ['focus' => 'self_declined']) }}"><div class="bd-kpi-label">Declined Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['decline'] }}</div><div class="bd-kpi-note">Decline requests raised by you</div></a>
        <div class="bd-kpi"><div class="bd-kpi-label">Split Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['split'] }}</div><div class="bd-kpi-note">Split requests raised by you</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Approved Requests</div><div class="bd-kpi-value">{{ $requestTypeCounts['approved'] }}</div><div class="bd-kpi-note">Across swap/split/decline</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Overall Rework</div><div class="bd-kpi-value">{{ $overallRework['cycles'] }}</div><div class="bd-kpi-note">All-time rework cycles</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Overall Rework Creatives</div><div class="bd-kpi-value">{{ $overallRework['creatives'] }}</div><div class="bd-kpi-note">All-time creatives sent for rework</div></div>
    </div>

    <section class="bd-card">
        <div class="bd-card-head">
            <div class="bd-card-title">Tasks Overview</div>
            <a class="bd-card-link" href="{{ route('designer.tasks.index') }}">View All Tasks</a>
        </div>

        <div class="bd-table-wrap">
            <table class="bd-table">
                <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Task</th>
                    <th>Client / Agency</th>
                    <th>Vertical</th>
                    <th>Task Nature</th>
                    <th>Assigned By</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentTasks as $task)
                    @php
                        $isOverdue = $task->status !== 'completed' && $task->due_at && $task->due_at->isPast();
                        $statusClass = match($task->status) {
                            'assigned_tasks' => 'pill-assigned',
                            'in_progress' => 'pill-progress',
                            'completed' => 'pill-completed',
                            'waiting_confirmation' => 'pill-waiting',
                            'rework' => 'pill-rework',
                            default => 'pill-default',
                        };
                    @endphp
                    <tr>
                        <td><a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
                        <td>{{ $task->display_task_name ?? $task->task_name }}</td>
                        <td>{{ $task->party_name }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$task->task_nature)) }}</td>
                        <td>{{ $task->assigner?->name ?? '—' }}</td>
                        <td>
                            <span class="bd-pill {{ $isOverdue ? 'pill-overdue' : $statusClass }}">
                                {{ $isOverdue ? 'Overdue' : ucwords(str_replace('_',' ',$task->status === 'swap_tasks' ? 'swapped_tasks' : $task->status)) }}
                            </span>
                        </td>
                        <td>{{ ucfirst($task->priority) }}</td>
                        <td style="{{ $isOverdue ? 'color:#c01048;font-weight:850' : '' }}">{{ $task->due_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="bd-empty">No tasks assigned yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bd-card">
        <div class="bd-card-head"><div class="bd-card-title">Your Task Details</div></div>
        <div class="bd-table-wrap">
            <table class="bd-table">
                <thead>
                <tr>
                    <th>Task ID</th><th>Task Name</th><th>Assigned At</th><th>Status</th><th>Progress</th>
                    <th>Creatives</th><th>Deadline</th><th>Completed At</th><th>Overdue</th><th>Rework</th><th>Rating</th>
                </tr>
                </thead>
                <tbody>
                @forelse($taskRows as $row)
                    @php
                        $task = $row['task'];
                        $rowRatingValue = $row['rating'] !== null ? max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($row['rating']))) : null;
                    @endphp
                    <tr>
                        <td><a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
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
                        <td style="{{ $row['overdue'] ? 'color:#c01048;font-weight:850' : '' }}">{{ $task->due_at?->format('d M Y') ?? '—' }}</td>
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
                    <tr><td colspan="11"><div class="bd-empty">No tasks assigned yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bd-card">
        <div class="bd-card-head"><div class="bd-card-title">Performance Trend</div></div>
        <div class="bd-card-body">
            @include('shared.performance-trend', [
                'trendCards' => $trendCards,
                'trendData' => $line,
                'trendContext' => $trendContext,
            ])
        </div>
    </section>

    <section class="bd-card">
        <div class="bd-card-head"><div class="bd-card-title">Designer Reviews</div></div>
        <div class="bd-card-body">
            @php $reviewCount = $reviewCards->count(); @endphp
            @if($reviewCount === 0)
                <div class="bd-empty">No BD comments yet on your completed tasks.</div>
            @else
                <div class="bd-reviews-wrap">
                    <div class="bd-reviews-track {{ $reviewCount <= 1 ? 'bd-reviews-static' : '' }}"
                         @if($reviewCount > 1) style="animation-duration:{{ $reviewCount * 6 }}s" @endif>
                        @foreach(($reviewCount > 1 ? $reviewCards->concat($reviewCards) : $reviewCards) as $review)
                            @php $ratingValue = max(0, min(5, $review['rating'])); @endphp
                            <div class="bd-review-card">
                                <div class="bd-review-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        @php $fill = $ratingValue >= $i ? 100 : ($ratingValue >= $i - 0.5 ? 50 : 0); @endphp
                                        <span class="bd-review-star" style="--star-fill:{{ $fill }}%">★</span>
                                    @endfor
                                </div>
                                <div class="bd-review-comment">&ldquo;{{ $review['comment'] }}&rdquo;</div>
                                <div class="bd-review-meta">
                                    @if($review['task'])
                                        <a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $review['task']->status, 'task' => $review['task_id']]) }}">{{ $review['task_id'] }}</a>
                                    @else
                                        {{ $review['task_id'] }}
                                    @endif
                                    · {{ $review['task_name'] }}
                                </div>
                                <div class="bd-review-meta">{{ $review['reviewer'] }}</div>
                                <div class="bd-review-meta">Reviewed: {{ $review['reviewed_at']?->format('d M Y, g:i A') ?? '—' }}</div>
                                <div class="bd-review-meta">Completed: {{ $review['completed_at']?->format('d M Y, g:i A') ?? '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="bd-lower">
        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Requests Overview</div></div>
            <div class="bd-card-body">
                <div class="bd-request-row"><div><div class="bd-request-name">Swap Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['swap'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Split Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['split'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Decline Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['decline'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Total Pending</div></div><div class="bd-request-count">{{ array_sum($requestSummary) }}</div></div>
            </div>
        </section>

        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Request Outcomes</div></div>
            <div class="bd-card-body">
                @php $outcomeColors = ['approved' => '#027a48', 'rejected' => '#c01048', 'pending' => '#6938ef']; @endphp
                @foreach($requestOutcomes as $row)
                    @php $rowTotal = $row['approved'] + $row['rejected'] + $row['pending']; @endphp
                    <div class="bd-hist-row">
                        <div class="bd-hist-top"><span>{{ $row['label'] }}</span><span>{{ $rowTotal }} total</span></div>
                        <div class="bd-hist-track">
                            @if($rowTotal > 0)
                                <span style="width:{{ $row['approved'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['approved'] }}"></span>
                                <span style="width:{{ $row['rejected'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['rejected'] }}"></span>
                                <span style="width:{{ $row['pending'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['pending'] }}"></span>
                            @endif
                        </div>
                    </div>
                @endforeach
                <div class="bd-hist-legend">
                    <span><i style="background:{{ $outcomeColors['approved'] }}"></i>Approved</span>
                    <span><i style="background:{{ $outcomeColors['rejected'] }}"></i>Rejected</span>
                    <span><i style="background:{{ $outcomeColors['pending'] }}"></i>Pending</span>
                </div>
            </div>
        </section>

        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Task Status Summary</div></div>
            <div class="bd-card-body">
                @php
                    $donutColors = [
                        'assigned_tasks' => '#475467', 'review_analysis' => '#2970ff',
                        'need_clarification' => '#f79009', 'yet_to_start' => '#06aed4',
                        'in_progress' => '#3538cd', 'waiting_confirmation' => '#6938ef',
                        'rework' => '#b54708', 'completed' => '#027a48', 'swap_tasks' => '#c01048',
                    ];
                    $donutSegments = $statusSummary->filter(fn ($row) => $row['count'] > 0)->values();
                    $donutCircumference = 2 * M_PI * 26;
                    $donutCursor = 0;
                @endphp
                @if($stats['total'] > 0)
                    <div class="bd-donut-wrap">
                        <svg width="64" height="64" viewBox="0 0 64 64" style="flex-shrink:0">
                            <circle cx="32" cy="32" r="26" fill="none" stroke="#f2f4f7" stroke-width="10"/>
                            @foreach($donutSegments as $seg)
                                @php
                                    $segLen = ($seg['count'] / $stats['total']) * $donutCircumference;
                                @endphp
                                <circle cx="32" cy="32" r="26" fill="none" stroke="{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"
                                        stroke-width="10" stroke-dasharray="{{ $segLen }} {{ $donutCircumference - $segLen }}"
                                        stroke-dashoffset="{{ -$donutCursor }}" transform="rotate(-90 32 32)"/>
                                @php $donutCursor += $segLen; @endphp
                            @endforeach
                        </svg>
                        <div class="bd-donut-legend">
                            @foreach($donutSegments as $seg)
                                <div class="bd-donut-legend-row">
                                    <span class="bd-donut-dot" style="background:{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"></span>
                                    {{ $seg['label'] }}
                                    <span class="bd-donut-legend-count">{{ $seg['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach($statusSummary as $row)
                    @php
                        $percentage = $stats['total'] > 0 ? round(($row['count'] / $stats['total']) * 100) : 0;
                    @endphp
                    <div class="bd-status-row">
                        <div class="bd-status-label">{{ $row['label'] }}</div>
                        <div class="bd-status-count">{{ $row['count'] }}</div>
                        <div class="bd-bar"><span style="width:{{ $percentage }}%"></span></div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="bd-card">
        <div class="bd-card-head">
            <div class="bd-card-title">My Requests — Swap, Decline &amp; Split</div>
        </div>
        <div class="bd-card-body bd-scroll-y">
            @forelse($myRequests as $req)
                @php
                    $pending = in_array($req->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                    $pillClass = match(true) {
                        $req->overall_status === 'approved' => 'pill-approved',
                        $req->overall_status === 'rejected' => 'pill-rejected',
                        default => 'pill-waiting',
                    };
                    $resultLabel = $pending ? 'Pending' : ucfirst($req->overall_status);
                    $currentHandler = ($req->overall_status === 'approved' && in_array($req->request_type, ['swap','split'], true))
                        ? ($req->approvedDesigner?->name ?? '—')
                        : ($req->task?->designer?->name ?? '—');
                @endphp
                <div class="bd-req-history">
                    <div class="bd-req-history-top">
                        <div>
                            <div class="bd-req-history-title">
                                @if($req->task)
                                    <a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $req->task->status, 'task' => $req->task->task_id]) }}">{{ $req->task->task_id }}</a>
                                @else
                                    Task
                                @endif
                                — {{ $req->task?->display_task_name ?? $req->task?->task_name ?? 'Task unavailable' }}
                            </div>
                            <div class="bd-req-history-type">{{ ucfirst($req->request_type) }} Request</div>
                        </div>
                        <span class="bd-pill {{ $pillClass }}">{{ $resultLabel }}</span>
                    </div>
                    <div class="bd-req-history-grid">
                        <div class="bd-req-history-line">Requested by: <b>{{ $req->requester?->name ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Requested at: <b>{{ $req->created_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Responded by: <b>{{ $req->decided_by?->name ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Responded at: <b>{{ $req->responded_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Current handler: <b>{{ $currentHandler }}</b></div>
                    </div>
                </div>
            @empty
                <div class="bd-empty">No swap, decline or split requests yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
