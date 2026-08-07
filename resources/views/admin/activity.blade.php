@extends('layouts.app')
@section('title','System Activity')
@section('workspace-title','System Activity')
@section('workspace-subtitle','Audit trail of design task pipeline movements')
@section('content')
<div class="page-head"><div><h1>System Activity</h1><p>Pipeline history across the application. {{ number_format($commentCount) }} comments are currently stored.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:9px;margin-bottom:14px"><input class="premium-input" style="max-width:420px" name="search" value="{{ request('search') }}" placeholder="Search Task ID or task name"><button class="btn btn-dark">Search</button></form><div class="activity-list">@forelse($history as $event)<div class="activity-item"><div style="display:flex;justify-content:space-between;gap:10px"><strong>{{ $event->task?->task_id }} · {{ $event->task?->task_name }}</strong><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$event->change_source)) }}</span></div><p>{{ $event->changedBy?->name ?? 'User' }} ({{ ucwords(str_replace('_',' ',$event->changedBy?->role ?? '')) }}) · {{ $event->from_status ? ucwords(str_replace('_',' ',$event->from_status)).' → ' : '' }}{{ ucwords(str_replace('_',' ',$event->to_status)) }} · {{ $event->created_at->format('d M Y, h:i A') }}</p></div>@empty<div class="empty-state">No activity found.</div>@endforelse</div><div class="pagination-wrap">{{ $history->links() }}</div></div></div>
@endsection
