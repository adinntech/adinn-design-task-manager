@extends('layouts.app')

@section('title','Designer Head Dashboard')
@section('workspace-title','Designer Head Dashboard')
@section('workspace-subtitle','Monitor workload, task movement and approval requests')

@section('content')
<style>
    .dh-dashboard{display:flex;flex-direction:column;gap:14px}
    .dh-top{display:flex;align-items:flex-end;justify-content:space-between;gap:14px}
    .dh-title{font-size:21px;font-weight:900;color:#101828;margin:0}
    .dh-sub{font-size:10px;color:#667085;margin-top:4px}
    .dh-actions{display:flex;gap:8px}
    .dh-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:9px;font-weight:900}
    .dh-btn-primary{background:#e30613;color:#fff}
    .dh-btn-secondary{background:#fff;color:#344054;border:1px solid #d0d5dd}

    .dh-kpis{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:9px}
    .dh-kpi{border:1px solid #e4e7ec;border-radius:12px;background:#fff;padding:12px;min-height:92px}
    .dh-kpi-icon{width:30px;height:30px;border-radius:9px;background:#f4f5f7;display:grid;place-items:center;font-size:14px;margin-bottom:8px}
    .dh-kpi-label{font-size:8px;font-weight:900;color:#667085;text-transform:uppercase;letter-spacing:.04em}
    .dh-kpi-value{font-size:21px;font-weight:950;color:#101828;margin-top:4px}
    .dh-kpi-note{font-size:8px;color:#98a2b3;margin-top:2px}

    .dh-lower{display:grid;grid-template-columns:.92fr 1.22fr 1.22fr;gap:10px}
    .dh-card{background:#fff;border:1px solid #e4e7ec;border-radius:13px;overflow:hidden;min-width:0}
    .dh-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .dh-card-title{font-size:11px;font-weight:900;color:#101828}
    .dh-card-link{font-size:8px;color:#e30613;font-weight:900;text-decoration:none}
    .dh-card-body{padding:10px 12px}

    .workload-head,.workload-row{display:grid;grid-template-columns:minmax(95px,1.35fr) 48px 55px 48px minmax(90px,1fr) 68px;gap:7px;align-items:center}
    .workload-head{padding:0 0 7px;font-size:7px;font-weight:900;color:#98a2b3;text-transform:uppercase;border-bottom:1px solid #f2f4f7}
    .workload-row{padding:8px 0;border-bottom:1px solid #f2f4f7;font-size:8px;color:#475467}
    .workload-row:last-child{border-bottom:0}
    .designer-name{font-weight:850;color:#101828;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .load-track{height:6px;background:#f2f4f7;border-radius:999px;overflow:hidden}
    .load-fill{height:100%;background:#e30613;border-radius:999px}
    .load-value{font-size:7px;color:#667085;margin-top:3px}
    .work-status{display:inline-flex;justify-content:center;padding:4px 6px;border-radius:999px;font-size:7px;font-weight:900}
    .status-busy{background:#fff1f3;color:#c01048}
    .status-working{background:#fffaeb;color:#b54708}
    .status-available{background:#ecfdf3;color:#027a48}

    .request-table-wrap{overflow:auto}
    .request-table{width:100%;border-collapse:collapse;min-width:620px}
    .request-table th{padding:7px 8px;font-size:7px;color:#98a2b3;text-transform:uppercase;text-align:left;border-bottom:1px solid #eaecf0;white-space:nowrap}
    .request-table td{padding:8px;font-size:8px;color:#475467;border-bottom:1px solid #f2f4f7;vertical-align:middle}
    .request-table tr:last-child td{border-bottom:0}
    .req-task{font-weight:850;color:#101828;text-decoration:none}
    .req-task:hover{color:#e30613}
    .req-status{display:inline-flex;padding:4px 6px;border-radius:999px;font-size:7px;font-weight:900;white-space:nowrap}
    .req-pending{background:#fffaeb;color:#b54708}
    .req-approved{background:#ecfdf3;color:#027a48}
    .req-rejected{background:#fff1f3;color:#c01048}
    .empty{padding:24px 8px;text-align:center;color:#98a2b3;font-size:9px}

    @media(max-width:1350px){.dh-kpis{grid-template-columns:repeat(4,1fr)}.dh-lower{grid-template-columns:1fr 1fr}.dh-card:first-child{grid-column:1/-1}}
    @media(max-width:850px){.dh-top{flex-direction:column;align-items:flex-start}.dh-kpis{grid-template-columns:repeat(2,1fr)}.dh-lower{grid-template-columns:1fr}.dh-card:first-child{grid-column:auto}}
</style>

<div class="dh-dashboard">
    <div class="dh-top">
        <div>
            <h1 class="dh-title">Designer Head Dashboard</h1>
            <div class="dh-sub">A clear overview of team workload and pending task split / transfer requests.</div>
        </div>
        <div class="dh-actions">
            <a class="dh-btn dh-btn-secondary" href="{{ route('designer-head.assigned-tasks') }}">View Assigned Tasks</a>
        </div>
    </div>

    <div class="dh-kpis">
        <div class="dh-kpi"><div class="dh-kpi-icon">▦</div><div class="dh-kpi-label">Total Tasks</div><div class="dh-kpi-value">{{ $stats['total'] }}</div><div class="dh-kpi-note">Across all Designers</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">＋</div><div class="dh-kpi-label">New Assignments</div><div class="dh-kpi-value">{{ $stats['new_assignments'] }}</div><div class="dh-kpi-note">Assigned Tasks</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">▶</div><div class="dh-kpi-label">In Progress</div><div class="dh-kpi-value">{{ $stats['in_progress'] }}</div><div class="dh-kpi-note">Currently active</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">↗</div><div class="dh-kpi-label">Updates Submitted Today</div><div class="dh-kpi-value">{{ $stats['submitted_today'] }}</div><div class="dh-kpi-note">Task updates today</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">✓</div><div class="dh-kpi-label">Waiting for Review</div><div class="dh-kpi-value">{{ $stats['pending_review'] }}</div><div class="dh-kpi-note">Waiting for BD Review</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">!</div><div class="dh-kpi-label">Overdue</div><div class="dh-kpi-value">{{ $stats['overdue'] }}</div><div class="dh-kpi-note">Past due date</div></div>
        <div class="dh-kpi"><div class="dh-kpi-icon">◇</div><div class="dh-kpi-label">Pending Approval</div><div class="dh-kpi-value">{{ $stats['approval_pending'] }}</div><div class="dh-kpi-note">Requests awaiting decision</div></div>
    </div>

    <div class="dh-lower">
        <section class="dh-card">
            <div class="dh-card-head">
                <div class="dh-card-title">Designer Workload</div>
            </div>
            <div class="dh-card-body">
                <div class="workload-head">
                    <div>Designer</div><div>Active</div><div>Completed</div><div>Overdue</div><div>Workload</div><div>Status</div>
                </div>

                @forelse($workload as $row)
                    <div class="workload-row">
                        <div class="designer-name">{{ $row['designer']->name }}</div>
                        <div>{{ $row['active'] }}</div>
                        <div>{{ $row['completed'] }}</div>
                        <div>{{ $row['overdue'] }}</div>
                        <div>
                            <div class="load-track"><div class="load-fill" style="width:{{ $row['load_percent'] }}%"></div></div>
                            <div class="load-value">{{ $row['load_percent'] }}%</div>
                        </div>
                        <div>
                            <span class="work-status status-{{ strtolower($row['status']) }}">{{ $row['status'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty">No active Designers found.</div>
                @endforelse
            </div>
        </section>

        <section class="dh-card">
            <div class="dh-card-head">
                <div class="dh-card-title">Task Transfer Requests</div>
                <a class="dh-card-link" href="{{ route('designer-head.assigned-tasks') }}">View All</a>
            </div>
            <div class="request-table-wrap">
                <table class="request-table">
                    <thead>
                        <tr><th>Task</th><th>Preferred Designer</th><th>Reason</th><th>Requested</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse($swapRequests as $request)
                        @php
                            $statusClass = in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true)
                                ? 'req-pending'
                                : ($request->overall_status === 'approved' ? 'req-approved' : 'req-rejected');
                        @endphp
                        <tr>
                            <td><a class="req-task" href="{{ $request->task ? route('designer-head.tasks.show',['task'=>$request->task,'tab'=>'swap-details']) : '#' }}">{{ $request->task?->task_id ?? '—' }}</a></td>
                            
                            <td>{{ $request->targetDesigner?->name ?? '—' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($request->reason,35) }}</td>
                            <td>{{ $request->created_at?->format('d M') }}</td>
                            <td><span class="req-status {{ $statusClass }}">{{ $request->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">No Swap requests found.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dh-card">
            <div class="dh-card-head">
                <div class="dh-card-title">Split Task Requests</div>
                <a class="dh-card-link" href="{{ route('designer-head.assigned-tasks') }}">View All</a>
            </div>
            <div class="request-table-wrap">
                <table class="request-table">
                    <thead>
                        <tr><th>Task</th><th>Designer</th><th>Split Qty</th><th>Reason</th><th>Requested</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse($splitRequests as $request)
                        @php
                            $statusClass = in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true)
                                ? 'req-pending'
                                : ($request->overall_status === 'approved' ? 'req-approved' : 'req-rejected');
                            $splitQty = data_get($request,'split_count')
                                ?? data_get($request,'split_details.requested_count')
                                ?? data_get($request,'split_details.creative_count')
                                ?? '—';
                        @endphp
                        <tr>
                            <td><a class="req-task" href="{{ $request->task ? route('designer-head.tasks.show',['task'=>$request->task,'tab'=>'split-details']) : '#' }}">{{ $request->task?->task_id ?? '—' }}</a></td>
                            
                            <td>{{ $splitQty }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($request->reason,35) }}</td>
                            <td>{{ $request->created_at?->format('d M') }}</td>
                            <td><span class="req-status {{ $statusClass }}">{{ $request->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">No Split requests found.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
