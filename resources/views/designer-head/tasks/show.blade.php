@extends('layouts.app')

@section('title',$task->task_id)
@section('workspace-title','All Tasks')
@section('workspace-subtitle','View the complete task and review Designer requests')

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

        .progress-card{padding:13px;border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-top:12px}.progress-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.progress-title{font-size:10px;font-weight:900;color:#344054}.progress-value{font-size:11px;font-weight:950}.progress-track{height:9px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:8px}.progress-fill{height:100%;border-radius:999px;transition:width .25s}.progress-start .progress-fill{background:#94a3b8}.progress-low .progress-fill{background:#f59e0b}.progress-mid .progress-fill{background:#3b82f6}.progress-high .progress-fill{background:#8b5cf6}.progress-complete .progress-fill{background:#16a34a}.progress-start .progress-value{color:#64748b}.progress-low .progress-value{color:#b45309}.progress-mid .progress-value{color:#1d4ed8}.progress-high .progress-value{color:#7c3aed}.progress-complete .progress-value{color:#15803d}.collapse-panel{border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-bottom:14px;overflow:hidden}.collapse-panel summary{list-style:none;cursor:pointer;padding:12px 14px;font-size:11px;font-weight:900;color:#1d2939;display:flex;justify-content:space-between;align-items:center}.collapse-panel summary::-webkit-details-marker{display:none}.collapse-panel summary:after{content:'+';font-size:17px;color:#667085}.collapse-panel[open] summary:after{content:'−'}.collapse-panel .collapse-body{border-top:1px solid #eef0f3;padding:14px}.task-update-note{padding:10px 12px;border-radius:10px;background:#fffaeb;border:1px solid #fedf89;color:#93370d;font-size:10px;margin-bottom:12px}.rework-box{padding:13px;border:1px solid #fecaca;background:#fff7f7;border-radius:12px;margin-bottom:14px}.update-file{margin-top:8px;font-size:9px}.history-section-title{font-size:11px;font-weight:900;margin:18px 0 9px;color:#1d2939}.history-section-title:first-child{margin-top:0}

        .history-switcher{display:flex;gap:6px;padding:6px;background:#f4f5f7;border:1px solid #e4e7ec;border-radius:12px;margin-bottom:14px;width:max-content}
        .history-switch-btn{border:0;background:transparent;color:#667085;border-radius:8px;padding:8px 13px;font-size:10px;font-weight:900;cursor:pointer;transition:.16s ease}
        .history-switch-btn:hover{background:#fff;color:#101828}
        .history-switch-btn.active{background:#101828;color:#fff;box-shadow:0 4px 12px rgba(16,24,40,.14)}
        .history-view-card{border:1px solid #e5e7eb;border-radius:14px;background:#fff;overflow:hidden;box-shadow:0 5px 16px rgba(16,24,40,.04)}
        .history-view-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;background:linear-gradient(180deg,#fff,#f9fafb);border-bottom:1px solid #eaecf0}
        .history-view-title{font-size:12px;font-weight:950;color:#101828}
        .history-view-subtitle{font-size:9px;color:#667085;margin-top:3px}
        .history-view-count{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:4px 9px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:9px;font-weight:900}
        .history-view-body{padding:12px}
        .history-pipeline-card{border:1px solid #e7e9ee;border-left:4px solid #2563eb;border-radius:11px;background:#fff;padding:11px 12px;margin-bottom:8px}
        .history-pipeline-card:last-child{margin-bottom:0}
        .history-pipeline-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
        .history-pipeline-title{font-size:10px;font-weight:950;color:#101828}
        .history-pipeline-meta{margin-top:4px;font-size:9px;color:#667085;line-height:1.45}
        .history-role-badge{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:8px;font-weight:900;white-space:nowrap}
        .history-task-batch{border:1px solid #e7e9ee;border-left:4px solid #7c3aed;border-radius:11px;background:#fff;margin-bottom:9px;overflow:hidden}
        .history-task-batch:last-child{margin-bottom:0}
        .history-task-head{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:#faf9ff;border-bottom:1px solid #ede9fe}
        .history-task-editor{font-size:10px;font-weight:900;color:#4c1d95}
        .history-task-time{font-size:9px;color:#7c8492}
        .history-task-row{padding:10px 12px;border-bottom:1px solid #f0f1f3}
        .history-task-row:last-child{border-bottom:0}
        .history-task-field{font-size:8px;font-weight:950;text-transform:uppercase;letter-spacing:.045em;color:#667085;margin-bottom:7px}
        .history-task-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}
        .history-task-old,.history-task-new{padding:8px 9px;border-radius:8px;font-size:9px;line-height:1.45;overflow-wrap:anywhere}
        .history-task-old{background:#fff1f1;color:#b42318;border:1px solid #fecaca}
        .history-task-new{background:#ecfdf3;color:#067647;border:1px solid #abefc6;font-weight:800}
        .history-task-arrow{text-align:center;color:#98a2b3;font-weight:900}
        .history-nothing{text-align:center;padding:34px 14px;color:#98a2b3;font-size:10px}
        @media(max-width:700px){.history-task-values{grid-template-columns:1fr}.history-task-arrow{transform:rotate(90deg)}}


    .head-decision{margin-top:12px;border-top:1px solid #eaecf0;padding-top:12px}
    .head-decision-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .head-decision-box{border:1px solid #e4e7ec;border-radius:10px;padding:10px;background:#fcfcfd}
    .head-decision-title{font-size:10px;font-weight:900;color:#101828;margin-bottom:8px}
    .head-label{display:block;font-size:8px;font-weight:900;color:#667085;text-transform:uppercase;margin:8px 0 4px}
    .head-field{width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:8px 9px;background:#fff;font-size:9px;color:#344054}
    textarea.head-field{min-height:72px;resize:vertical}
    .head-hint{font-size:8px;color:#98a2b3;margin-top:4px}
    .head-btn-row{display:flex;justify-content:flex-end;margin-top:9px}
    .head-btn{border:0;border-radius:8px;padding:8px 11px;font-size:9px;font-weight:900;cursor:pointer}
    .head-btn-accept{background:#101828;color:#fff}
    .head-btn-decline{background:#e30613;color:#fff}
    @media(max-width:760px){.head-decision-grid{grid-template-columns:1fr}}

.request-choice-actions{display:flex;gap:10px;margin-top:12px}
.request-choice-btn{border:0;border-radius:9px;padding:9px 16px;font-size:10px;font-weight:900;cursor:pointer}
.request-choice-accept{background:#16a34a;color:#fff}
.request-choice-decline{background:#dc2626;color:#fff}
.request-choice-form{display:none;margin-top:12px}
.request-choice-form.active{display:block}
</style>

<div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'overview', requestAction: null }">
    <div class="page-head">
        <div>
            <h1>{{ $task->display_task_name ?? $task->task_name }}</h1>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('designer-head.dashboard') }}" class="btn btn-secondary">Back to All Tasks</a>
        </div>
    </div>


    <div class="bd-detail-tabs">
        <button class="bd-detail-tab" :class="{active:tab==='overview'}" @click="tab='overview'">Overview</button>
        
        <button class="bd-detail-tab" :class="{active:tab==='comments'}" @click="tab='comments'">Comments</button>
        @if($splitRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='split-details'}" @click="tab='split-details'">Split Details</button>@endif
        @if($swapRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='swap-details'}" @click="tab='swap-details'">Swap Details</button>@endif
        @if($declineRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='decline-details'}" @click="tab='decline-details'">Decline Details</button>@endif
        <button class="bd-detail-tab" :class="{active:tab==='history'}" @click="tab='history'">History</button>
        <button class="bd-detail-tab" :class="{active:tab==='eod'}" @click="tab='eod'">Task Updation</button>
    </div>

    <section class="bd-tab-panel" x-show="tab==='overview'">
        <div class="detail-grid">
            <div>
                <details class="collapse-panel"><summary>Task Information</summary><div class="collapse-body"><div class="info-grid">
                    @foreach(['Client / Agency'=>ucfirst($task->party_type).' · '.$task->party_name,'Contact Person'=>$task->contact_person,'Mobile Number'=>$task->mobile_number,'Vertical'=>ucwords(str_replace('_',' ',$task->vertical)),'Task Nature'=>ucwords(str_replace('_',' ',$task->task_nature)),'Priority'=>ucfirst($task->priority),'Designer'=>$task->designer?->name ?? '—','Total Creatives'=>$task->total_creatives,'Due Date'=>$task->due_at?->format('d M Y, h:i A'),'Assigned At'=>$task->assigned_at?->format('d M Y, h:i A')] as $key=>$value)
                        <div class="info-item"><span>{{ $key }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </div></div></details>
                <details class="collapse-panel"><summary>Requirement Details</summary><div class="collapse-body"><div class="requirement-list">
                    @forelse(($task->requirements ?? []) as $key=>$value)
                        @php
                            $isRequirementFile = (is_string($value) && str_contains($value,'/') && !filter_var($value,FILTER_VALIDATE_URL))
                                || (is_array($value) && collect($value)->contains(fn($item) => is_string($item) && str_contains($item,'/') && !filter_var($item,FILTER_VALIDATE_URL)));
                        @endphp
                        @continue(str_starts_with((string)$key,'_') || $isRequirementFile)
                        <div class="requirement-row"><div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div><div>{{ is_array($value) ? json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $value }}</div></div>
                    @empty<div class="empty-state">No requirement data available.</div>@endforelse
                </div></div></details>
                <details class="collapse-panel"><summary>Attachments <span class="bd-tab-count">{{ $requirementAttachmentCount }}</span></summary><div class="collapse-body">
                    @forelse($requirementAttachmentGroups as $group)<div class="bd-attachment-group"><div class="bd-attachment-title">{{ $group['label'] }}</div>@foreach($group['files'] as $file)<div class="bd-file"><div class="bd-file-name">{{ $file['name'] }}</div><a class="btn btn-secondary" target="_blank" href="{{ $file['url'] }}">Open</a></div>@endforeach</div>@empty<div class="empty-state">No task creation/edit attachments.</div>@endforelse
                </div></details>
            </div>
            <aside><section class="panel"><div class="panel-header"><div class="panel-title">Current Status</div></div><div class="panel-body"><span class="badge badge-red">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span><div class="progress-card progress-{{ $progressColorKey }}"><div class="progress-head"><span class="progress-title">Creative Progress</span><span class="progress-value">{{ $eodCompletedTotal }} / {{ $task->total_creatives }} · {{ $progressPercentage }}%</span></div><div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div></div><div class="activity-item" style="margin-top:12px"><strong>Assigned Designer</strong><p>{{ $task->designer?->name ?? '—' }}</p></div><div class="activity-item" style="margin-top:8px"><strong>Due Date</strong><p>{{ $task->due_at?->format('d M Y, h:i A') }}</p></div></div></section></aside>
        </div>
    </section>

    <section class="bd-tab-panel" x-show="tab==='comments'" x-cloak>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Comments</div>
                    <div style="font-size:10px;color:#667085;margin-top:3px">Shared task communication.</div>
                </div>
            </div>

            <div class="panel-body">
                <div style="margin-bottom:16px">
                    @forelse($comments as $comment)
                        <div class="bd-comment role-{{ $comment->user?->role ?? 'default' }}">
                            <div class="bd-comment-head">
                                <strong>{{ $comment->user?->name ?? 'User' }}</strong>
                                <span>{{ $comment->created_at?->format('d M Y, h:i A') }}</span>
                            </div>

                            <div class="bd-comment-message">{{ $comment->comment }}</div>

                            @foreach($comment->attachments as $attachment)
                                <div class="bd-file">
                                    <div class="bd-file-name">{{ $attachment->original_name }}</div>
                                    <a target="_blank" class="btn btn-secondary" href="{{ $attachment->url }}">Open</a>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="empty-state">No comments yet.</div>
                    @endforelse
                </div>

                <div style="border-top:1px solid #eaecf0;padding-top:14px">
                    <form
                        method="POST"
                        action="{{ route('bd.tasks.comments.store', $task) }}"
                        enctype="multipart/form-data"
                        onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerText='Posting...';"
                    >
                        @csrf

                        <label style="display:block;font-size:10px;font-weight:850;color:#344054;margin-bottom:6px">
                            Add Comment
                        </label>

                        <textarea
                            class="premium-input"
                            name="comment"
                            rows="4"
                            maxlength="10000"
                            placeholder="Write a comment for the Designer..."
                            required
                        >{{ old('comment') }}</textarea>

                        @error('comment')
                            <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                        @enderror

                        <div style="margin-top:10px">
                            <label style="display:block;font-size:10px;font-weight:850;color:#344054;margin-bottom:6px">
                                Attachments
                            </label>

                            <input
                                class="premium-input"
                                type="file"
                                name="attachments[]"
                                multiple
                            >

                            <div style="font-size:9px;color:#98a2b3;margin-top:5px">
                                Up to 10 files · Maximum 100 MB each
                            </div>

                            @error('attachments.*')
                                <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:12px">
                            Post Comment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    @if($splitRequests->isNotEmpty())
    <section class="bd-tab-panel" x-show="tab==='split-details'" x-cloak>
        <div class="panel">
            <div class="panel-header"><div class="panel-title">Split Details</div></div>
            <div class="panel-body">
                @foreach($splitRequests as $request)
                    @php
                        $isPendingRequest = in_array($request->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                        $requestedSplit = data_get($request,'split_count')
                            ?? data_get($request,'split_details.requested_count')
                            ?? data_get($request,'split_details.creative_count');
                        $approvedSplit = data_get($request,'approved_split_count')
                            ?? data_get($request,'split_details.approved_count')
                            ?? data_get($request,'split_details.approved_creative_count');
                    @endphp

                    <div class="bd-request-card" id="request-{{ $request->id }}">
                        <div class="bd-request-head">
                            <div>
                                <div class="bd-request-title">Split {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div>
                                <div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                            <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span>
                        </div>

                        <div class="bd-request-grid">
                            <div class="bd-request-field"><strong>Requested Split</strong>{{ $requestedSplit ?? '—' }}</div>
                            <div class="bd-request-field"><strong>Approved Split</strong>{{ $approvedSplit ?? '—' }}</div>
                            <div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div>
                            <div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>
                            @if($request->reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Request Reason</strong>{{ $request->reason }}</div>@endif
                            @if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Comment</strong>{{ $request->decision_reason }}</div>@endif
                        </div>

                        @if($isPendingRequest)
                            <div class="request-choice-actions">
    <button type="button" class="request-choice-btn request-choice-accept" @click="requestAction='accept'">Accept</button>
    <button type="button" class="request-choice-btn request-choice-decline" @click="requestAction='decline'">Decline</button>
</div>

<div class="head-decision" x-show="requestAction" x-cloak>
                                <div class="head-decision-grid">
                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='accept'}" method="POST" action="{{ route('designer-head.requests.approve', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Accept Split Request</div>

                                        <label class="head-label">Final Designer *</label>
                                        <select class="head-field" name="approved_designer_id" required>
                                            <option value="">Select Designer</option>
                                            @foreach($designers as $designer)
                                                @continue((int)$designer->id === (int)$task->designer_id)
                                                <option value="{{ $designer->id }}" @selected((int)old('approved_designer_id',$request->target_designer_id) === (int)$designer->id)>{{ $designer->name }}</option>
                                            @endforeach
                                        </select>

                                        <label class="head-label">Final Split Quantity *</label>
                                        <input class="head-field" type="number" name="approved_creative_count" min="1" max="{{ max(1,((int)$task->total_creatives)-1) }}" value="{{ old('approved_creative_count',$requestedSplit ?: 1) }}" required>
                                        <div class="head-hint">At least 1 creative must remain with the original task.</div>

                                        <label class="head-label">Comment</label>
                                        <textarea class="head-field" name="decision_comment" placeholder="Optional approval comment">{{ old('decision_comment') }}</textarea>
                                        <div class="head-hint">Optional</div>

                                        <div class="head-btn-row"><button class="head-btn head-btn-accept" type="submit">Accept</button></div>
                                    </form>

                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='decline'}" method="POST" action="{{ route('designer-head.requests.reject', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Decline Split Request</div>
                                        <label class="head-label">Decline Comment *</label>
                                        <textarea class="head-field" name="decision_reason" placeholder="Enter the reason for declining" required>{{ old('decision_reason') }}</textarea>
                                        <div class="head-hint">Mandatory</div>
                                        <div class="head-btn-row"><button class="head-btn head-btn-decline" type="submit">Decline</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

    @if($swapRequests->isNotEmpty())
    <section class="bd-tab-panel" x-show="tab==='swap-details'" x-cloak>
        <div class="panel">
            <div class="panel-header"><div class="panel-title">Swap Details</div></div>
            <div class="panel-body">
                @foreach($swapRequests as $request)
                    @php
                        $isPendingRequest = in_array($request->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                    @endphp

                    <div class="bd-request-card" id="request-{{ $request->id }}">
                        <div class="bd-request-head">
                            <div>
                                <div class="bd-request-title">Swap {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div>
                                <div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                            <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span>
                        </div>

                        <div class="bd-request-grid">
                            <div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div>
                            <div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>
                            @if($request->reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Request Reason</strong>{{ $request->reason }}</div>@endif
                            @if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Comment</strong>{{ $request->decision_reason }}</div>@endif
                        </div>

                        @if($isPendingRequest)
                            <div class="request-choice-actions">
    <button type="button" class="request-choice-btn request-choice-accept" @click="requestAction='accept'">Accept</button>
    <button type="button" class="request-choice-btn request-choice-decline" @click="requestAction='decline'">Decline</button>
</div>

<div class="head-decision" x-show="requestAction" x-cloak>
                                <div class="head-decision-grid">
                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='accept'}" method="POST" action="{{ route('designer-head.requests.approve', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Accept Swap Request</div>

                                        <label class="head-label">Final Designer *</label>
                                        <select class="head-field" name="approved_designer_id" required>
                                            <option value="">Select Designer</option>
                                            @foreach($designers as $designer)
                                                @continue((int)$designer->id === (int)$task->designer_id)
                                                <option value="{{ $designer->id }}" @selected((int)old('approved_designer_id',$request->target_designer_id) === (int)$designer->id)>{{ $designer->name }}</option>
                                            @endforeach
                                        </select>

                                        <label class="head-label">Comment</label>
                                        <textarea class="head-field" name="decision_comment" placeholder="Optional approval comment">{{ old('decision_comment') }}</textarea>
                                        <div class="head-hint">Optional</div>

                                        <div class="head-btn-row"><button class="head-btn head-btn-accept" type="submit">Accept</button></div>
                                    </form>

                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='decline'}" method="POST" action="{{ route('designer-head.requests.reject', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Decline Swap Request</div>
                                        <label class="head-label">Decline Comment *</label>
                                        <textarea class="head-field" name="decision_reason" placeholder="Enter the reason for declining" required>{{ old('decision_reason') }}</textarea>
                                        <div class="head-hint">Mandatory</div>
                                        <div class="head-btn-row"><button class="head-btn head-btn-decline" type="submit">Decline</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if($declineRequests->isNotEmpty())
    <section class="bd-tab-panel" x-show="tab==='decline-details'" x-cloak>
        <div class="panel">
            <div class="panel-header"><div class="panel-title">Decline Details</div></div>
            <div class="panel-body">
                @foreach($declineRequests as $request)
                    @php
                        $isPendingRequest = in_array($request->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                    @endphp
                    <div class="bd-request-card" id="request-{{ $request->id }}">
                        <div class="bd-request-head">
                            <div>
                                <div class="bd-request-title">Decline {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div>
                                <div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y, h:i A') }}</div>
                            </div>
                            <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span>
                        </div>
                        <div class="bd-request-grid">
                            <div class="bd-request-field" style="grid-column:1/-1"><strong>Request Reason</strong>{{ $request->reason }}</div>
                            @if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Comment</strong>{{ $request->decision_reason }}</div>@endif
                        </div>

                        @if($isPendingRequest)
                            <div class="request-choice-actions">
    <button type="button" class="request-choice-btn request-choice-accept" @click="requestAction='accept'">Accept</button>
    <button type="button" class="request-choice-btn request-choice-decline" @click="requestAction='decline'">Decline</button>
</div>

<div class="head-decision" x-show="requestAction" x-cloak>
                                <div class="head-decision-grid">
                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='accept'}" method="POST" action="{{ route('designer-head.requests.approve', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Accept Decline Request</div>
                                        <label class="head-label">Comment</label>
                                        <textarea class="head-field" name="decision_comment" placeholder="Optional approval comment">{{ old('decision_comment') }}</textarea>
                                        <div class="head-hint">Optional</div>
                                        <div class="head-btn-row"><button class="head-btn head-btn-accept" type="submit">Accept</button></div>
                                    </form>

                                    <form class="head-decision-box request-choice-form" :class="{active: requestAction==='decline'}" method="POST" action="{{ route('designer-head.requests.reject', $request) }}">
                                        @csrf
                                        <div class="head-decision-title">Decline Request</div>
                                        <label class="head-label">Decline Comment *</label>
                                        <textarea class="head-field" name="decision_reason" placeholder="Enter the reason for declining" required>{{ old('decision_reason') }}</textarea>
                                        <div class="head-hint">Mandatory</div>
                                        <div class="head-btn-row"><button class="head-btn head-btn-decline" type="submit">Decline</button></div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

    <section class="bd-tab-panel" x-show="tab==='history'" x-cloak x-data="{ historyView: 'pipeline' }">
        <div class="history-switcher">
            <button
                type="button"
                class="history-switch-btn"
                :class="{ 'active': historyView === 'pipeline' }"
                @click="historyView='pipeline'"
            >
                Pipeline History
            </button>

            <button
                type="button"
                class="history-switch-btn"
                :class="{ 'active': historyView === 'task' }"
                @click="historyView='task'"
            >
                Task History
            </button>
        </div>

        <div x-show="historyView === 'pipeline'">
            <div class="history-view-card">
                <div class="history-view-head">
                    <div>
                        <div class="history-view-title">Pipeline History</div>
                        <div class="history-view-subtitle">Complete workflow, comment and status activity.</div>
                    </div>
                    <span class="history-view-count">{{ $pipelineEvents->count() }} Events</span>
                </div>

                <div class="history-view-body">
                    @forelse($pipelineEvents as $event)
                        @php
                            $historyRole = $event['role'] ?? 'default';
                        @endphp

                        <div class="history-pipeline-card">
                            <div class="history-pipeline-top">
                                <div class="history-pipeline-title">{{ $event['title'] }}</div>
                                <span class="history-role-badge">
                                    {{ $historyRole === 'default' ? 'System' : ucwords(str_replace('_', ' ', $historyRole)) }}
                                </span>
                            </div>

                            <div class="history-pipeline-meta">
                                {{ $event['description'] }}
                                · {{ $event['created_at']?->format('d M Y, h:i A') }}
                            </div>
                        </div>
                    @empty
                        <div class="history-nothing">No pipeline events have been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="historyView === 'task'" x-cloak>
            <div class="history-view-card">
                <div class="history-view-head">
                    <div>
                        <div class="history-view-title">Task History</div>
                        <div class="history-view-subtitle">Every recorded change to task information.</div>
                    </div>
                    <span class="history-view-count">{{ $editHistory->count() }} Updates</span>
                </div>

                <div class="history-view-body">
                    @forelse($editHistory as $changes)
                        @php
                            $firstChange = $changes->first();
                        @endphp

                        <div class="history-task-batch">
                            <div class="history-task-head">
                                <div class="history-task-editor">
                                    {{ $firstChange->editor?->name ?? 'User' }}
                                </div>
                                <div class="history-task-time">
                                    {{ $firstChange->created_at?->format('d M Y, h:i A') }}
                                </div>
                            </div>

                            @foreach($changes as $change)
                                <div class="history-task-row">
                                    <div class="history-task-field">{{ $change->field_name }}</div>

                                    <div class="history-task-values">
                                        <div class="history-task-old">
                                            <del>{{ $change->old_value ?: '—' }}</del>
                                        </div>
                                        <div class="history-task-arrow">→</div>
                                        <div class="history-task-new">
                                            {{ $change->new_value ?: '—' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="history-nothing">No task edits have been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="bd-tab-panel" x-show="tab==='eod'" x-cloak><div class="panel"><div class="panel-header"><div><div class="panel-title">Task Updation</div><div style="font-size:10px;color:#667085;margin-top:3px">Designer Task Updation records and Rework uploads.</div></div></div><div class="panel-body">@if($reworkCount > 0)<div class="task-update-note">Rework Count: <strong>{{ $reworkCount }}</strong></div>@endif<div class="bd-eod-summary"><div class="bd-eod-card"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div><div class="bd-eod-card"><span>Completed</span><strong>{{ $eodCompletedTotal }}</strong></div><div class="bd-eod-card"><span>Remaining</span><strong>{{ $eodRemaining }}</strong></div></div>
        @forelse($eodRecords as $record)<div class="bd-eod-row"><div><strong>Submitted By</strong>{{ $record->designer?->name ?? '—' }}<br>{{ $record->submitted_at?->format('d M Y, h:i A') }}@if($record->attachment_url)<br><a target="_blank" href="{{ $record->attachment_url }}">{{ $record->attachment_original_name ?? 'Download ZIP' }}</a>@endif</div><div><strong>{{ ($record->update_type ?? 'progress') === 'rework' ? 'Update Type' : 'Progress Added' }}</strong>{{ ($record->update_type ?? 'progress') === 'rework' ? 'Rework #'.$record->rework_count_snapshot : $record->completed_count }}</div><div><strong>Total Creatives</strong>{{ $record->total_creatives_snapshot }}</div><div><strong>Total Completed</strong>{{ $record->cumulative_completed }}</div><div><strong>Remaining</strong>{{ $record->remaining_creatives }}</div></div>@empty<div class="empty-state">No Task Updation records have been submitted yet.</div>@endforelse
    </div></div></section>
</div>
@endsection
