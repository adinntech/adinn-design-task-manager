@php
    $splitSummary = $request->splitCreativeSummary();
    $isPending = in_array($request->overall_status, ['pending_approval', 'pending_designer_head', 'pending_admin'], true);
    $isApproved = $request->overall_status === 'approved';
    $isRejected = $request->overall_status === 'rejected';
    $decisionLabel = $isApproved ? 'Approved' : ($isRejected ? 'Rejected' : 'Pending');
    $fmt = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y · h:i A') : null;
    $createdSplitTask = $request->createdSplitTask();
    $splitNotes = data_get($request->split_details, 'details');
@endphp

@once
<style>
    .split-detail-summary{display:flex;flex-wrap:wrap;align-items:center;gap:18px;padding:16px;border:1px solid #eaecf0;border-radius:14px;background:#fff;margin-bottom:14px}
    .split-detail-status{display:flex;align-items:center;gap:10px;padding-right:16px;border-right:1px solid #eef0f3}
    .split-detail-status-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-size:15px;font-weight:900;flex-shrink:0}
    .split-detail-status-icon.approved{background:#eaf9f2;color:#08784b}
    .split-detail-status-icon.rejected{background:#fff0f1;color:#b4232f}
    .split-detail-status-icon.pending{background:#fff5df;color:#9a6500}
    .split-detail-status-label{font-size:12px;font-weight:900;color:#101828}
    .split-detail-status-label.approved{color:#08784b}
    .split-detail-status-label.rejected{color:#b4232f}
    .split-detail-status-label.pending{color:#9a6500}
    .split-detail-status-sub{font-size:9px;color:#667085;margin-top:2px}
    .split-detail-stats{display:flex;flex-wrap:wrap;gap:16px;flex:1}
    .split-detail-stat{display:flex;align-items:center;gap:8px}
    .split-detail-stat-icon{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;font-size:13px;font-weight:900;background:#f2f4f7;color:#475467;flex-shrink:0}
    .split-detail-stat-value{font-size:15px;font-weight:900;color:#101828;line-height:1.2}
    .split-detail-stat-label{font-size:8px;color:#667085;font-weight:750;white-space:nowrap}
    .split-detail-approval{min-width:170px;text-align:right}
    .split-detail-approval-label{font-size:9px;color:#667085;font-weight:750}
    .split-detail-approval-value{font-size:15px;font-weight:900;color:#101828;margin-top:2px}
    .split-detail-bar{width:100%;height:6px;border-radius:999px;background:#eef0f3;margin-top:6px;overflow:hidden}
    .split-detail-bar-fill{height:100%;background:#2563eb;border-radius:999px}
    .split-detail-approval-note{font-size:8px;color:#98a2b3;margin-top:4px}

    .split-detail-info-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:14px}
    .split-detail-info-card{border:1px solid #eaecf0;border-radius:14px;background:#fff;padding:14px}
    .split-detail-info-title{font-size:11px;font-weight:900;color:#101828;margin-bottom:10px}
    .split-detail-info-row{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #f4f5f7;font-size:10px}
    .split-detail-info-row:last-child{border-bottom:0}
    .split-detail-info-row-label{color:#667085;font-weight:700}
    .split-detail-info-row-value{color:#101828;font-weight:850;text-align:right}
    .split-detail-pill{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:9px;font-weight:900}
    .split-detail-pill.approved{background:#eaf9f2;color:#08784b}
    .split-detail-pill.rejected{background:#fff0f1;color:#b4232f}
    .split-detail-pill.pending{background:#fff5df;color:#9a6500}

    .split-detail-child-card{border:1px solid #eaecf0;border-radius:14px;background:#fff;padding:14px;margin-bottom:14px}
    .split-detail-child-link{color:#2563eb;font-weight:850;text-decoration:none}
    .split-detail-child-link:hover{text-decoration:underline}
    .split-detail-notes{white-space:pre-wrap;font-size:10px;color:#344054;margin:0}

    .split-detail-timeline{border:1px solid #eaecf0;border-radius:14px;background:#fff;padding:16px}
    .split-detail-timeline-title{font-size:11px;font-weight:900;color:#101828;margin-bottom:14px}
    .split-detail-timeline-track{display:flex;align-items:center}
    .split-detail-timeline-step{display:flex;flex-direction:column;align-items:center;text-align:center;min-width:120px}
    .split-detail-timeline-dot{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:900;background:#2563eb;color:#fff}
    .split-detail-timeline-dot.pending{background:#eaecf0;color:#98a2b3}
    .split-detail-timeline-dot.rejected{background:#b4232f}
    .split-detail-timeline-dot.approved{background:#08784b}
    .split-detail-timeline-label{font-size:10px;font-weight:900;color:#101828;margin-top:7px}
    .split-detail-timeline-meta{font-size:8px;color:#667085;margin-top:2px}
    .split-detail-timeline-line{flex:1;height:2px;background:#2563eb;margin:0 6px 34px}
    .split-detail-timeline-line.pending{background:#e4e7ec}

    @media(max-width:750px){.split-detail-info-grid{grid-template-columns:1fr}.split-detail-stats{gap:12px}}
</style>
@endonce

<div class="split-detail-summary">
    <div class="split-detail-status">
        <div class="split-detail-status-icon {{ $isApproved ? 'approved' : ($isRejected ? 'rejected' : 'pending') }}">
            {{ $isApproved ? '✓' : ($isRejected ? '✕' : '⏳') }}
        </div>
        <div>
            <div class="split-detail-status-label {{ $isApproved ? 'approved' : ($isRejected ? 'rejected' : 'pending') }}">{{ $decisionLabel }}</div>
            <div class="split-detail-status-sub">
                @if($isPending)
                    Awaiting decision
                @else
                    Decision completed on {{ $fmt($request->responded_at) }}
                @endif
            </div>
        </div>
    </div>

    <div class="split-detail-stats">
        <div class="split-detail-stat">
            <div class="split-detail-stat-icon">📄</div>
            <div>
                <div class="split-detail-stat-value">{{ $splitSummary['total'] ?? '—' }}</div>
                <div class="split-detail-stat-label">Total creatives<br>in ticket</div>
            </div>
        </div>
        <div class="split-detail-stat">
            <div class="split-detail-stat-icon">📝</div>
            <div>
                <div class="split-detail-stat-value">{{ $splitSummary['requested'] ?? '—' }}</div>
                <div class="split-detail-stat-label">Requested split<br>creatives</div>
            </div>
        </div>
        <div class="split-detail-stat">
            <div class="split-detail-stat-icon">✅</div>
            <div>
                <div class="split-detail-stat-value">{{ $splitSummary['approved'] ?? ($isPending ? 'Pending' : '—') }}</div>
                <div class="split-detail-stat-label">Approved split<br>creatives</div>
            </div>
        </div>
        <div class="split-detail-stat">
            <div class="split-detail-stat-icon">📊</div>
            <div>
                <div class="split-detail-stat-value">{{ $splitSummary['remaining'] ?? ($isPending ? 'Pending' : '—') }}</div>
                <div class="split-detail-stat-label">Remaining creatives<br>(Other designers)</div>
            </div>
        </div>
    </div>

    <div class="split-detail-approval">
        <div class="split-detail-approval-label">Split approval</div>
        <div class="split-detail-approval-value">{{ $splitSummary['percent'] !== null ? $splitSummary['percent'].'% of ticket' : 'Pending' }}</div>
        <div class="split-detail-bar"><div class="split-detail-bar-fill" style="width:{{ $splitSummary['percent'] ?? 0 }}%"></div></div>
        <div class="split-detail-approval-note">
            @if($splitSummary['approved'] !== null && $splitSummary['total'])
                {{ $splitSummary['approved'] }} of {{ $splitSummary['total'] }} creatives approved
            @else
                Not yet decided
            @endif
        </div>
    </div>
</div>

<div class="split-detail-info-grid">
    <div class="split-detail-info-card">
        <div class="split-detail-info-title">1. Request Information</div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Split created by</span><span class="split-detail-info-row-value">{{ $request->requester?->name ?? '—' }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Requested at</span><span class="split-detail-info-row-value">{{ $fmt($request->created_at) }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Requested split count</span><span class="split-detail-info-row-value">{{ $splitSummary['requested'] !== null ? $splitSummary['requested'].' creatives' : '—' }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Preferred designer</span><span class="split-detail-info-row-value">{{ $request->targetDesigner?->name ?? '—' }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Request reason</span><span class="split-detail-info-row-value">{{ $request->reason ?: '—' }}</span></div>
    </div>

    <div class="split-detail-info-card">
        <div class="split-detail-info-title">2. Decision Information</div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Responded by (Decision maker)</span><span class="split-detail-info-row-value">{{ $isPending ? 'Pending' : ($request->decidedBy?->name ?? '—') }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Approved designer</span><span class="split-detail-info-row-value">{{ $isPending ? 'Pending' : ($request->approvedDesigner?->name ?? '—') }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Responded at</span><span class="split-detail-info-row-value">{{ $isPending ? 'Pending' : ($fmt($request->responded_at) ?? '—') }}</span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Decision status</span><span class="split-detail-info-row-value"><span class="split-detail-pill {{ $isApproved ? 'approved' : ($isRejected ? 'rejected' : 'pending') }}">{{ $decisionLabel }}</span></span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Decision comment</span><span class="split-detail-info-row-value">{{ $isPending ? 'Pending' : ($request->decision_reason ?: '—') }}</span></div>
    </div>
</div>

@if($createdSplitTask)
    <div class="split-detail-child-card">
        <div class="split-detail-info-title">Created Split Task</div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Task ID</span><span class="split-detail-info-row-value"><a class="split-detail-child-link" href="{{ $taskShowRoute ? route($taskShowRoute, $createdSplitTask) : '#' }}">{{ $createdSplitTask->task_id }}</a></span></div>
        <div class="split-detail-info-row"><span class="split-detail-info-row-label">Task Name</span><span class="split-detail-info-row-value"><a class="split-detail-child-link" href="{{ $taskShowRoute ? route($taskShowRoute, $createdSplitTask) : '#' }}">{{ $createdSplitTask->display_task_name ?? $createdSplitTask->task_name }}</a></span></div>
    </div>
@endif

@if($splitNotes)
    <div class="split-detail-child-card">
        <div class="split-detail-info-title">Split Notes</div>
        <p class="split-detail-notes">{{ $splitNotes }}</p>
    </div>
@endif

<div class="split-detail-timeline">
    <div class="split-detail-timeline-title">3. Split Request Timeline</div>
    <div class="split-detail-timeline-track">
        <div class="split-detail-timeline-step">
            <div class="split-detail-timeline-dot">📄</div>
            <div class="split-detail-timeline-label">Request Created</div>
            <div class="split-detail-timeline-meta">{{ $fmt($request->created_at) }}<br>by {{ $request->requester?->name ?? '—' }}</div>
        </div>
        <div class="split-detail-timeline-line {{ $isPending ? 'pending' : '' }}"></div>
        <div class="split-detail-timeline-step">
            <div class="split-detail-timeline-dot {{ $isApproved ? 'approved' : ($isRejected ? 'rejected' : 'pending') }}">{{ $isApproved ? '✓' : ($isRejected ? '✕' : '⏳') }}</div>
            <div class="split-detail-timeline-label">{{ $decisionLabel }}</div>
            <div class="split-detail-timeline-meta">
                @if($isPending)
                    Awaiting decision
                @else
                    {{ $fmt($request->responded_at) }}<br>by {{ $request->decidedBy?->name ?? '—' }}
                @endif
            </div>
        </div>
    </div>
</div>
