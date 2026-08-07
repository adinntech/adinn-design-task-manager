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

<div class="content-grid-3">
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Recent Tasks</div></div>
        <div class="panel-body" style="padding:0"><div class="table-wrap" style="border:0;border-radius:0 0 16px 16px"><table class="premium-table" style="min-width:650px"><thead><tr><th>Task</th><th>Designer</th><th>Status</th><th>Due</th></tr></thead><tbody>@forelse($recentTasks as $task)<tr><td><a class="file-link" href="{{ route('admin.tasks.show',$task) }}">{{ $task->task_id }}</a><div style="margin-top:3px;font-weight:700">{{ $task->task_name }}</div></td><td>{{ $task->designer?->name ?? '—' }}</td><td><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$task->status)) }}</span></td><td>{{ $task->due_at?->format('d M, h:i A') }}</td></tr>@empty<tr><td colspan="4" class="empty-state">No tasks available.</td></tr>@endforelse</tbody></table></div></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Designer Workload</div></div>
        <div class="panel-body"><div class="activity-list">@forelse($designerWorkload as $designer)<div class="activity-item"><div style="display:flex;justify-content:space-between;gap:10px"><strong>{{ $designer->name }}</strong><span class="badge {{ $designer->active_tasks_count > 10 ? 'badge-danger' : 'badge-success' }}">{{ $designer->active_tasks_count }} active</span></div><p>{{ $designer->completed_tasks_count }} completed tasks</p></div>@empty<div class="empty-state">No active designers.</div>@endforelse</div></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Recent Activity</div><a href="{{ route('admin.activity.index') }}" class="file-link" style="font-size:10px">View all</a></div>
        <div class="panel-body"><div class="activity-list">@forelse($recentActivity as $event)<div class="activity-item"><strong>{{ $event->task?->task_id }} · {{ $event->task?->task_name }}</strong><p>{{ $event->changedBy?->name ?? 'User' }} moved task to {{ ucwords(str_replace('_',' ',$event->to_status)) }} · {{ $event->created_at->diffForHumans() }}</p></div>@empty<div class="empty-state">No activity recorded.</div>@endforelse</div></div>
    </section>
</div>
@endsection
