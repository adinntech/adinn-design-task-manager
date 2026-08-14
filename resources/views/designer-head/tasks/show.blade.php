@extends('layouts.app')

@section('title', $task->task_id . ' - Designer Head')
@section('workspace-title','Designer Head Task Review')
@section('workspace-subtitle','View task information and decide pending Designer requests')

@section('content')
<style>
    .dht-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px}
    .dht-back{display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:#475467;font-size:10px;font-weight:800;margin-bottom:7px}
    .dht-head h1{font-size:21px;font-weight:850;color:#101828;margin:0 0 4px}
    .dht-sub{color:#667085;font-size:11px}
    .dht-status{display:inline-flex;align-items:center;border:1px solid #e4e7ec;background:#fff;border-radius:999px;padding:7px 10px;color:#344054;font-size:9px;font-weight:850}
    .dht-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(330px,.85fr);gap:14px;align-items:start}
    .dht-panel{border:1px solid #e4e7ec;background:#fff;border-radius:16px;overflow:hidden}
    .dht-panel-head{padding:13px 15px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .dht-panel-title{font-size:12px;font-weight:850;color:#101828}
    .dht-panel-sub{font-size:9px;color:#667085;margin-top:3px}
    .dht-panel-body{padding:14px 15px}
    .dht-info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .dht-item{border:1px solid #f0f1f3;border-radius:12px;padding:10px 11px;background:#fff}
    .dht-item span{display:block;font-size:8px;color:#98a2b3;font-weight:850;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .dht-item strong{display:block;color:#344054;font-size:10px;word-break:break-word}
    .dht-section{margin-top:14px}
    .dht-section-title{font-size:11px;font-weight:850;color:#101828;margin:0 0 8px}
    .dht-requirements{display:grid;gap:7px}
    .dht-req{display:grid;grid-template-columns:180px minmax(0,1fr);gap:10px;padding:8px 0;border-bottom:1px solid #f2f4f7}
    .dht-req:last-child{border-bottom:0}
    .dht-req-label{font-size:9px;color:#667085;font-weight:800}
    .dht-req-value{font-size:9px;color:#344054;white-space:pre-wrap;word-break:break-word}
    .request-card{border:1px solid #e4e7ec;border-radius:14px;overflow:hidden;margin-bottom:11px;scroll-margin-top:16px}
    .request-card.pending{border-color:#fda29b}
    .request-card:last-child{margin-bottom:0}
    .request-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:11px 12px;background:#fcfcfd;border-bottom:1px solid #eaecf0}
    .request-card.pending .request-head{background:#fff7f6}
    .request-title{font-size:11px;font-weight:900;color:#101828}
    .request-meta{font-size:8px;color:#667085;margin-top:3px}
    .request-badge{font-size:8px;font-weight:900;text-transform:uppercase;border-radius:999px;padding:5px 7px;background:#f2f4f7;color:#475467}
    .request-badge.pending{background:#fee4e2;color:#b42318}
    .request-badge.approved{background:#ecfdf3;color:#027a48}
    .request-badge.rejected{background:#fff1f3;color:#c01048}
    .request-body{padding:12px}
    .request-detail{display:grid;gap:7px;margin-bottom:11px}
    .request-detail-row{display:flex;justify-content:space-between;gap:10px;font-size:9px}
    .request-detail-row span{color:#667085}
    .request-detail-row strong{color:#344054;text-align:right}
    .request-reason{border:1px solid #eaecf0;background:#f9fafb;border-radius:10px;padding:9px;color:#475467;font-size:9px;line-height:1.5;margin-bottom:11px}
    .decision-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}
    .decision-box{border:1px solid #eaecf0;border-radius:12px;padding:10px}
    .decision-box h4{font-size:10px;color:#101828;margin:0 0 8px;font-weight:900}
    .field-label{display:block;font-size:8px;font-weight:850;color:#475467;margin:8px 0 4px}
    .field{width:100%;border:1px solid #d0d5dd;border-radius:9px;padding:8px 9px;background:#fff;color:#344054;font-size:9px;outline:none}
    .field:focus{border-color:#98a2b3;box-shadow:0 0 0 3px rgba(152,162,179,.12)}
    textarea.field{min-height:75px;resize:vertical}
    .hint{font-size:8px;color:#98a2b3;margin-top:4px}
    .action-row{display:flex;justify-content:flex-end;margin-top:9px}
    .btn-action{border:0;border-radius:9px;padding:8px 11px;font-size:9px;font-weight:900;cursor:pointer}
    .btn-accept{background:#101828;color:#fff}
    .btn-decline{background:#d92d20;color:#fff}
    .decision-note{margin-top:9px;border-top:1px solid #f2f4f7;padding-top:9px;color:#475467;font-size:9px}
    .empty-box{text-align:center;color:#98a2b3;font-size:10px;padding:30px 12px;border:1px dashed #d0d5dd;border-radius:12px}
    @media(max-width:1050px){.dht-grid{grid-template-columns:1fr}.decision-grid{grid-template-columns:1fr}}
    @media(max-width:720px){.dht-info{grid-template-columns:1fr}.dht-req{grid-template-columns:1fr}}
</style>

<div class="dht-head">
    <div>
        <a class="dht-back" href="{{ route('designer-head.dashboard') }}">← Back to Kanban</a>
        <h1>{{ $task->task_id }} · {{ $task->task_name }}</h1>
        <div class="dht-sub">
            {{ ucwords(str_replace('_',' ',$task->vertical)) }}
            · {{ ucwords(str_replace('_',' ',$task->task_nature)) }}
        </div>
    </div>
    <div class="dht-status">{{ ucwords(str_replace('_',' ',$task->status)) }}</div>
</div>

@if(session('success'))
    <div style="margin-bottom:12px;border:1px solid #abefc6;background:#ecfdf3;color:#027a48;padding:10px 12px;border-radius:10px;font-size:10px;font-weight:700">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div style="margin-bottom:12px;border:1px solid #fecdca;background:#fff1f3;color:#b42318;padding:10px 12px;border-radius:10px;font-size:10px;font-weight:700">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div style="margin-bottom:12px;border:1px solid #fecdca;background:#fff1f3;color:#b42318;padding:10px 12px;border-radius:10px;font-size:10px;font-weight:700">
        {{ $errors->first() }}
    </div>
@endif

<div class="dht-grid">
    <div>
        <section class="dht-panel">
            <div class="dht-panel-head">
                <div class="dht-panel-title">Task Information</div>
                <div class="dht-panel-sub">View only</div>
            </div>
            <div class="dht-panel-body">
                <div class="dht-info">
                    <div class="dht-item"><span>Designer</span><strong>{{ $task->designer?->name ?? 'Unassigned' }}</strong></div>
                    <div class="dht-item"><span>Assigned By</span><strong>{{ $task->assigner?->name ?? '—' }}</strong></div>
                    <div class="dht-item"><span>Priority</span><strong>{{ ucfirst($task->priority) }}</strong></div>
                    <div class="dht-item"><span>Due Date</span><strong>{{ $task->due_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                    <div class="dht-item"><span>Party</span><strong>{{ $task->party_name }} ({{ ucfirst($task->party_type) }})</strong></div>
                    <div class="dht-item"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div>
                    <div class="dht-item"><span>Contact Person</span><strong>{{ $task->contact_person ?: '—' }}</strong></div>
                    <div class="dht-item"><span>Mobile Number</span><strong>{{ $task->mobile_number ?: '—' }}</strong></div>
                </div>

                <div class="dht-section">
                    <div class="dht-section-title">Requirement Details</div>
                    <div class="dht-requirements">
                        @forelse(($task->requirements ?? []) as $key => $value)
                            @continue(str_starts_with((string) $key, '_'))

                            @php
                                $displayValue = $value;
                                if (is_array($value)) {
                                    $displayValue = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } elseif (is_bool($value)) {
                                    $displayValue = $value ? 'Yes' : 'No';
                                }
                            @endphp

                            <div class="dht-req">
                                <div class="dht-req-label">{{ ucwords(str_replace('_',' ',(string) $key)) }}</div>
                                <div class="dht-req-value">{{ $displayValue === null || $displayValue === '' ? '—' : $displayValue }}</div>
                            </div>
                        @empty
                            <div class="empty-box">No requirement details available.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>

    <aside>
        <section class="dht-panel">
            <div class="dht-panel-head">
                <div class="dht-panel-title">Split / Swap / Decline Requests</div>
                <div class="dht-panel-sub">Open the task first, then decide here. Accept comment is optional; decline comment is mandatory.</div>
            </div>

            <div class="dht-panel-body">
                @forelse($requests as $item)
                    @php
                        $isPending = in_array($item->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                        $statusClass = $isPending ? 'pending' : ($item->overall_status === 'approved' ? 'approved' : 'rejected');
                        $proposedSplit = (int) data_get($item->split_details, 'creative_count', 0);
                        $approvedSplit = data_get($item->split_details, 'approved_creative_count');
                        $decider = $item->adminActor ?: $item->designerHeadActor;
                    @endphp

                    <article id="request-{{ $item->id }}" class="request-card {{ $isPending ? 'pending' : '' }}">
                        <div class="request-head">
                            <div>
                                <div class="request-title">{{ ucfirst($item->request_type) }} Request</div>
                                <div class="request-meta">Raised by {{ $item->requester?->name ?? '—' }} · {{ $item->created_at->format('d M Y, h:i A') }}</div>
                            </div>
                            <div class="request-badge {{ $statusClass }}">{{ $item->status_label }}</div>
                        </div>

                        <div class="request-body">
                            <div class="request-detail">
                                @if($item->request_type === 'split')
                                    <div class="request-detail-row">
                                        <span>Requested Split Quantity</span>
                                        <strong>{{ $proposedSplit ?: '—' }}</strong>
                                    </div>
                                @endif

                                @if(in_array($item->request_type, ['split','swap'], true))
                                    <div class="request-detail-row">
                                        <span>Preferred Designer</span>
                                        <strong>{{ $item->targetDesigner?->name ?? 'Not specified' }}</strong>
                                    </div>
                                @endif

                                @if($approvedSplit)
                                    <div class="request-detail-row">
                                        <span>Approved Split Quantity</span>
                                        <strong>{{ $approvedSplit }}</strong>
                                    </div>
                                @endif

                                @if($item->approvedDesigner)
                                    <div class="request-detail-row">
                                        <span>Final Designer</span>
                                        <strong>{{ $item->approvedDesigner->name }}</strong>
                                    </div>
                                @endif
                            </div>

                            <div class="request-reason">
                                <strong style="display:block;color:#344054;margin-bottom:3px">Designer Reason</strong>
                                {{ $item->reason }}
                            </div>

                            @if($isPending)
                                <div class="decision-grid">
                                    <form class="decision-box" method="POST" action="{{ route('designer-head.requests.approve', $item) }}">
                                        @csrf
                                        <h4>Accept Request</h4>

                                        @if(in_array($item->request_type, ['split','swap'], true))
                                            <label class="field-label">Final Designer *</label>
                                            <select class="field" name="approved_designer_id" required>
                                                <option value="">Select Designer</option>
                                                @foreach($designers as $designer)
                                                    @continue((int) $designer->id === (int) $task->designer_id)
                                                    <option
                                                        value="{{ $designer->id }}"
                                                        @selected((int) old('approved_designer_id', $item->target_designer_id) === (int) $designer->id)
                                                    >
                                                        {{ $designer->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif

                                        @if($item->request_type === 'split')
                                            <label class="field-label">Final Split Quantity *</label>
                                            <input
                                                class="field"
                                                type="number"
                                                min="1"
                                                max="{{ max(1, ((int) $task->total_creatives) - 1) }}"
                                                name="approved_creative_count"
                                                value="{{ old('approved_creative_count', $proposedSplit ?: 1) }}"
                                                required
                                            >
                                            <div class="hint">Must leave at least 1 creative with the original task.</div>
                                        @endif

                                        <label class="field-label">Comment</label>
                                        <textarea class="field" name="decision_comment" placeholder="Optional comment for acceptance">{{ old('decision_comment') }}</textarea>
                                        <div class="hint">Optional</div>

                                        <div class="action-row">
                                            <button class="btn-action btn-accept" type="submit">Accept</button>
                                        </div>
                                    </form>

                                    <form class="decision-box" method="POST" action="{{ route('designer-head.requests.reject', $item) }}">
                                        @csrf
                                        <h4>Decline Request</h4>

                                        <label class="field-label">Decline Comment *</label>
                                        <textarea class="field" name="decision_reason" placeholder="Enter the reason for declining this request" required>{{ old('decision_reason') }}</textarea>
                                        <div class="hint">Mandatory</div>

                                        <div class="action-row">
                                            <button class="btn-action btn-decline" type="submit">Decline</button>
                                        </div>
                                    </form>
                                </div>
                            @else
                                <div class="decision-note">
                                    <strong>{{ $item->overall_status === 'approved' ? 'Accepted' : 'Declined' }}</strong>
                                    by {{ $decider?->name ?? '—' }}.
                                    @if($item->decision_reason)
                                        <div style="margin-top:5px"><strong>Comment:</strong> {{ $item->decision_reason }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-box">No Split, Swap or Decline requests for this task.</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
