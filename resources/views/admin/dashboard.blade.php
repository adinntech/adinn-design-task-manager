<style>.task-operation-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent;vertical-align:middle;white-space:nowrap}.task-operation-pill-split{color:#6938ef;background:linear-gradient(135deg,#f4f0ff,#ede9fe);border-color:#d9d6fe}.task-operation-pill-swap{color:#175cd3;background:linear-gradient(135deg,#eff8ff,#e6f1ff);border-color:#b2ddff}</style>
@extends('layouts.app')
@section('title','Admin Dashboard')
@section('workspace-title','Manager Dashboard')
@section('workspace-subtitle','Monitor users, tasks, designers and the complete design process')
@section('content')
<div class="page-head">
    <div><h1>Manager Dashboard</h1><p>A compact operational view of the Design Task Manager.</p></div>
    <div class="page-actions"><a href="{{ route('admin.users.create') }}" class="btn btn-secondary">Add User</a><a href="{{ route('admin.tasks.index') }}" class="btn btn-primary">View All Tasks</a></div>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-label">Total Tasks</div><div class="metric-value">{{ $stats['total_tasks'] }}</div><div class="metric-note">Across all verticals</div></div>
    <div class="metric-card"><div class="metric-label">Active Tasks</div><div class="metric-value">{{ $stats['active_tasks'] }}</div><div class="metric-note">Not yet completed</div></div>
    <div class="metric-card"><div class="metric-label">Waiting Confirmation</div><div class="metric-value">{{ $stats['waiting_confirmation'] }}</div><div class="metric-note">Awaiting BD/client action</div></div>
    <div class="metric-card"><div class="metric-label">Rework</div><div class="metric-value">{{ $stats['rework'] }}</div><div class="metric-note">Returned for correction</div></div>
    <div class="metric-card"><div class="metric-label">Overdue</div><div class="metric-value">{{ $stats['overdue'] }}</div><div class="metric-note">Open tasks past due date</div></div>
    <div class="metric-card"><div class="metric-label">Completed</div><div class="metric-value">{{ $stats['completed'] }}</div><div class="metric-note">Closed design tasks</div></div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-header"><div><div class="panel-title">Project Pipeline</div><div class="metric-note">Live task count by workflow stage</div></div><a class="btn btn-secondary" href="{{ route('admin.tasks.index') }}">Open Monitoring</a></div>
        <div class="panel-body"><div class="pipeline-mini">@foreach($pipeline as $key=>$item)<div class="pipeline-mini-card"><span>{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong></div>@endforeach</div></div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><div class="panel-title">Team Snapshot</div><div class="metric-note">Current active users</div></div></div>
        <div class="panel-body"><div class="info-grid"><div class="info-item"><span>Active Designers</span><strong>{{ $stats['active_designers'] }}</strong></div><div class="info-item"><span>Active BD</span><strong>{{ $stats['active_bd'] }}</strong></div></div><div style="margin-top:12px"><a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="width:100%">Manage Users</a></div></div>
    </section>
</div>

<section class="panel" style="margin-top:16px">
    <div class="panel-header">
        <div>
            <div class="panel-title">Designer Request Approvals</div>
            <div class="metric-note">Admin and Designer Head have equal approval authority. The first decision finalizes the request.</div>
        </div>
        <span class="badge {{ $requestStats['pending'] > 0 ? 'badge-warning' : 'badge-success' }}">{{ $requestStats['pending'] }} Pending</span>
    </div>
    <div class="panel-body">
        <div class="info-grid" style="margin-bottom:14px">
            <div class="info-item"><span>Decline Pending</span><strong>{{ $requestStats['decline'] }}</strong></div>
            <div class="info-item"><span>Split Pending</span><strong>{{ $requestStats['split'] }}</strong></div>
            <div class="info-item"><span>Swap Pending</span><strong>{{ $requestStats['swap'] }}</strong></div>
        </div>

        <div class="table-wrap">
            <table class="premium-table" style="min-width:980px">
                <thead><tr><th>Task</th><th>Request</th><th>Designer</th><th>Reason / Details</th><th>Raised</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($pendingRequests as $item)
                        <tr>
                            <td><a class="file-link" href="{{ route('admin.tasks.show', $item->task) }}">{{ $item->task?->task_id ?? '—' }}</a><div style="margin-top:3px;font-weight:700">{{ $item->task?->display_task_name ?? 'Task removed' }}</div></td>
                            <td><span class="badge badge-warning">{{ ucfirst($item->request_type) }} · Pending</span></td>
                            <td>{{ $item->requester?->name ?? '—' }}</td>
                            <td style="max-width:320px"><div>{{ \Illuminate\Support\Str::limit($item->reason, 150) }}</div>@if($item->request_type === 'split' && !empty($item->split_details['creative_count']))<div class="muted" style="margin-top:4px">Split {{ $item->split_details['creative_count'] }} creatives</div>@endif @if($item->targetDesigner)<div class="muted" style="margin-top:4px">Preferred: {{ $item->targetDesigner->name }}</div>@endif</td>
                            <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                            <td>
                                <div style="display:grid;gap:6px;min-width:210px">
                                    @if(in_array($item->request_type, ['split','swap'], true))
                                        <div class="muted">Preferred: <strong style="color:#111827">{{ $item->targetDesigner?->name ?? 'No preference' }}</strong></div>
                                        <form method="POST" action="{{ route('admin.requests.approve', $item) }}" onsubmit="return confirm('Approve this request and assign it to the selected Designer? This is the final decision.');">
                                            @csrf
                                            <select name="approved_designer_id" class="field" required style="margin-bottom:6px">
                                                <option value="">Select approved Designer</option>
                                                @foreach($approvalDesigners as $designer)
                                                    @if((int)$designer->id !== (int)($item->task?->designer_id ?? 0))
                                                        <option value="{{ $designer->id }}" @selected((int)$designer->id === (int)($item->target_designer_id ?? 0))>{{ $designer->name }}{{ (int)$designer->id === (int)($item->target_designer_id ?? 0) ? ' · Preferred' : '' }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button class="btn btn-primary" style="width:100%">Approve</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.requests.approve', $item) }}" onsubmit="return confirm('Approve this request? This is the final decision.');">@csrf<button class="btn btn-primary" style="width:100%">Approve</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.requests.reject', $item) }}" onsubmit="return confirm('Reject this request? This is the final decision.');">@csrf<button class="btn btn-danger" style="width:100%">Reject</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">No Designer requests are waiting for approval.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="content-grid-3">
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Recent Tasks</div></div>
        <div class="panel-body" style="padding:0"><div class="table-wrap" style="border:0;border-radius:0 0 16px 16px"><table class="premium-table" style="min-width:650px"><thead><tr><th>Task</th><th>Designer</th><th>Status</th><th>Due</th></tr></thead><tbody>@forelse($recentTasks as $task)<tr><td><a class="file-link" href="{{ route('admin.tasks.show',$task) }}">{{ $task->task_id }}</a><div style="margin-top:3px;font-weight:700;display:flex;align-items:center;gap:6px;flex-wrap:wrap"><span>{{ $task->display_task_name }}</span>@foreach($task->operation_pills as $pill)<span class="{{ $pill['class'] }}">{{ $pill['label'] }}</span>@endforeach</div></td><td>{{ $task->designer?->name ?? '—' }}</td><td><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$task->status)) }}</span></td><td>{{ $task->due_at?->format('d M, h:i A') }}</td></tr>@empty<tr><td colspan="4" class="empty-state">No tasks available.</td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Designer Workload</div></div>
        <div class="panel-body"><div class="activity-list">@forelse($designerWorkload as $designer)<div class="activity-item"><div style="display:flex;justify-content:space-between;gap:10px"><strong>{{ $designer->name }}</strong><span class="badge {{ $designer->active_tasks_count > 10 ? 'badge-danger' : 'badge-success' }}">{{ $designer->active_tasks_count }} active</span></div><p>{{ $designer->completed_tasks_count }} completed tasks</p></div>@empty<div class="empty-state">No active designers.</div>@endforelse</div></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Recent Activity</div><a href="{{ route('admin.activity.index') }}" class="file-link" style="font-size:10px">View all</a></div>
        <div class="panel-body"><div class="activity-list">@forelse($recentActivity as $event)<div class="activity-item"><strong>{{ $event->task?->task_id }} · {{ $event->task?->display_task_name }}</strong><p>{{ $event->changedBy?->name ?? 'User' }} moved task to {{ ucwords(str_replace('_',' ',$event->to_status)) }} · {{ $event->created_at->diffForHumans() }}</p></div>@empty<div class="empty-state">No activity recorded.</div>@endforelse</div></div>
    </section>
</div>
@endsection
