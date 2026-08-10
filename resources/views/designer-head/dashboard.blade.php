@extends('layouts.app')
@section('title','Designer Head Dashboard')
@section('workspace-title','Designer Head Dashboard')
@section('workspace-subtitle','Review requests raised by designers across all tasks')
@section('content')

<div class="page-head">
    <div><h1>Designer Head Dashboard</h1><p>Approve or reject Decline, Split and Swap requests across all verticals.</p></div>
</div>

<div class="metric-grid">
    <div class="metric-card"><div class="metric-label">Total Requests</div><div class="metric-value">{{ $stats['total'] }}</div><div class="metric-note">All time</div></div>
    <div class="metric-card"><div class="metric-label">Pending Approval</div><div class="metric-value">{{ $stats['pending'] }}</div><div class="metric-note">Either you or Admin can decide</div></div>
    <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value">{{ $stats['approved'] }}</div><div class="metric-note">Finalized requests</div></div>
    <div class="metric-card"><div class="metric-label">Rejected</div><div class="metric-value">{{ $stats['rejected'] }}</div><div class="metric-note">Finalized requests</div></div>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-header">
            <div>
                <div class="panel-title">Designer Requests</div>
                <div class="metric-note">The first decision by Designer Head or Admin finalizes the request</div>
            </div>
        </div>

        <div class="panel-body" style="padding:0">
            <div class="table-wrap" style="border:0;border-radius:0 0 16px 16px">
                <table class="premium-table" style="min-width:1120px">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Requested By</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Raised</th>
                            <th>Decision</th>
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
                                $isPending = in_array($item->overall_status, $pendingStatuses, true);
                                $decider = $item->adminActor ?: $item->designerHeadActor;
                            @endphp
                            <tr>
                                <td>
                                    <span class="file-link">{{ $item->task?->task_id ?? '—' }}</span>
                                    <div style="margin-top:3px;font-weight:700">{{ $item->task?->task_name ?? 'Task removed' }}</div>
                                    <div class="muted" style="margin-top:3px">{{ ucwords(str_replace('_',' ',$item->task?->vertical ?? '')) }}</div>
                                </td>
                                <td><span class="badge badge-dark">{{ ucfirst($item->request_type) }}</span></td>
                                <td>{{ $item->requester?->name ?? '—' }}</td>
                                <td style="max-width:300px">
                                    <div style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($item->reason, 150) }}</div>
                                    @if($item->request_type === 'split' && !empty($item->split_details['creative_count']))
                                        <div class="muted" style="margin-top:5px">Split {{ $item->split_details['creative_count'] }} creatives</div>
                                    @endif
                                    @if($item->targetDesigner)
                                        <div class="muted" style="margin-top:4px">Preferred: {{ $item->targetDesigner->name }}</div>
                                    @endif
                                    @if($createdSplitTask)
                                        <div style="margin-top:4px;font-size:10px;color:#08784b;font-weight:700">Created: {{ $createdSplitTask->task_id }}</div>
                                    @endif
                                </td>
                                <td><span class="badge {{ $statusBadge }}">{{ $item->status_label }}</span></td>
                                <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($isPending)
                                        <div style="display:grid;gap:7px;min-width:210px">
                                            @if(in_array($item->request_type, ['split','swap'], true))
                                                <div class="muted">
                                                    Preferred Designer:
                                                    <strong style="color:#111827">{{ $item->targetDesigner?->name ?? 'No preference' }}</strong>
                                                </div>
                                                <form method="POST" action="{{ route('designer-head.requests.approve', $item) }}" onsubmit="return confirm('Approve this request and assign it to the selected Designer? This decision is final.');">
                                                    @csrf
                                                    <select name="approved_designer_id" class="field" required style="margin-bottom:6px">
                                                        <option value="">Select approved Designer</option>
                                                        @foreach($designers as $designer)
                                                            @if((int)$designer->id !== (int)($item->task?->designer_id ?? 0))
                                                                <option value="{{ $designer->id }}" @selected((int)$designer->id === (int)($item->target_designer_id ?? 0))>{{ $designer->name }}{{ (int)$designer->id === (int)($item->target_designer_id ?? 0) ? ' · Preferred' : '' }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    @if($item->request_type === 'split')
                                                        <label class="label" style="margin-top:6px">Approved Split Quantity</label>
                                                        <input class="field" type="number" name="approved_creative_count" min="1" max="{{ max(1, ($item->task?->total_creatives ?? 1) - 1) }}" value="{{ $item->split_details['creative_count'] ?? 1 }}" required>
                                                        <div class="muted" style="margin:4px 0 6px">Designer requested {{ $item->split_details['creative_count'] ?? '—' }}. You can change the final quantity.</div>
                                                    @endif
                                                    <button class="btn btn-primary" style="width:100%">Approve</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('designer-head.requests.approve', $item) }}" onsubmit="return confirm('Approve this request? This decision is final.');">
                                                    @csrf
                                                    <button class="btn btn-primary" style="width:100%">Approve</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('designer-head.requests.reject', $item) }}" onsubmit="const b=this.querySelector('button'); if(b.disabled) return false; if(!confirm('Decline this request? A reason is mandatory and this decision is final.')) return false; b.disabled=true; b.textContent='Declining...'; return true;">
                                                @csrf
                                                <textarea name="decision_reason" class="field" rows="2" required maxlength="5000" placeholder="Reason for declining this request..."></textarea>
                                                <button class="btn btn-danger" style="width:100%;margin-top:6px">Decline</button>
                                            </form>
                                        </div>
                                    @else
                                        <strong style="font-size:10px">{{ $decider?->name ?? '—' }}</strong>
                                        @if($item->approvedDesigner)
                                            <div class="muted" style="margin-top:3px">Approved Designer: {{ $item->approvedDesigner->name }}</div>
                                        @endif
                                        <div class="muted" style="margin-top:3px">{{ $item->admin_action_at?->format('d M Y, h:i A') ?? $item->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}</div>
                                        @if($item->overall_status === 'rejected' && $item->decision_reason)<div class="muted" style="margin-top:4px;color:#b42318"><strong>Decline reason:</strong> {{ $item->decision_reason }}</div>@endif
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
