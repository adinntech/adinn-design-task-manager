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
    .bd-kpis.bd-kpis-4{grid-template-columns:repeat(4,minmax(0,1fr))}
    .bd-kpi{background:#fff;border:1px solid #e4e7ec;border-radius:12px;padding:12px 13px}
    .bd-kpi-label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.045em;color:#667085}
    .bd-kpi-value{font-size:22px;font-weight:950;color:#101828;margin-top:5px}
    .bd-kpi-note{font-size:8px;color:#98a2b3;margin-top:3px}

    .bd-card{background:#fff;border:1px solid #e4e7ec;border-radius:13px;overflow:hidden}
    .bd-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .bd-card-title{font-size:11px;font-weight:900;color:#101828}
    .bd-card-link{font-size:8px;font-weight:900;text-decoration:none;color:#e30613}
    .bd-card-body{padding:12px}

    .bd-table-wrap{overflow:auto}
    .bd-table{width:100%;border-collapse:collapse;min-width:900px}
    .bd-table th{padding:8px 9px;text-align:left;border-bottom:1px solid #eaecf0;font-size:8px;color:#667085;text-transform:uppercase;letter-spacing:.035em;font-weight:900}
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

    .bd-lower{display:grid;grid-template-columns:.8fr 1.2fr 1fr;gap:10px}
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

    @media(max-width:1200px){.bd-kpis{grid-template-columns:repeat(3,1fr)}.bd-lower{grid-template-columns:1fr 1fr}}
    @media(max-width:760px){.bd-dash-head{align-items:flex-start;flex-direction:column}.bd-kpis{grid-template-columns:repeat(2,1fr)}.bd-lower{grid-template-columns:1fr}}
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

    <div class="bd-kpis">
        <div class="bd-kpi"><div class="bd-kpi-label">Total Tasks</div><div class="bd-kpi-value">{{ $stats['total'] }}</div><div class="bd-kpi-note">Tasks assigned to you</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Assigned</div><div class="bd-kpi-value">{{ $stats['assigned'] }}</div><div class="bd-kpi-note">Awaiting your action</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">In Progress</div><div class="bd-kpi-value">{{ $stats['in_progress'] }}</div><div class="bd-kpi-note">Currently being worked on</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Completed</div><div class="bd-kpi-value">{{ $stats['completed'] }}</div><div class="bd-kpi-note">Finished tasks</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Pending Requests</div><div class="bd-kpi-value">{{ $stats['pending_approval'] }}</div><div class="bd-kpi-note">Your open requests</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Overdue</div><div class="bd-kpi-value">{{ $stats['overdue'] }}</div><div class="bd-kpi-note">Past due date</div></div>
    </div>

    <div class="bd-kpis bd-kpis-4">
        <div class="bd-kpi"><div class="bd-kpi-label">Swapped Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['swap'] }}</div><div class="bd-kpi-note">Swap requests raised by you</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Declined Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['decline'] }}</div><div class="bd-kpi-note">Decline requests raised by you</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Split Tasks</div><div class="bd-kpi-value">{{ $requestTypeCounts['split'] }}</div><div class="bd-kpi-note">Split requests raised by you</div></div>
        <div class="bd-kpi"><div class="bd-kpi-label">Approved Requests</div><div class="bd-kpi-value">{{ $requestTypeCounts['approved'] }}</div><div class="bd-kpi-note">Across swap/split/decline</div></div>
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
                        <td><a class="bd-task-link" href="{{ route('designer.tasks.show',$task) }}">{{ $task->task_id }}</a></td>
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
            <div class="bd-card-head"><div class="bd-card-title">Recent Requests</div></div>
            <div class="bd-card-body">
                @forelse($recentRequests as $request)
                    <div class="bd-recent-request">
                        <div class="bd-recent-request-top">
                            <div class="bd-recent-request-title">{{ ucfirst($request->request_type) }} Request · {{ $request->task?->task_id ?? 'Task' }}</div>
                            <span class="bd-request-state">{{ $request->status_label }}</span>
                        </div>
                        <div class="bd-recent-request-sub">
                            {{ $request->task?->task_name ?? 'Task unavailable' }}
                            · {{ $request->created_at?->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="bd-empty">No request activity yet.</div>
                @endforelse
            </div>
        </section>

        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Task Status Summary</div></div>
            <div class="bd-card-body">
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
        <div class="bd-table-wrap">
            <table class="bd-table">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Current Handler</th>
                    <th>Requested On</th>
                </tr>
                </thead>
                <tbody>
                @forelse($myRequests as $req)
                    @php
                        $pillClass = match($req->overall_status) {
                            'approved' => 'pill-approved',
                            'rejected' => 'pill-rejected',
                            default => 'pill-waiting',
                        };
                        $currentHandler = ($req->overall_status === 'approved' && in_array($req->request_type, ['swap','split'], true))
                            ? ($req->approvedDesigner?->name ?? '—')
                            : ($req->task?->designer?->name ?? '—');
                    @endphp
                    <tr>
                        <td>{{ ucfirst($req->request_type) }}</td>
                        <td>
                            @if($req->task)
                                <a class="bd-task-link" href="{{ route('designer.tasks.show',$req->task) }}">{{ $req->task->task_id }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="bd-pill {{ $pillClass }}">{{ $req->status_label }}</span></td>
                        <td>{{ $currentHandler }}</td>
                        <td>{{ $req->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="bd-empty">No swap, decline or split requests yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
