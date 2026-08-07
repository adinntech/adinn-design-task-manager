@extends('layouts.app')
@section('title','Designer Head Dashboard')
@section('workspace-title','Designer Head Dashboard')
@section('workspace-subtitle','Review requests raised by designers across all tasks')
@section('content')

<div class="page-head">
    <div><h1>Designer Head Dashboard</h1><p>Decline, split and swap requests raised by designers.</p></div>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-label">Total Requests</div><div class="metric-value">{{ $stats['total'] }}</div><div class="metric-note">All time</div></div>
    <div class="metric-card"><div class="metric-label">Pending Your Review</div><div class="metric-value">{{ $stats['pending_designer_head'] }}</div><div class="metric-note">Awaiting Designer Head action</div></div>
    <div class="metric-card"><div class="metric-label">Pending Admin</div><div class="metric-value">{{ $stats['pending_admin'] }}</div><div class="metric-note">Reserved for future Admin stage</div></div>
    <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ $stats['approved'] }}</div><div class="metric-note">Fully approved</div></div>
    <div class="metric-card"><div class="metric-label">Rejected</div><div class="metric-value">{{ $stats['rejected'] }}</div><div class="metric-note">Not approved</div></div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">All Requests</div>
                <div class="metric-note">Approving a Swap reassigns the task immediately; approving a Split creates a new task immediately</div>
            </div>
        </div>

        <div class="panel-body" style="padding:0">
            <div class="table-wrap" style="border:0;border-radius:0 0 16px 16px">
                <table class="premium-table" style="min-width:1080px">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Requested By</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Raised</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            @php
                                $statusBadge = match($item->overall_status) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    default => 'badge-warning',
                                };
                                $createdSplitTask = $item->request_type === 'split'
                                    ? $splitTasks->get($item->split_details['created_task_id'] ?? null)
                                    : null;
                            @endphp
                            <tr>
                                <td>
                                    <span class="file-link">{{ $item->task?->task_id ?? '—' }}</span>
                                    <div style="margin-top:3px;font-weight:700">{{ $item->task?->task_name ?? 'Task removed' }}</div>
                                </td>
                                <td><span class="badge badge-dark">{{ ucfirst($item->request_type) }}</span></td>
                                <td>{{ $item->requester?->name ?? '—' }}</td>
                                <td style="max-width:260px">
                                    <div style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($item->reason, 140) }}</div>
                                    @if($item->request_type === 'split' && !empty($item->split_details['creative_count']))
                                        <div class="muted" style="margin-top:4px;font-size:10px;color:#7c8492">{{ $item->split_details['creative_count'] }} creatives</div>
                                    @endif
                                    @if($item->targetDesigner)
                                        <div class="muted" style="margin-top:4px;font-size:10px;color:#7c8492">Target designer: {{ $item->targetDesigner->name }}</div>
                                    @endif
                                    @if($createdSplitTask)
                                        <div class="muted" style="margin-top:4px;font-size:10px;color:#08784b">Created: {{ $createdSplitTask->task_id }}</div>
                                    @endif
                                    @if($item->request_type === 'swap' && $item->overall_status === 'approved')
                                        <div class="muted" style="margin-top:4px;font-size:10px;color:#08784b">Reassigned to {{ $item->targetDesigner?->name }}</div>
                                    @endif
                                </td>
                                <td><span class="badge {{ $statusBadge }}">{{ ucwords(str_replace('_', ' ', $item->overall_status)) }}</span></td>
                                <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($item->overall_status === 'pending_designer_head')
                                        <div style="display:flex;gap:6px">
                                            <form method="POST" action="{{ route('designer-head.requests.approve', $item) }}" onsubmit="return confirm('Approve this request? This will apply the change immediately and cannot be undone.');">
                                                @csrf
                                                <button class="btn btn-primary">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('designer-head.requests.reject', $item) }}" onsubmit="return confirm('Reject this request?');">
                                                @csrf
                                                <button class="btn btn-danger">Reject</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="muted" style="font-size:10px;color:#7c8492">
                                            {{ $item->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">No requests have been raised yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Requests by Type</div></div>
        <div class="panel-body">
            <div class="info-grid">
                <div class="info-item"><span>Decline</span><strong>{{ $byType['decline'] }}</strong></div>
                <div class="info-item"><span>Split</span><strong>{{ $byType['split'] }}</strong></div>
                <div class="info-item"><span>Swap</span><strong>{{ $byType['swap'] }}</strong></div>
            </div>
        </div>
    </section>
</div>
@endsection
