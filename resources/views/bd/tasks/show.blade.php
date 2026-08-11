@extends('layouts.app')

@section('title',$task->task_id)
@section('workspace-title','BD Task Detail')
@section('workspace-subtitle','Review the complete task, collaboration, audit trail and production progress')

@section('content')
<style>
    [x-cloak]{display:none!important}.bd-detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;max-width:100%;overflow:auto;margin-bottom:14px}
    .bd-detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
    .bd-detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}.bd-tab-count{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#fff0f1;color:#e30613;font-size:8px;display:grid;place-items:center}
    .bd-tab-panel{margin-top:0}.bd-history-list,.bd-edit-history-list{display:flex;flex-direction:column;gap:10px}.bd-history-item{border:1px solid #e8eaef;border-left:4px solid #98a2b3;border-radius:10px;background:#fff;padding:11px 12px}.bd-history-item.role-bd{border-left-color:#e30613}.bd-history-item.role-designer{border-left-color:#2563eb}.bd-history-item.role-designer_head{border-left-color:#7c3aed}.bd-history-item.role-admin{border-left-color:#111827}.bd-history-title{font-size:11px;font-weight:850;color:#1d2939}.bd-history-meta{font-size:9px;color:#7a8493;margin-top:4px}
    .bd-request-card{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:12px;margin-bottom:9px}.bd-request-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.bd-request-title{font-size:11px;font-weight:900}.bd-request-meta{font-size:9px;color:#667085;margin-top:4px}.bd-request-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:10px}.bd-request-field{background:#f8f9fb;border-radius:8px;padding:8px;font-size:9px;color:#667085}.bd-request-field strong{display:block;color:#344054;font-size:8px;text-transform:uppercase;margin-bottom:3px}
    .bd-edit-batch{border:1px solid #e7e9ee;border-radius:12px;overflow:hidden}.bd-edit-head{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:#f8f9fb;border-bottom:1px solid #eceef2;font-size:9px;color:#667085}.bd-edit-head strong{font-size:10px;color:#344054}.bd-edit-row{padding:11px 12px;border-bottom:1px solid #f0f1f3}.bd-edit-row:last-child{border-bottom:0}.bd-edit-field{font-size:9px;font-weight:900;text-transform:uppercase;color:#667085;margin-bottom:7px}.bd-edit-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}.bd-old,.bd-new{padding:9px;border-radius:9px;font-size:10px;overflow-wrap:anywhere}.bd-old{background:#fff1f1;border:1px solid #fecaca;color:#9b1c1c}.bd-new{background:#ecfdf3;border:1px solid #abefc6;color:#067647;font-weight:750}.bd-arrow{text-align:center;color:#98a2b3;font-weight:900}
    .bd-eod-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-bottom:12px}.bd-eod-card{padding:12px;border-radius:10px;background:#f8f9fb;border:1px solid #eaecf0}.bd-eod-card span{display:block;font-size:8px;font-weight:900;text-transform:uppercase;color:#667085}.bd-eod-card strong{display:block;font-size:19px;margin-top:4px;color:#101828}.bd-eod-row{display:grid;grid-template-columns:1.2fr repeat(4,.8fr);gap:8px;padding:10px;border:1px solid #eaecf0;border-radius:9px;margin-bottom:7px;font-size:9px}.bd-eod-row strong{display:block;font-size:8px;text-transform:uppercase;color:#667085;margin-bottom:2px}
    .bd-attachment-group{border:1px solid #e8eaef;border-radius:11px;padding:11px;margin-bottom:9px}.bd-attachment-title{font-size:10px;font-weight:850;margin-bottom:8px}.bd-file{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:8px 9px;background:#f8f9fb;border-radius:8px;margin-top:6px}.bd-file-name{font-size:9px;font-weight:750;overflow-wrap:anywhere}.bd-comment{border:1px solid #e8eaef;border-left:4px solid #98a2b3;border-radius:11px;padding:11px 12px;margin-bottom:9px}.bd-comment.role-bd{border-left-color:#e30613}.bd-comment.role-designer{border-left-color:#2563eb}.bd-comment-head{display:flex;justify-content:space-between;gap:10px;font-size:9px;color:#667085}.bd-comment-head strong{font-size:10px;color:#344054}.bd-comment-message{margin-top:8px;font-size:13px;line-height:1.55;font-weight:650;white-space:pre-wrap;color:#111827}
    @media(max-width:750px){.bd-request-grid,.bd-eod-summary{grid-template-columns:1fr}.bd-edit-values,.bd-eod-row{grid-template-columns:1fr}.bd-arrow{transform:rotate(90deg)}}
</style>

<div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'overview' }">
    <div class="page-head">
        <div>
            <h1>{{ $task->display_task_name ?? $task->task_name }}</h1>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('bd.tasks.index') }}" class="btn btn-secondary">Back to Kanban</a>
            <a href="{{ route('bd.tasks.edit',$task) }}" class="btn btn-primary">Edit Task</a>
        </div>
    </div>

    @if(session('success'))<div class="flash flash-success" style="margin-bottom:14px">{{ session('success') }}</div>@endif

    <div class="bd-detail-tabs">
        <button class="bd-detail-tab" :class="{active:tab==='overview'}" @click="tab='overview'">Overview</button>
        <button class="bd-detail-tab" :class="{active:tab==='attachments'}" @click="tab='attachments'">Attachments <span class="bd-tab-count">{{ $attachmentCount }}</span></button>
        <button class="bd-detail-tab" :class="{active:tab==='comments'}" @click="tab='comments'">Comments</button>
        @if($splitRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='split-details'}" @click="tab='split-details'">Split Details</button>@endif
        @if($swapRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='swap-details'}" @click="tab='swap-details'">Swap Details</button>@endif
        <button class="bd-detail-tab" :class="{active:tab==='history'}" @click="tab='history'">Pipeline History</button>
        <button class="bd-detail-tab" :class="{active:tab==='edit-history'}" @click="tab='edit-history'">Edit History</button>
        <button class="bd-detail-tab" :class="{active:tab==='eod'}" @click="tab='eod'">EOD</button>
    </div>

    <section class="bd-tab-panel" x-show="tab==='overview'">
        <div class="detail-grid">
            <div>
                <section class="panel"><div class="panel-header"><div class="panel-title">Task Information</div></div><div class="panel-body"><div class="info-grid">
                    @foreach(['Client / Agency'=>ucfirst($task->party_type).' · '.$task->party_name,'Contact Person'=>$task->contact_person,'Mobile Number'=>$task->mobile_number,'Vertical'=>ucwords(str_replace('_',' ',$task->vertical)),'Task Nature'=>ucwords(str_replace('_',' ',$task->task_nature)),'Priority'=>ucfirst($task->priority),'Designer'=>$task->designer?->name ?? '—','Total Creatives'=>$task->total_creatives,'Due Date'=>$task->due_at?->format('d M Y, h:i A'),'Assigned At'=>$task->assigned_at?->format('d M Y, h:i A')] as $key=>$value)
                        <div class="info-item"><span>{{ $key }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </div></div></section>
                <section class="panel" style="margin-top:14px"><div class="panel-header"><div class="panel-title">Requirement Details</div></div><div class="panel-body"><div class="requirement-list">
                    @forelse(($task->requirements ?? []) as $key=>$value)
                        @continue(str_starts_with((string)$key,'_'))
                        <div class="requirement-row"><div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div><div>{{ is_array($value) ? json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $value }}</div></div>
                    @empty<div class="empty-state">No requirement data available.</div>@endforelse
                </div></div></section>
            </div>
            <aside><section class="panel"><div class="panel-header"><div class="panel-title">Current Status</div></div><div class="panel-body"><span class="badge badge-red">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span><div class="activity-item" style="margin-top:12px"><strong>Assigned Designer</strong><p>{{ $task->designer?->name ?? '—' }}</p></div><div class="activity-item" style="margin-top:8px"><strong>Due Date</strong><p>{{ $task->due_at?->format('d M Y, h:i A') }}</p></div><a href="{{ route('bd.tasks.edit',$task) }}" class="btn btn-primary" style="width:100%;margin-top:12px">Edit Task</a></div></section></aside>
        </div>
    </section>

    <section class="bd-tab-panel" x-show="tab==='attachments'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Attachments</div></div><div class="panel-body">
        @forelse($requirementAttachmentGroups as $group)<div class="bd-attachment-group"><div class="bd-attachment-title">{{ $group['label'] }}</div>@foreach($group['files'] as $file)<div class="bd-file"><div class="bd-file-name">{{ $file['name'] }}</div><a class="btn btn-secondary" target="_blank" href="{{ $file['url'] }}">Open</a></div>@endforeach</div>@empty<div class="empty-state">No requirement attachments.</div>@endforelse
        @foreach($comments as $comment)@foreach($comment->attachments as $attachment)<div class="bd-file"><div class="bd-file-name">{{ $attachment->original_name }}</div><a class="btn btn-secondary" target="_blank" href="{{ $attachment->url }}">Open</a></div>@endforeach@endforeach
    </div></div></section>

    <section class="bd-tab-panel" x-show="tab==='comments'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Comments</div></div><div class="panel-body">
        @forelse($comments as $comment)<div class="bd-comment role-{{ $comment->user?->role ?? 'default' }}"><div class="bd-comment-head"><strong>{{ $comment->user?->name ?? 'User' }}</strong><span>{{ $comment->created_at?->format('d M Y, h:i A') }}</span></div><div class="bd-comment-message">{{ $comment->comment }}</div>@foreach($comment->attachments as $attachment)<div class="bd-file"><div class="bd-file-name">{{ $attachment->original_name }}</div><a target="_blank" class="btn btn-secondary" href="{{ $attachment->url }}">Open</a></div>@endforeach</div>@empty<div class="empty-state">No comments yet.</div>@endforelse
    </div></div></section>

    @if($splitRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='split-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Split Details</div></div><div class="panel-body">
        @foreach($splitRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Split {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y, h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested Split</strong>{{ data_get($request,'split_count') ?? data_get($request,'split_details.requested_count') ?? '—' }}</div><div class="bd-request-field"><strong>Approved Split</strong>{{ data_get($request,'approved_split_count') ?? data_get($request,'split_details.approved_count') ?? '—' }}</div><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    @if($swapRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='swap-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Swap Details</div></div><div class="panel-body">
        @foreach($swapRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Swap {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y, h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    <section class="bd-tab-panel" x-show="tab==='history'" x-cloak><div class="panel"><div class="panel-header"><div><div class="panel-title">Pipeline History</div><div style="font-size:10px;color:#667085;margin-top:3px">{{ $pipelineEvents->count() }} recorded event{{ $pipelineEvents->count()===1?'':'s' }}</div></div></div><div class="panel-body"><div class="bd-history-list">
        @forelse($pipelineEvents as $event)<div class="bd-history-item role-{{ $event['role'] }}"><div class="bd-history-title">{{ $event['title'] }}</div><div class="bd-history-meta">{{ $event['description'] }} · {{ $event['created_at']?->format('d M Y, h:i A') }}</div></div>@empty<div class="empty-state">No pipeline events yet.</div>@endforelse
    </div></div></div></section>

    <section class="bd-tab-panel" x-show="tab==='edit-history'" x-cloak><div class="panel"><div class="panel-header"><div><div class="panel-title">Edit History</div><div style="font-size:10px;color:#667085;margin-top:3px">Previous and updated values are permanently retained.</div></div></div><div class="panel-body"><div class="bd-edit-history-list">
        @forelse($editHistory as $changes)@php $firstChange = $changes->first(); @endphp<div class="bd-edit-batch"><div class="bd-edit-head"><strong>Edited by {{ $firstChange->editor?->name ?? 'User' }}</strong><span>{{ $firstChange->created_at?->format('d M Y, h:i A') }}</span></div>@foreach($changes as $change)<div class="bd-edit-row"><div class="bd-edit-field">{{ $change->field_name }}</div><div class="bd-edit-values"><div class="bd-old"><del>{{ $change->old_value ?: '—' }}</del></div><div class="bd-arrow">→</div><div class="bd-new">{{ $change->new_value ?: '—' }}</div></div></div>@endforeach</div>@empty<div class="empty-state">No task edits have been recorded yet.</div>@endforelse
    </div></div></div></section>

    <section class="bd-tab-panel" x-show="tab==='eod'" x-cloak><div class="panel"><div class="panel-header"><div><div class="panel-title">EOD</div><div style="font-size:10px;color:#667085;margin-top:3px">Designer production progress for this task.</div></div></div><div class="panel-body"><div class="bd-eod-summary"><div class="bd-eod-card"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div><div class="bd-eod-card"><span>Completed</span><strong>{{ $eodCompletedTotal }}</strong></div><div class="bd-eod-card"><span>Remaining</span><strong>{{ $eodRemaining }}</strong></div></div>
        @forelse($eodRecords as $record)<div class="bd-eod-row"><div><strong>Designer</strong>{{ $record->designer?->name ?? '—' }}<br>{{ $record->submitted_at?->format('d M Y, h:i A') }}</div><div><strong>Completed Now</strong>{{ $record->completed_count }}</div><div><strong>Total Creatives</strong>{{ $record->total_creatives_snapshot }}</div><div><strong>Total Completed</strong>{{ $record->cumulative_completed }}</div><div><strong>Remaining</strong>{{ $record->remaining_creatives }}</div></div>@empty<div class="empty-state">No EOD updates have been submitted yet.</div>@endforelse
    </div></div></section>
</div>
@endsection
