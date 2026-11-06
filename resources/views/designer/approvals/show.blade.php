@extends('layouts.app')

@section('title', $task->task_id)
@section('workspace-title', 'Approvals')
@section('workspace-subtitle', 'Review this request raised by a teammate')

@section('content')
@php
    $isSplit = $taskRequest->request_type === 'split';
    $requestedSplit = $taskRequest->splitCreativeSummary()['requested'];
    $statusLabels = \App\Services\DesignTaskStatusService::STATUSES;
@endphp
<style>
    .apv-back{display:inline-block;margin-bottom:12px;font-size:12px;font-weight:800;color:#e30613;text-decoration:none}
    .apv-facts{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:9px;margin-bottom:14px}
    .apv-fact{background:#f8f9fb;border:1px solid #eef0f3;border-radius:10px;padding:9px 11px;font-size:12px;font-weight:700;color:#344054}
    .apv-fact strong{display:block;font-size:9px;text-transform:uppercase;color:#98a2b3;margin-bottom:3px}
    .head-decision{margin-top:12px;border-top:1px solid #eaecf0;padding-top:12px}
    .head-decision-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .head-decision-box{border:1px solid #e4e7ec;border-radius:10px;padding:10px;background:#fcfcfd}
    .head-decision-title{font-size:11px;font-weight:900;color:#101828;margin-bottom:8px}
    .head-label{display:block;font-size:9px;font-weight:900;color:#667085;text-transform:uppercase;margin:8px 0 4px}
    .head-field{width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:8px 9px;background:#fff;font-size:12px;color:#344054}
    textarea.head-field{min-height:72px;resize:vertical}
    .head-hint{font-size:10px;color:#98a2b3;margin-top:4px}
    .head-btn-row{display:flex;justify-content:flex-end;margin-top:9px}
    .head-btn{border:0;border-radius:8px;padding:8px 14px;font-size:11px;font-weight:900;cursor:pointer}
    .head-btn-accept{background:#101828;color:#fff}
    .head-btn-decline{background:#e30613;color:#fff}
    .apv-card2{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:12px;margin-bottom:9px}
    .apv-grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:10px}
    .apv-field2{background:#f8f9fb;border-radius:8px;padding:8px;font-size:12px;color:#667085}
    .apv-field2 strong{display:block;color:#344054;font-size:9px;text-transform:uppercase;margin-bottom:3px}
    @media(max-width:760px){.head-decision-grid,.apv-grid2{grid-template-columns:1fr}}
</style>

<a class="apv-back" href="{{ route('designer.approvals.index') }}">&larr; Back to Approvals</a>

<div class="panel">
    <div class="panel-header"><div class="panel-title">{{ $task->task_id }} · {{ $task->display_task_name ?? $task->task_name }}</div></div>
    <div class="panel-body">
        <div class="apv-facts">
            <div class="apv-fact"><strong>Client / Agency</strong>{{ $task->party_name ?? '—' }}</div>
            <div class="apv-fact"><strong>Assigned Designer</strong>{{ $task->designer?->name ?? '—' }}</div>
            <div class="apv-fact"><strong>Requested By</strong>{{ $taskRequest->requester?->name ?? '—' }}</div>
            <div class="apv-fact"><strong>Vertical</strong>{{ ucwords(str_replace('_', ' ', (string) $task->vertical)) }}</div>
            <div class="apv-fact"><strong>Status</strong>{{ $statusLabels[$task->status] ?? ucwords(str_replace('_', ' ', (string) $task->status)) }}</div>
            <div class="apv-fact"><strong>Priority</strong>{{ ucfirst((string) $task->priority) }}</div>
            <div class="apv-fact"><strong>Due</strong>{{ $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('d M Y, h:i A') : '—' }}</div>
            <div class="apv-fact"><strong>Creatives</strong>{{ (int) $completedCreatives }} done / {{ (int) $task->total_creatives }} total</div>
        </div>

        @if($isSplit)
            @include('partials.split-details-card', ['request' => $taskRequest, 'taskShowRoute' => null])
        @else
            <div class="apv-card2">
                <div class="apv-grid2">
                    <div class="apv-field2"><strong>Requested At</strong>{{ $taskRequest->created_at?->format('d M Y · h:i A') }}</div>
                    <div class="apv-field2"><strong>Status</strong>{{ $taskRequest->status_label }}</div>
                    <div class="apv-field2"><strong>Preferred Designer</strong>{{ $taskRequest->targetDesigner?->name ?? '—' }}</div>
                    <div class="apv-field2"><strong>Approved Designer</strong>{{ $taskRequest->approvedDesigner?->name ?? '—' }}</div>
                    @if($taskRequest->reason)<div class="apv-field2" style="grid-column:1/-1"><strong>Request Reason</strong>{{ $taskRequest->reason }}</div>@endif
                    @if($taskRequest->decision_reason)<div class="apv-field2" style="grid-column:1/-1"><strong>Decision Comment</strong>{{ $taskRequest->decision_reason }}</div>@endif
                </div>
            </div>
        @endif

        @if($isPending)
            <div class="head-decision">
                <div class="head-decision-grid">
                    <form class="head-decision-box" method="POST" action="{{ route('designer.approvals.approve', $taskRequest) }}">
                        @csrf
                        <div class="head-decision-title">Accept {{ $isSplit ? 'Split' : 'Task Transfer' }} Request</div>

                        <label class="head-label">Final Designer *</label>
                        <select class="head-field" name="approved_designer_id" required data-availability-url="{{ url('/designer/designers/__ID__/availability') }}">
                            <option value="">Select Designer</option>
                            @foreach($designers as $designer)
                                <option value="{{ $designer->id }}" @selected((int) old('approved_designer_id', $taskRequest->target_designer_id) === (int) $designer->id)>{{ $designer->name }}</option>
                            @endforeach
                        </select>
                        <div class="designer-availability hidden" data-availability-box></div>

                        @if($isSplit)
                            <label class="head-label">Final Split Quantity *</label>
                            <input class="head-field" type="number" name="approved_creative_count" min="1" max="{{ max(1, (int) ($splitCtx['max'] ?? 1)) }}" value="{{ old('approved_creative_count', $requestedSplit ?: 1) }}" required>
                            <div class="head-hint">At least 1 creative must remain with the original task. Completed creatives stay with the original task.</div>
                            <div class="head-hint">Overall Creative Count: {{ (int) $task->total_creatives }}</div>
                        @endif

                        <label class="head-label">Comment</label>
                        <textarea class="head-field" name="decision_comment" placeholder="Optional approval comment">{{ old('decision_comment') }}</textarea>
                        <div class="head-hint">Optional</div>

                        <div class="head-btn-row"><button class="head-btn head-btn-accept" type="submit">Accept</button></div>
                    </form>

                    <form class="head-decision-box" method="POST" action="{{ route('designer.approvals.reject', $taskRequest) }}">
                        @csrf
                        <div class="head-decision-title">Decline {{ $isSplit ? 'Split' : 'Task Transfer' }} Request</div>
                        <label class="head-label">Decline Comment *</label>
                        <textarea class="head-field" name="decision_reason" placeholder="Enter the reason for declining" required>{{ old('decision_reason') }}</textarea>
                        <div class="head-hint">Mandatory</div>
                        <div class="head-btn-row"><button class="head-btn head-btn-decline" type="submit">Decline</button></div>
                    </form>
                </div>
            </div>
        @else
            <div class="apv-note" style="margin-top:12px;padding:10px 12px;border-radius:10px;background:#f4f0ff;border:1px solid #d9d6fe;font-size:12px">This request has already been decided.</div>
        @endif
    </div>
</div>
@endsection
