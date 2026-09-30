@extends('layouts.app')
@section('title','Admin Dashboard')
@section('workspace-title','Manager Dashboard')
@section('workspace-subtitle','Monitor users, tasks, designers and the complete design process')
@section('content')
@php
    $statusLabels = \App\Services\DesignTaskStatusService::STATUSES;

    // One fixed colour per workflow stage (colour follows the stage, never its
    // rank or count). Order validated for adjacent-pair separation; every
    // colour is always paired with its label in the legend.
    $stageColors = [
        'assigned_tasks' => '#2a78d6',
        'review_analysis' => '#eda100',
        'need_clarification' => '#e87ba4',
        'yet_to_start' => '#1baf7a',
        'in_progress' => '#eb6834',
        'waiting_confirmation' => '#4a3aa7',
        'rework' => '#e34948',
        'prepare_printing_file' => '#00a3bf',
        'completed' => '#008300',
        'swap_tasks' => '#b07cf0',
    ];

    // Pipeline donut geometry (viewBox 0 0 200 200, r=70). Only stages with
    // tasks get a slice. Percentages use largest-remainder rounding to one
    // decimal so they always add up to exactly 100.
    $donutCircumference = 2 * M_PI * 70;
    $pipelineTotal = (int) $pipeline->sum('count');
    $nonZeroStages = $pipeline->filter(fn ($item) => $item['count'] > 0);
    $sliceGap = $nonZeroStages->count() > 1 ? 2.5 : 0;
    $pctTenths = [];
    if ($pipelineTotal > 0) {
        $rawTenths = $nonZeroStages->map(fn ($item) => $item['count'] / $pipelineTotal * 1000);
        $pctTenths = $rawTenths->map(fn ($value) => (int) floor($value))->all();
        $bumpKeys = $rawTenths->map(fn ($value, $key) => $value - $pctTenths[$key])->sortDesc()->keys()->take(1000 - array_sum($pctTenths));
        foreach ($bumpKeys as $key) {
            $pctTenths[$key]++;
        }
    }
    $formatPct = fn (int $tenths) => rtrim(rtrim(number_format($tenths / 10, 1, '.', ''), '0'), '.');
    $donutSlices = [];
    $donutCursor = 0.0;
    foreach ($nonZeroStages as $key => $item) {
        $share = $item['count'] / $pipelineTotal * $donutCircumference;
        $length = $nonZeroStages->count() > 1 ? max($share - $sliceGap, 0.8) : $donutCircumference;
        $midAngle = ($donutCursor + $length / 2) / $donutCircumference * 2 * M_PI;
        $pop = $nonZeroStages->count() > 1 ? 7 : 0;
        $donutSlices[] = [
            'key' => $key,
            'label' => $item['label'],
            'count' => $item['count'],
            'pct' => $formatPct($pctTenths[$key]),
            'color' => $stageColors[$key] ?? '#667085',
            'dash' => round($length, 3).' '.round($donutCircumference, 3),
            'offset' => round(-$donutCursor, 3),
            'dx' => round(sin($midAngle) * $pop, 2),
            'dy' => round(-cos($midAngle) * $pop, 2),
        ];
        $donutCursor += $share;
    }

    $kpiIcons = [
        'total' => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'active' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'waiting' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'rework' => '<path d="M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5"/>',
        'overdue' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'completed' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    ];
    $kpis = [
        ['key' => 'total', 'label' => 'Total Tasks', 'value' => $stats['total_tasks'], 'note' => 'Across all verticals', 'color' => '#344054', 'href' => route('admin.tasks.index')],
        ['key' => 'active', 'label' => 'Active Tasks', 'value' => $stats['active_tasks'], 'note' => 'Not yet completed', 'color' => '#2a78d6', 'href' => null],
        ['key' => 'waiting', 'label' => $statusLabels['waiting_confirmation'], 'value' => $stats['waiting_confirmation'], 'note' => 'Awaiting BD/client action', 'color' => $stageColors['waiting_confirmation'], 'href' => route('admin.tasks.index', ['status' => 'waiting_confirmation'])],
        ['key' => 'rework', 'label' => $statusLabels['rework'], 'value' => $stats['rework'], 'note' => 'Returned for correction', 'color' => $stageColors['rework'], 'href' => route('admin.tasks.index', ['status' => 'rework'])],
        ['key' => 'overdue', 'label' => 'Overdue', 'value' => $stats['overdue'], 'note' => 'Open tasks past due date', 'color' => $stats['overdue'] > 0 ? '#b42318' : '#98a2b3', 'href' => null],
        ['key' => 'completed', 'label' => $statusLabels['completed'], 'value' => $stats['completed'], 'note' => 'Closed design tasks', 'color' => $stageColors['completed'], 'href' => route('admin.tasks.index', ['status' => 'completed'])],
    ];
@endphp

<style>
    .adm-kicker{font-size:11px;font-weight:850;letter-spacing:.07em;text-transform:uppercase;color:#98a2b3;margin-bottom:6px}

    .adm-kpi{position:relative;overflow:hidden;display:block;text-decoration:none;color:inherit;box-shadow:inset 3px 0 0 var(--kpi),0 6px 20px rgba(16,24,40,.04);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .adm-kpi-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
    .adm-kpi-icon{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:0 0 auto;color:var(--kpi);background:color-mix(in srgb,var(--kpi) 11%,#fff)}
    .adm-kpi-go{font-size:10px;font-weight:850;color:#98a2b3;margin-top:6px;display:inline-flex;gap:4px;align-items:center}
    a.adm-kpi:hover,a.adm-kpi:focus-visible{transform:translateY(-3px);border-color:color-mix(in srgb,var(--kpi) 35%,#e6e8ec);box-shadow:inset 3px 0 0 var(--kpi),0 14px 28px rgba(16,24,40,.09);outline:none}
    a.adm-kpi:hover .adm-kpi-go,a.adm-kpi:focus-visible .adm-kpi-go{color:var(--kpi)}
    .adm-kpi.is-alert{background:#fff5f5;border-color:#fecdd3}
    .adm-kpi.is-alert .metric-value{color:#b42318}

    .adm-pipeline{display:grid;grid-template-columns:230px minmax(0,1fr);gap:22px;align-items:center}
    .adm-donut-wrap{position:relative;width:230px;height:230px;margin:0 auto}
    .adm-donut{display:block;width:100%;height:100%;overflow:visible}
    .adm-donut-track{fill:none;stroke:#eef0f4;stroke-width:26}
    .adm-slice{cursor:pointer;outline:none}
    .adm-slice-g{transform:translate(0,0);transition:transform .22s ease,opacity .22s ease}
    .adm-slice circle{fill:none;stroke-width:26;transition:stroke-dasharray .9s cubic-bezier(.2,.7,.2,1) var(--delay,0ms),stroke-width .22s ease,filter .22s ease}
    .adm-slice.is-active .adm-slice-g{transform:translate(var(--dx),var(--dy))}
    .adm-slice.is-active circle{stroke-width:34;filter:drop-shadow(0 3px 5px rgba(16,24,40,.28))}
    .adm-pipeline.has-active .adm-slice:not(.is-active) .adm-slice-g{opacity:.28}
    .adm-donut-center{position:absolute;inset:0;display:grid;place-items:center;pointer-events:none;text-align:center}
    .adm-center-layer{grid-area:1/1;display:flex;flex-direction:column;align-items:center;max-width:118px;transition:opacity .2s ease,transform .2s ease}
    .adm-center-layer strong{font-size:30px;line-height:1;font-weight:950;letter-spacing:-.04em;color:#101828}
    .adm-center-layer span{margin-top:5px;font-size:10px;font-weight:850;color:#475467;line-height:1.3}
    .adm-center-layer em{margin-top:3px;font-style:normal;font-size:10px;font-weight:750;color:#667085}
    .adm-center-stage{opacity:0;transform:scale(.94)}
    .adm-pipeline.has-active .adm-center-total{opacity:0;transform:scale(.94)}
    .adm-pipeline.has-active .adm-center-stage{opacity:1;transform:none}
    .adm-legend{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
    .adm-chip{display:flex;align-items:center;gap:8px;min-width:0;padding:8px 10px;border:1px solid var(--line,#e6e8ec);border-radius:10px;background:#fff;text-decoration:none;color:#344054;transition:background .18s ease,transform .18s ease,box-shadow .18s ease,opacity .18s ease}
    .adm-chip-dot{width:10px;height:10px;border-radius:3px;background:var(--c);flex:0 0 auto}
    .adm-chip-label{flex:1;min-width:0;font-size:10px;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .adm-chip-count{font-size:13px;font-weight:950;color:#101828}
    .adm-chip.is-zero{opacity:.45}
    .adm-chip.is-zero .adm-chip-dot{background:#d0d5dd}
    .adm-chip.is-active{background:color-mix(in srgb,var(--c) 13%,#fff);outline:1.5px solid color-mix(in srgb,var(--c) 60%,#fff);outline-offset:-1px;transform:translateX(3px) scale(1.02);box-shadow:0 6px 14px color-mix(in srgb,var(--c) 18%,transparent)}
    .adm-chip:focus-visible{outline:2px solid var(--c);outline-offset:1px}
    @media(max-width:1180px){.adm-pipeline{grid-template-columns:minmax(0,1fr)}.adm-legend{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}}

    .adm-team{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .adm-team-chip{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid var(--line,#e6e8ec);border-radius:12px;background:#fbfbfc}
    .adm-team-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto}
    .adm-team-chip strong{display:block;font-size:20px;font-weight:950;line-height:1;letter-spacing:-.03em}
    .adm-team-chip span{display:block;margin-top:4px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#667085}

    .adm-approvals-empty{margin-top:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:12px 16px;border:1px solid #c8efd9;border-radius:14px;background:#f3fcf7;font-size:12px;color:#344054}
    .adm-approvals-empty strong{font-weight:900;color:#101828}
    .adm-approvals-empty .badge{margin-left:auto}

    .adm-act-group{padding:10px 11px;border:1px solid var(--line,#e6e8ec);border-radius:11px;background:#fff}
    .adm-act-task{display:block;font-size:11px;font-weight:850;color:#101828;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    a.adm-act-task:hover{color:var(--brand,#e30613)}
    .adm-act-events{list-style:none;margin:7px 0 0;padding:0;display:grid;gap:6px}
    .adm-act-events li{display:flex;gap:8px;align-items:flex-start;font-size:10px;color:#667085;line-height:1.45}
    .adm-act-dot{width:8px;height:8px;border-radius:50%;background:var(--c);margin-top:4px;flex:0 0 auto;box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 16%,transparent)}
    .adm-act-events strong{color:#344054;font-weight:800}

    @media(max-width:900px){.adm-bottom{grid-template-columns:minmax(0,1fr)}}
    @media(prefers-reduced-motion:reduce){.adm-kpi,.adm-slice-g,.adm-slice circle,.adm-center-layer,.adm-chip{transition:none}a.adm-kpi:hover,a.adm-kpi:focus-visible,.adm-chip.is-active{transform:none}}
</style>

<div class="page-head">
    <div><div class="adm-kicker">{{ now()->format('l, d F Y') }}</div><h1>Welcome back, {{ auth()->user()->name }}</h1><p>A compact operational view of the Design Task Manager.</p></div>
    <div class="page-actions"><a href="{{ route('admin.users.create') }}" class="btn btn-secondary">Add User</a><a href="{{ route('admin.tasks.index') }}" class="btn btn-primary">View All Tasks</a></div>
</div>

<div class="metric-grid">
    @foreach($kpis as $kpi)
        @php $kpiTag = $kpi['href'] ? 'a' : 'div'; @endphp
        <{{ $kpiTag }} class="metric-card adm-kpi{{ $kpi['key'] === 'overdue' && $kpi['value'] > 0 ? ' is-alert' : '' }}" style="--kpi:{{ $kpi['color'] }}" @if($kpi['href']) href="{{ $kpi['href'] }}" aria-label="{{ $kpi['label'] }}: {{ $kpi['value'] }} — view in Task Monitoring" @endif>
            <div class="adm-kpi-top">
                <div class="metric-label">{{ $kpi['label'] }}</div>
                <span class="adm-kpi-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $kpiIcons[$kpi['key']] !!}</svg></span>
            </div>
            <div class="metric-value">{{ $kpi['value'] }}</div>
            <div class="metric-note">{{ $kpi['note'] }}</div>
            @if($kpi['href'])<span class="adm-kpi-go" aria-hidden="true">View tasks →</span>@endif
        </{{ $kpiTag }}>
    @endforeach
</div>

<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-header"><div><div class="panel-title">Project Pipeline</div><div class="metric-note">Live task count by workflow stage</div></div><a class="btn btn-secondary" href="{{ route('admin.tasks.index') }}">Open Monitoring</a></div>
        <div class="panel-body">
            <div class="adm-pipeline" id="adm-pipeline">
                <div class="adm-donut-wrap">
                    <svg class="adm-donut" viewBox="0 0 200 200" role="group" aria-label="Tasks by workflow stage">
                        <circle class="adm-donut-track" cx="100" cy="100" r="70"/>
                        @foreach($donutSlices as $i => $slice)
                            <a class="adm-slice" href="{{ route('admin.tasks.index', ['status' => $slice['key']]) }}" data-key="{{ $slice['key'] }}" data-label="{{ $slice['label'] }}" data-count="{{ $slice['count'] }}" data-pct="{{ $slice['pct'] }}" style="--dx:{{ $slice['dx'] }}px;--dy:{{ $slice['dy'] }}px" aria-label="{{ $slice['label'] }}: {{ $slice['count'] }} {{ \Illuminate\Support\Str::plural('task', $slice['count']) }} ({{ $slice['pct'] }}%)">
                                <g class="adm-slice-g">
                                    <circle cx="100" cy="100" r="70" transform="rotate(-90 100 100)" stroke="{{ $slice['color'] }}" stroke-dasharray="0 {{ round($donutCircumference, 3) }}" data-dash="{{ $slice['dash'] }}" stroke-dashoffset="{{ $slice['offset'] }}" style="--delay:{{ $i * 90 }}ms"/>
                                </g>
                            </a>
                        @endforeach
                    </svg>
                    <div class="adm-donut-center" aria-live="polite">
                        <div class="adm-center-layer adm-center-total"><strong>{{ $pipelineTotal }}</strong><span>Total Tasks</span></div>
                        <div class="adm-center-layer adm-center-stage" aria-hidden="true"><strong data-center-count></strong><span data-center-label></span><em data-center-pct></em></div>
                    </div>
                </div>
                <div class="adm-legend">
                    @foreach($pipeline as $key => $item)
                        <a class="adm-chip{{ $item['count'] > 0 ? '' : ' is-zero' }}" href="{{ route('admin.tasks.index', ['status' => $key]) }}" data-key="{{ $key }}" style="--c:{{ $stageColors[$key] ?? '#667085' }}">
                            <span class="adm-chip-dot" aria-hidden="true"></span>
                            <span class="adm-chip-label" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            <span class="adm-chip-count">{{ $item['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><div><div class="panel-title">Team Snapshot</div><div class="metric-note">Current active users</div></div></div>
        <div class="panel-body">
            <div class="adm-team">
                <div class="adm-team-chip">
                    <span class="adm-team-icon" style="background:#eef4fd;color:#2a78d6" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.6 7.6"/><circle cx="11" cy="11" r="2"/></svg></span>
                    <div><strong>{{ $stats['active_designers'] }}</strong><span>Active Designers</span></div>
                </div>
                <div class="adm-team-chip">
                    <span class="adm-team-icon" style="background:#fff0f1;color:#e30613" aria-hidden="true"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                    <div><strong>{{ $stats['active_bd'] }}</strong><span>Active BD</span></div>
                </div>
            </div>
            <div style="margin-top:12px"><a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="width:100%">Manage Users</a></div>
        </div>
    </section>
</div>

@if($requestStats['pending'] === 0)
<div class="adm-approvals-empty" role="status">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#08784b" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
    <strong>Designer Request Approvals</strong><span>No pending requests — nothing is waiting for approval.</span>
    <span class="badge badge-success">0 Pending</span>
</div>
@else
<section class="panel" style="margin-top:16px">
    <div class="panel-header">
        <div>
            <div class="panel-title">Designer Request Approvals</div>
            <div class="metric-note">Admin and Designer Head have equal approval authority for Split/Transfer. Decline requests are decided by Designer Head only.</div>
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
                            <td><a class="file-link" href="{{ route('admin.tasks.show', $item->task) }}">{{ $item->task?->task_id ?? '—' }}</a><div style="margin-top:3px;font-weight:700">{{ $item->task?->task_name ?? 'Task removed' }}</div></td>
                            <td><span class="badge badge-warning">{{ ucfirst($item->request_type) }} · Pending</span></td>
                            <td>{{ $item->requester?->name ?? '—' }}</td>
                            <td style="max-width:320px"><div>{{ \Illuminate\Support\Str::limit($item->reason, 150) }}</div>@if($item->request_type === 'split' && !empty($item->split_details['creative_count']))<div class="muted" style="margin-top:4px">Split {{ $item->split_details['creative_count'] }} creatives</div>@endif @if($item->targetDesigner)<div class="muted" style="margin-top:4px">Preferred: {{ $item->targetDesigner->name }}</div>@endif</td>
                            <td>{{ $item->created_at->format('d M Y') }}</td>
                            <td>
                                <div style="display:grid;gap:6px;min-width:210px">
                                    @if(in_array($item->request_type, ['split','swap'], true))
                                        <div class="muted">Preferred: <strong style="color:#111827">{{ $item->targetDesigner?->name ?? 'No preference' }}</strong></div>
                                        <form
                    method="POST"
                    action="{{ route('admin.requests.approve', $item) }}"
                    data-formal-confirm
                    data-confirm-title="Approve {{ ucfirst($item->request_type) }} Request?"
                    data-confirm-message="You are about to approve this {{ strtolower($item->request_type) }} request. The approved decision will be applied to the task workflow immediately."
                    data-confirm-note="Please verify the approved Designer and quantity, where applicable, before confirming. This decision is final for the current request."
                    data-confirm-label="Approve Request"
                    data-processing-label="Approving..."
                    data-confirm-tone="success"
                >
                                            @csrf
                                            <select name="approved_designer_id" class="field" required style="margin-bottom:6px">
                                                <option value="">Select approved Designer</option>
                                                @foreach($approvalDesigners as $designer)
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
                                            <textarea name="decision_comment" class="field" rows="2" placeholder="Optional approval comment" style="margin-bottom:6px"></textarea>
                                            <button class="btn btn-primary" style="width:100%">Approve</button>
                                        </form>
                                    @else
                                        <form
                    method="POST"
                    action="{{ route('admin.requests.approve', $item) }}"
                    data-formal-confirm
                    data-confirm-title="Approve {{ ucfirst($item->request_type) }} Request?"
                    data-confirm-message="You are about to approve this {{ strtolower($item->request_type) }} request. The approved decision will be applied to the task workflow immediately."
                    data-confirm-note="Please verify the reassigned Designer before confirming. This decision is final for the current request."
                    data-confirm-label="Approve Request"
                    data-processing-label="Approving..."
                    data-confirm-tone="success"
                >
                                            @csrf
                                            <select name="approved_designer_id" class="field" required style="margin-bottom:6px">
                                                <option value="">Select approved Designer</option>
                                                @foreach($approvalDesigners as $designer)
                                                    @if((int)$designer->id !== (int)($item->task?->designer_id ?? 0))
                                                        <option value="{{ $designer->id }}">{{ $designer->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <textarea name="decision_comment" class="field" rows="2" placeholder="Optional approval comment" style="margin-bottom:6px"></textarea>
                                            <button class="btn btn-primary" style="width:100%">Approve</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.requests.reject', $item) }}" data-formal-confirm
                    data-confirm-title="Decline {{ ucfirst($item->request_type) }} Request?"
                    data-confirm-message="You are about to decline this request. The Designer will be informed through the request status and task history."
                    data-confirm-note="Please ensure a meaningful decline reason has been entered before continuing. This decision is final for the current request."
                    data-confirm-label="Decline Request"
                    data-processing-label="Declining..."
                    data-confirm-tone="danger">
                                        @csrf
                                        <textarea name="decision_reason" class="field" rows="2" required maxlength="5000" placeholder="Reason for declining this request..."></textarea>
                                        <button class="btn btn-danger" style="width:100%;margin-top:6px">Decline</button>
                                    </form>
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
@endif

<div class="content-grid-3 adm-bottom">
    <section class="panel">
        <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="panel-title">Recent Tasks</div>
            <input type="text" id="admin-task-search" style="min-width:220px;border:1px solid #d0d5dd;border-radius:8px;padding:7px 10px;font-size:13px" placeholder="Search tasks, project, client, designer, BD..." autocomplete="off">
        </div>
        <div class="panel-body" style="padding:0"><div class="table-wrap" style="border:0;border-radius:0 0 16px 16px"><table class="premium-table" style="min-width:650px"><thead><tr><th>Task</th><th>Designer</th><th>Status</th><th>Due</th></tr></thead><tbody id="admin-recent-tasks">@include('admin.dashboard-recent-tasks')</tbody></table></div></div>
    </section>

    <script>
    (function () {
        var input = document.getElementById('admin-task-search');
        var tbody = document.getElementById('admin-recent-tasks');
        var base = "{{ route('admin.dashboard.recentTasks') }}";
        var debounceTimer = null;

        function reload() {
            tbody.parentElement.parentElement.style.opacity = '0.5';
            fetch(base + '?search=' + encodeURIComponent(input.value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    tbody.innerHTML = html;
                    tbody.parentElement.parentElement.style.opacity = '';
                })
                .catch(function () { tbody.parentElement.parentElement.style.opacity = ''; });
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(reload, 350);
        });
    })();
    </script>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Designer Workload</div></div>
        <div class="panel-body"><div class="activity-list">@forelse($designerWorkload as $designer)<div class="activity-item"><div style="display:flex;justify-content:space-between;gap:10px"><strong>{{ $designer->name }}</strong><span class="badge {{ $designer->active_tasks_count > 10 ? 'badge-danger' : 'badge-success' }}">{{ $designer->active_tasks_count }} active</span></div><p>{{ $designer->completed_tasks_count }} completed tasks</p></div>@empty<div class="empty-state">No active designers.</div>@endforelse</div></div>
    </section>

    <section class="panel">
        <div class="panel-header"><div class="panel-title">Recent Activity</div><a href="{{ route('admin.activity.index') }}" class="file-link" style="font-size:10px">View all</a></div>
        <div class="panel-body"><div class="activity-list">
            {{-- Same 10 events; consecutive events on one task share a heading. --}}
            @forelse($recentActivity->chunkWhile(fn ($event, $key, $chunk) => $event->design_task_id === $chunk->last()->design_task_id) as $group)
                @php $groupTask = $group->first()->task; @endphp
                <div class="adm-act-group">
                    @if($groupTask)
                        <a class="adm-act-task" href="{{ route('admin.tasks.show', $groupTask) }}" title="{{ $groupTask->task_id }} · {{ $groupTask->task_name }}">{{ $groupTask->task_id }} · {{ $groupTask->task_name }}</a>
                    @else
                        <span class="adm-act-task" style="color:#98a2b3">Task removed</span>
                    @endif
                    <ul class="adm-act-events">
                        @foreach($group as $event)
                            <li><span class="adm-act-dot" style="--c:{{ $stageColors[$event->to_status] ?? '#98a2b3' }}" aria-hidden="true"></span><span>{{ $event->changedBy?->name ?? 'User' }} moved task to <strong>{{ $statusLabels[$event->to_status] ?? ucwords(str_replace('_',' ',$event->to_status)) }}</strong> · {{ $event->created_at->diffForHumans() }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="empty-state">No activity recorded.</div>
            @endforelse
        </div></div>
    </section>
</div>

<x-formal-confirm-dialog />

<script>
// Pipeline donut: staggered draw-in, and slice <-> legend-chip highlighting.
(function () {
    var root = document.getElementById('adm-pipeline');
    if (!root) return;

    var slices = Array.prototype.slice.call(root.querySelectorAll('.adm-slice'));
    var chips = Array.prototype.slice.call(root.querySelectorAll('.adm-chip:not(.is-zero)'));
    var countEl = root.querySelector('[data-center-count]');
    var labelEl = root.querySelector('[data-center-label]');
    var pctEl = root.querySelector('[data-center-pct]');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function draw() {
        slices.forEach(function (slice) {
            var circle = slice.querySelector('circle');
            circle.style.strokeDasharray = circle.getAttribute('data-dash');
        });
    }
    if (reduceMotion) {
        draw();
    } else {
        requestAnimationFrame(function () { requestAnimationFrame(draw); });
    }

    function activate(key) {
        var slice = slices.filter(function (s) { return s.getAttribute('data-key') === key; })[0];
        if (!slice) return;
        slices.forEach(function (s) { s.classList.toggle('is-active', s === slice); });
        chips.forEach(function (c) { c.classList.toggle('is-active', c.getAttribute('data-key') === key); });
        countEl.textContent = slice.getAttribute('data-count');
        labelEl.textContent = slice.getAttribute('data-label');
        pctEl.textContent = slice.getAttribute('data-pct') + '% of tasks';
        root.classList.add('has-active');
    }

    function clear() {
        root.classList.remove('has-active');
        slices.forEach(function (s) { s.classList.remove('is-active'); });
        chips.forEach(function (c) { c.classList.remove('is-active'); });
    }

    slices.concat(chips).forEach(function (el) {
        var key = el.getAttribute('data-key');
        el.addEventListener('mouseenter', function () { activate(key); });
        el.addEventListener('focus', function () { activate(key); });
        el.addEventListener('mouseleave', clear);
        el.addEventListener('blur', clear);
    });
})();
</script>
@endsection
