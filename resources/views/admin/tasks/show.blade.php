@extends('layouts.app')
@section('title',$task->task_id)
@section('workspace-title','Task Detail')
@section('workspace-subtitle','Administrative task view and control')
@section('content')

<style>
    [x-cloak]{display:none!important}.bd-detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;max-width:100%;overflow:auto;margin-bottom:14px}
    .bd-detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
    .bd-detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}.bd-tab-count{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#fff0f1;color:#e30613;font-size:8px;display:grid;place-items:center}
    .bd-tab-panel{margin-top:0}
    .bd-request-card{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:12px;margin-bottom:9px}.bd-request-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.bd-request-title{font-size:11px;font-weight:900}.bd-request-meta{font-size:9px;color:#667085;margin-top:4px}.bd-request-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:10px}.bd-request-field{background:#f8f9fb;border-radius:8px;padding:8px;font-size:9px;color:#667085}.bd-request-field strong{display:block;color:#344054;font-size:8px;text-transform:uppercase;margin-bottom:3px}
    .bd-eod-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}.bd-eod-card{padding:13px;border-radius:12px;background:#fff;border:1px solid #e7e9ef}.bd-eod-card span{display:block;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.05em;color:#7c8492}.bd-eod-card strong{display:block;font-size:18px;margin-top:5px;color:#111827}.bd-eod-card:nth-child(1){background:#f8fafc;border-color:#e2e8f0}.bd-eod-card:nth-child(2){background:#ecfdf3;border-color:#bbf7d0}.bd-eod-card:nth-child(3){background:#fff7ed;border-color:#fed7aa}.bd-eod-card.rework-stat{background:#fff9eb;border-color:#f5d16a}.bd-eod-card.rework-stat span{color:#9a6700}.bd-eod-card.rework-stat strong{color:#7a5200}.bd-eod-overall{margin-bottom:16px;padding:14px;border:1px solid #e7e9ef;border-radius:12px;background:#f9fafb}.bd-eod-overall-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:11px;font-weight:850;color:#344054}.bd-eod-overall-head strong{font-size:15px;color:#111827}.bd-eod-row{border:1px solid #e7e9ef;border-radius:12px;padding:13px 14px;background:#fff;margin-bottom:10px;font-size:10px}.bd-eod-row.is-rework{border-color:#f2ce68;background:linear-gradient(180deg,#fffdf7,#fff9e8);box-shadow:inset 4px 0 0 #f5b301}.bd-eod-row.is-progress{border-left:4px solid #d9dee7}.bd-eod-row-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;padding-bottom:10px;margin-bottom:10px;border-bottom:1px solid #f2f4f7}.bd-eod-row-meta strong{display:block;font-size:11px;color:#111827;margin-top:2px}.bd-eod-row-meta span{display:block;margin-top:3px;font-size:9px;color:#7c8492}.bd-eod-grid{display:grid;grid-template-columns:repeat(4,minmax(90px,1fr));gap:10px}.bd-eod-grid div span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}.bd-eod-grid div strong{display:block;margin-top:3px;font-size:11px;color:#344054}.bd-eod-type-badge{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;margin-bottom:5px}.bd-eod-type-badge.rework{background:#fff0c2;color:#7a5200;border:1px solid #f2cf68}.bd-eod-type-badge.progress{background:#f2f4f7;color:#475467;border:1px solid #e4e7ec}
    .bd-attachment-group{border:1px solid #e4e7ec;border-radius:12px;background:#fff;overflow:hidden;margin-bottom:10px;padding:0}.bd-attachment-title{font-size:9px;font-weight:900;color:#344054;background:#f8fafc;padding:9px 11px;border-bottom:1px solid #eaecf0;margin:0}.bd-file{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:9px 11px;background:#fff;border-radius:0;margin:0;border-bottom:1px solid #f2f4f7}.bd-file:last-child{border-bottom:0}.bd-file-main{min-width:0}.bd-file-name{font-size:9px;font-weight:650;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.bd-file-actions{display:flex;gap:6px;align-items:center;flex-wrap:nowrap}.bd-file-btn{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:6px 10px;border-radius:8px;text-decoration:none;font-size:8px;font-weight:850;white-space:nowrap}.bd-file-open{background:#e30613;color:#fff;border:1px solid #e30613}.bd-file-download{background:#fff;color:#344054;border:1px solid #d0d5dd}
    .board-table-shell{width:100%;max-width:100%;overflow-x:auto;border:1px solid #e4e7ec;border-radius:10px;background:#fff}.board-details-table{width:100%;min-width:560px;border-collapse:collapse;table-layout:auto}.board-details-table th{background:#f8fafc;color:#344054;font-size:9px;font-weight:850;text-align:left;padding:9px 10px;border-bottom:1px solid #e4e7ec;white-space:nowrap}.board-details-table td{color:#101828;font-size:9px;font-weight:500;padding:9px 10px;border-bottom:1px solid #eef0f3;white-space:nowrap}.board-details-table tbody tr:last-child td{border-bottom:0}.board-table-empty{text-align:center;color:#98a2b3!important;padding:18px!important}
    @media(max-width:750px){.bd-request-grid,.bd-eod-summary{grid-template-columns:1fr}.bd-eod-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:700px){.bd-file{grid-template-columns:1fr}.bd-file-actions{justify-content:flex-start}.board-details-table{min-width:520px}}

    .progress-card{padding:13px;border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-top:12px}.progress-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.progress-title{font-size:10px;font-weight:900;color:#344054}.progress-value{font-size:11px;font-weight:950}.progress-track{height:9px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:8px}.progress-fill{height:100%;border-radius:999px;transition:width .25s}.progress-summary{margin-top:8px;font-size:9px;font-weight:750;color:#667085;text-align:left}.progress-start .progress-fill{background:#94a3b8}.progress-low .progress-fill{background:#f59e0b}.progress-mid .progress-fill{background:#3b82f6}.progress-high .progress-fill{background:#8b5cf6}.progress-complete .progress-fill{background:#16a34a}.progress-start .progress-value{color:#64748b}.progress-low .progress-value{color:#b45309}.progress-mid .progress-value{color:#1d4ed8}.progress-high .progress-value{color:#7c3aed}.progress-complete .progress-value{color:#15803d}.collapse-panel{border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-bottom:14px;overflow:hidden}.collapse-panel summary{list-style:none;cursor:pointer;padding:12px 14px;font-size:11px;font-weight:900;color:#1d2939;display:flex;justify-content:space-between;align-items:center}.collapse-panel summary::-webkit-details-marker{display:none}.collapse-panel summary:after{content:'+';font-size:17px;color:#667085}.collapse-panel[open] summary:after{content:'−'}.collapse-panel .collapse-body{border-top:1px solid #eef0f3;padding:14px}

    .history-switcher{display:flex;gap:6px;padding:6px;background:#f4f5f7;border:1px solid #e4e7ec;border-radius:12px;margin-bottom:14px;width:max-content}.history-switch-btn{border:0;background:transparent;color:#667085;border-radius:8px;padding:8px 13px;font-size:10px;font-weight:900;cursor:pointer}.history-switch-btn.active{background:#101828;color:#fff}.history-view-card{border:1px solid #e5e7eb;border-radius:14px;background:#fff;overflow:hidden}.history-view-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;background:linear-gradient(180deg,#fff,#f9fafb);border-bottom:1px solid #eaecf0}.history-view-title{font-size:12px;font-weight:950;color:#101828}.history-view-subtitle{font-size:9px;color:#667085;margin-top:3px}.history-view-count{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:4px 9px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:9px;font-weight:900}.history-view-body{padding:12px}.history-pipeline-card{border:1px solid #e7e9ee;border-left:4px solid #98a2b3;border-radius:11px;background:#fff;padding:11px 12px;margin-bottom:8px}.history-pipeline-card:last-child{margin-bottom:0}.history-pipeline-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.history-pipeline-title{font-size:10px;font-weight:950;color:#101828}.history-pipeline-meta{margin-top:4px;font-size:9px;color:#667085;line-height:1.45}.history-role-badge{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:8px;font-weight:900;white-space:nowrap}.history-pipeline-card.role-bd{border-left-color:#e30613}.history-pipeline-card.role-designer{border-left-color:#2563eb}.history-pipeline-card.role-designer_head{border-left-color:#7c3aed}.history-pipeline-card.role-admin{border-left-color:#111827}.history-role-badge.role-bd{background:#fff1f1;color:#b42318}.history-role-badge.role-designer{background:#eff6ff;color:#1d4ed8}.history-role-badge.role-designer_head{background:#f5f3ff;color:#6d28d9}.history-role-badge.role-admin{background:#111827;color:#fff}
    .history-task-batch{border:1px solid #e7e9ee;border-left:4px solid #7c3aed;border-radius:11px;background:#fff;margin-bottom:9px;overflow:hidden}.history-task-batch:last-child{margin-bottom:0}.history-task-head{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:#faf9ff;border-bottom:1px solid #ede9fe}.history-task-editor{font-size:10px;font-weight:900;color:#4c1d95}.history-task-time{font-size:9px;color:#7c8492}.history-task-row{padding:10px 12px;border-bottom:1px solid #f0f1f3}.history-task-row:last-child{border-bottom:0}.history-task-field{font-size:8px;font-weight:950;text-transform:uppercase;letter-spacing:.045em;color:#667085;margin-bottom:7px}.history-task-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}.history-task-old,.history-task-new{padding:8px 9px;border-radius:8px;font-size:9px;line-height:1.45;overflow-wrap:anywhere}.history-task-old{background:#fff1f1;color:#b42318;border:1px solid #fecaca}.history-task-new{background:#ecfdf3;color:#067647;border:1px solid #abefc6;font-weight:800}.history-task-arrow{text-align:center;color:#98a2b3;font-weight:900}.history-nothing{text-align:center;padding:34px 14px;color:#98a2b3;font-size:10px}
    @media(max-width:700px){.history-task-values{grid-template-columns:1fr}.history-task-arrow{transform:rotate(90deg)}}

    .bd-comment-feed{display:flex;flex-direction:column;gap:10px}.bd-comment{margin:0;padding:14px;border-radius:13px;background:#fff;border:1px solid #eaecf0;box-shadow:0 2px 8px rgba(16,24,40,.025)}.bd-comment-head{display:flex;justify-content:space-between;align-items:center;gap:10px}.bd-comment-person{display:flex;align-items:center;gap:9px;min-width:0}.bd-comment-avatar{width:30px;height:30px;border-radius:9px;background:#f2f4f7;display:grid;place-items:center;font-size:10px;font-weight:950;color:#344054;flex:0 0 auto}.bd-comment-name{font-size:10px;font-weight:900;color:#101828}.bd-comment-date{font-size:8px;color:#98a2b3;margin-top:2px}.bd-comment-message{margin-top:8px;font-size:10px;line-height:1.65;color:#344054;font-weight:450;white-space:pre-wrap}.bd-comment-files{margin-top:11px;display:flex;flex-direction:column;gap:6px}.bd-comment-file{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 9px;border:1px solid #eaecf0;border-radius:9px;background:#fafbfc}.bd-comment-file-primary{min-width:0;display:flex;align-items:center;gap:7px}.bd-comment-file-name{font-size:9px;font-weight:750;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px}.bd-comment-open{font-size:8px;font-weight:900;color:#e30613;text-decoration:none}.bd-comment-download{font-size:8px;font-weight:800;color:#667085;text-decoration:none}

    .rating-summary-shell{border:1px solid #f1d07a;border-radius:14px;background:linear-gradient(180deg,#fffdf7 0%,#fff9e9 100%);padding:14px;box-shadow:0 4px 14px rgba(245,179,1,.06)}.rating-summary-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.rating-summary-kicker{font-size:8px;font-weight:950;letter-spacing:.045em;text-transform:uppercase;color:#8a6200}.rating-summary-score{font-size:14px;font-weight:950;color:#624600;white-space:nowrap}.rating-summary-stars{display:flex;align-items:center;gap:4px;margin-top:7px;line-height:1}.rating-compact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}.rating-compact-item{border:1px solid #eee3bd;border-radius:11px;background:rgba(255,255,255,.74);padding:10px 11px;min-width:0}.rating-compact-head{display:flex;align-items:center;justify-content:space-between;gap:8px}.rating-compact-label{font-size:8px;font-weight:950;color:#5f6470;text-transform:uppercase;letter-spacing:.025em;line-height:1.35}.rating-compact-score{font-size:10px;font-weight:950;color:#5d4300;white-space:nowrap}.rating-compact-stars{display:flex;align-items:center;gap:3px;margin-top:7px;line-height:1}.rating-static-star{--star-fill:0%;display:inline-block;width:17px;height:17px;flex:0 0 17px;font-size:17px;line-height:17px;font-family:Arial,"Segoe UI Symbol",sans-serif;background:linear-gradient(90deg,#f5b301 0%,#f5b301 var(--star-fill),#d8dee8 var(--star-fill),#d8dee8 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}.rating-overall-item{border-color:#efcc69;background:#fffaf0}.rating-meta-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;margin-top:10px}.rating-comment-compact,.rating-submitted-compact{border:1px solid #e8e2cf;border-radius:10px;background:#fff;padding:10px 11px;font-size:9px;line-height:1.5;color:#475467}.rating-comment-compact strong,.rating-submitted-compact strong{color:#101828;font-weight:900}.rating-submitted-compact{min-width:220px}@media(max-width:760px){.rating-compact-grid{grid-template-columns:1fr}.rating-meta-row{grid-template-columns:1fr}.rating-submitted-compact{min-width:0}}
</style>

<div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'overview' }">
    <div class="page-head">
        <div>
            <h1>{{ $task->display_task_name ?? $task->task_name }}</h1>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}
                @if($task->decline_outcome_label)<span class="badge {{ str_contains($task->decline_outcome_label,'Rejected') ? 'badge-danger' : 'badge-success' }}" style="margin-left:6px">{{ str_contains($task->decline_outcome_label,'Approved') ? 'Task Transferred' : $task->decline_outcome_label }}</span>@endif
            </p>
        </div>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('admin.tasks.index') }}">Back to Tasks</a>
            <a class="btn btn-secondary" href="{{ route('admin.tasks.edit',$task) }}">Edit Task</a>
            <form method="POST" action="{{ route('admin.tasks.destroy',$task) }}" data-formal-confirm
                data-confirm-title="Delete Task?"
                data-confirm-message="Are you sure you want to delete {{ $task->task_id }} — {{ $task->display_task_name ?? $task->task_name }}?"
                data-confirm-label="Yes, Delete" data-processing-label="Deleting..." data-confirm-tone="danger">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn" style="background:#fff1f2;color:#b42318;border:1px solid #fecdd3">Delete Task</button>
            </form>
        </div>
    </div>

    <div class="bd-detail-tabs">
        <button class="bd-detail-tab" :class="{active:tab==='overview'}" @click="tab='overview'">Overview</button>
        <button class="bd-detail-tab" :class="{active:tab==='comments'}" @click="tab='comments'">Comments</button>
        @if($declineRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='decline-details'}" @click="tab='decline-details'">Decline Details</button>@endif
        @if($splitRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='split-details'}" @click="tab='split-details'">Split Details</button>@endif
        @if($swapRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='swap-details'}" @click="tab='swap-details'">Swap Details</button>@endif
        <button class="bd-detail-tab" :class="{active:tab==='history'}" @click="tab='history'">History</button>
        @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
            <button class="bd-detail-tab" :class="{active:tab==='eod'}" @click="tab='eod'">Progress Updates</button>
        @endif
        @if($task->status === 'completed')<button class="bd-detail-tab" :class="{active:tab==='ratings'}" @click="tab='ratings'">Ratings</button>@endif
    </div>

    <section class="bd-tab-panel" x-show="tab==='overview'">
        <div class="detail-grid">
            <div>
                <details class="collapse-panel"><summary>Task Information</summary><div class="collapse-body"><div class="info-grid">
                    @foreach(['Client / Agency'=>ucfirst($task->party_type).' · '.$task->party_name,'Contact'=>$task->contact_person.' · '.$task->mobile_number,'Vertical'=>ucwords(str_replace('_',' ',$task->vertical)),'Task Nature'=>ucwords(str_replace('_',' ',$task->task_nature)),'Priority'=>ucfirst($task->priority),'Designer'=>$task->designer?->name ?? '—','Assigned By'=>$task->assigner?->name ?? '—','Due Date'=>$task->due_at?->format('d M Y'),'Total Creatives'=>$task->total_creatives,'Assigned At'=>$task->assigned_at?->format('d M Y')] as $k=>$v)
                        <div class="info-item"><span>{{ $k }}</span><strong>{{ $v }}</strong></div>
                    @endforeach
                </div></div></details>

                <details class="collapse-panel"><summary>Task Requirements</summary><div class="collapse-body"><div class="requirement-list">
                    @forelse(($task->requirements ?? []) as $key=>$value)
                        @php
                            $isRequirementFile = (is_string($value) && str_contains($value,'/') && !filter_var($value,FILTER_VALIDATE_URL))
                                || (is_array($value) && collect($value)->contains(fn($item) => is_string($item) && str_contains($item,'/') && !filter_var($item,FILTER_VALIDATE_URL)));
                        @endphp
                        @continue(str_starts_with((string)$key,'_') || $isRequirementFile)
                        <div class="requirement-row"><div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div><div>@if($key === 'board_details' && is_array($value)) @include('partials.board-details-table',['rows'=>$value]) @elseif(is_array($value) && isset($value['square_feet'])) {{ $value['width'] ?? '' }} × {{ $value['height'] ?? '' }} feet = {{ $value['square_feet'] }} sq.ft @else {{ is_array($value) ? json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $value }} @endif</div></div>
                    @empty
                        <div class="empty-state">No requirement data available.</div>
                    @endforelse
                </div></div></details>

                <details class="collapse-panel"><summary><span class="collapse-summary-title">Attachments <span class="bd-tab-count">{{ $requirementAttachmentCount }}</span></span></summary><div class="collapse-body">
                    @forelse($requirementAttachmentGroups as $group)
                        <div class="bd-attachment-group">
                            <div class="bd-attachment-title">{{ $group['label'] }}</div>
                            @foreach($group['files'] as $file)
                                <div class="bd-file">
                                    <div class="bd-file-main"><div class="bd-file-name" title="{{ $file['name'] }}">{{ $file['name'] }}</div></div>
                                    <div class="bd-file-actions">
                                        <a class="bd-file-btn bd-file-open" target="_blank" href="{{ $file['url'] }}">Open</a>
                                        <a class="bd-file-btn bd-file-download" href="{{ $file['url'] }}" download>Download</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="empty-state">No task creation/edit attachments.</div>
                    @endforelse
                </div></details>
            </div>

            <aside>
                <section class="panel">
                    <div class="panel-header"><div class="panel-title">Current Status</div></div>
                    <div class="panel-body">
                        <span class="badge badge-red">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span>
                        @if(in_array($task->status,['in_progress','waiting_confirmation','rework'],true))
                            <div class="progress-card progress-{{ $progressColorKey }}">
                                <div class="progress-head"><span class="progress-title">Creative Progress</span><span class="progress-value">{{ $progressPercentage }}%</span></div>
                                <div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div>
                                <div class="progress-summary">{{ $eodCompletedTotal }} of {{ $task->total_creatives }} creatives completed • {{ $eodRemaining }} remaining</div>
                            </div>
                        @endif
                        <div class="activity-item" style="margin-top:12px"><strong>Assigned Designer</strong><p>{{ $task->designer?->name ?? '—' }}</p></div>
                        <div class="activity-item" style="margin-top:8px"><strong>Due Date</strong><p>{{ $task->due_at?->format('d M Y') }}</p></div>
                        <a href="{{ route('admin.tasks.edit',$task) }}" class="btn btn-primary" style="width:100%;margin-top:12px">Edit Task</a>
                    </div>
                </section>
            </aside>
        </div>
    </section>

    <section class="bd-tab-panel" x-show="tab==='comments'" x-cloak>
        <div class="panel">
            <div class="panel-header">
                <div><div class="panel-title">Comments</div><div style="font-size:9px;color:#667085;margin-top:4px">All task communication (read-only).</div></div>
                <span class="bd-tab-count">{{ $generalComments->count() }}</span>
            </div>
            <div class="panel-body">
                <div class="bd-comment-feed">
                    @forelse($generalComments as $comment)
                        @php
                            $cName = $comment->user?->name ?? 'User';
                            $cInitial = strtoupper(mb_substr($cName, 0, 1));
                        @endphp
                        <article class="bd-comment">
                            <div class="bd-comment-head">
                                <div class="bd-comment-person">
                                    <div class="bd-comment-avatar">{{ $cInitial }}</div>
                                    <div><div class="bd-comment-name">{{ $cName }}</div><div class="bd-comment-date">{{ $comment->created_at?->format('d M Y · h:i A') }}</div></div>
                                </div>
                                <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$comment->user?->role ?? 'user')) }}</span>
                            </div>
                            <div class="bd-comment-message">{{ $comment->comment }}</div>
                            @if($comment->attachments->isNotEmpty())
                                <div class="bd-comment-files">
                                    @foreach($comment->attachments as $attachment)
                                        <div class="bd-comment-file">
                                            <div class="bd-comment-file-primary"><span class="bd-comment-file-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span><a target="_blank" class="bd-comment-open" href="{{ $attachment->url }}">Open</a></div>
                                            <a class="bd-comment-download" href="{{ $attachment->url }}" download>Download</a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="empty-state">No comments yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    @if($declineRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='decline-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Decline Details</div></div><div class="panel-body">
        @foreach($declineRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Decline {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">{{ $request->created_at?->format('d M Y · h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested At</strong>{{ $request->created_at?->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Requested By</strong>{{ $request->requester?->name ?? '—' }}</div><div class="bd-request-field"><strong>Responded At</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? 'Pending Response' : optional($request->admin_action_at ?: $request->designer_head_action_at)->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded By</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? '—' : (($request->adminActor ?: $request->designerHeadActor)?->name ?? '—') }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div><div class="bd-request-field" style="grid-column:1/-1"><strong>Request Reason</strong>{{ $request->reason }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>{{ $request->overall_status === 'approved' ? 'Approval Reason' : 'Decline Reason' }}</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    @if($splitRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='split-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Split Details</div></div><div class="panel-body">
        @foreach($splitRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Split {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y · h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested At</strong>{{ $request->created_at?->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded At</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? 'Pending Response' : optional($request->admin_action_at ?: $request->designer_head_action_at)->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded By</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? '—' : (($request->adminActor ?: $request->designerHeadActor)?->name ?? '—') }}</div><div class="bd-request-field"><strong>Requested Split</strong>{{ data_get($request,'split_count') ?? data_get($request,'split_details.requested_count') ?? data_get($request,'split_details.creative_count') ?? '—' }}</div><div class="bd-request-field"><strong>Approved Split</strong>{{ data_get($request,'approved_split_count') ?? data_get($request,'split_details.approved_count') ?? data_get($request,'split_details.approved_creative_count') ?? '—' }}</div><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    @if($swapRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='swap-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Swap Details</div></div><div class="panel-body">
        @foreach($swapRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Swap {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y · h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested At</strong>{{ $request->created_at?->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded At</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? 'Pending Response' : optional($request->admin_action_at ?: $request->designer_head_action_at)->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded By</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? '—' : (($request->adminActor ?: $request->designerHeadActor)?->name ?? '—') }}</div><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    <section class="bd-tab-panel" x-show="tab==='history'" x-cloak x-data="{ historyView: 'pipeline' }">
        <div class="history-switcher">
            <button type="button" class="history-switch-btn" :class="{ 'active': historyView === 'pipeline' }" @click="historyView='pipeline'">Pipeline History</button>
            <button type="button" class="history-switch-btn" :class="{ 'active': historyView === 'task' }" @click="historyView='task'">Edit History</button>
        </div>

        <div x-show="historyView === 'pipeline'">
            <div class="history-view-card">
                <div class="history-view-head"><div><div class="history-view-title">Pipeline History</div><div class="history-view-subtitle">Complete workflow, comment and status activity.</div></div><span class="history-view-count">{{ $pipelineEvents->count() }} Events</span></div>
                <div class="history-view-body">
                    @forelse($pipelineEvents as $event)
                        @php $historyRole = $event['role'] ?? 'default'; @endphp
                        <div class="history-pipeline-card role-{{ $historyRole }}">
                            <div class="history-pipeline-top">
                                <div class="history-pipeline-title">{{ $event['title'] }}</div>
                                <span class="history-role-badge role-{{ $historyRole }}">{{ $historyRole === 'default' ? 'System' : ucwords(str_replace('_', ' ', $historyRole)) }}</span>
                            </div>
                            <div class="history-pipeline-meta">{{ str_ireplace('reconciled', 'moved to', $event['description']) }} · {{ $event['created_at']?->format('d M Y · h:i A') }}</div>
                        </div>
                    @empty
                        <div class="history-nothing">No pipeline events have been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="historyView === 'task'" x-cloak>
            <div class="history-view-card">
                <div class="history-view-head"><div><div class="history-view-title">Edit History</div><div class="history-view-subtitle">Every recorded change to task information.</div></div><span class="history-view-count">{{ $editHistory->count() }} Updates</span></div>
                <div class="history-view-body">
                    @forelse($editHistory as $changes)
                        @php $firstChange = $changes->first(); @endphp
                        <div class="history-task-batch">
                            <div class="history-task-head"><div class="history-task-editor">{{ $firstChange->editor?->name ?? 'User' }}</div><div class="history-task-time">{{ $firstChange->created_at?->format('d M Y') }}</div></div>
                            @foreach($changes as $change)
                                <div class="history-task-row">
                                    <div class="history-task-field">{{ $change->field_name }}</div>
                                    <div class="history-task-values"><div class="history-task-old"><del>{{ $change->old_value ?: '—' }}</del></div><div class="history-task-arrow">→</div><div class="history-task-new">{{ $change->new_value ?: '—' }}</div></div>
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

    @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
    <section class="bd-tab-panel" x-show="tab==='eod'" x-cloak>
        <div class="panel">
            <div class="panel-header"><div><div class="panel-title">Progress Updates</div><div style="font-size:10px;color:#667085;margin-top:3px">Designer Progress Updates records and Rework uploads.</div></div></div>
            <div class="panel-body">
                <div class="bd-eod-overall">
                    <div class="bd-eod-overall-head"><span>Overall Completion</span><strong>{{ $progressPercentage }}%</strong></div>
                    <div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div>
                </div>

                <div class="bd-eod-summary">
                    <div class="bd-eod-card"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div>
                    <div class="bd-eod-card"><span>Completed</span><strong>{{ $eodCompletedTotal }}</strong></div>
                    <div class="bd-eod-card"><span>Remaining</span><strong>{{ $eodRemaining }}</strong></div>
                    <div class="bd-eod-card rework-stat"><span>Rework Count</span><strong>{{ $reworkCount }}</strong></div>
                </div>

                @forelse($eodRecords as $record)
                    @php $isReworkRecord = ($record->update_type ?? 'progress') === 'rework'; @endphp
                    <div class="bd-eod-row {{ $isReworkRecord ? 'is-rework' : 'is-progress' }}">
                        <div class="bd-eod-row-head">
                            <div class="bd-eod-row-meta">
                                <span class="bd-eod-type-badge {{ $isReworkRecord ? 'rework' : 'progress' }}">{{ $isReworkRecord ? 'Rework Submission' : 'Progress Submission' }}</span>
                                <strong>Submitted by {{ $record->designer?->name ?? '—' }}</strong>
                                <span>{{ $record->submitted_at?->format('d M Y · h:i A') }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                @if($isReworkRecord)<span class="badge badge-danger">Rework #{{ $record->rework_count_snapshot }}</span>@endif
                                @if($record->attachment_url)
                                    <a class="bd-file-btn bd-file-download" target="_blank" href="{{ $record->attachment_url }}" title="Download">⬇ {{ $record->attachment_original_name ?? 'Download ZIP' }}</a>
                                @else
                                    <span class="muted" style="font-size:9px">No file available</span>
                                @endif
                            </div>
                        </div>
                        <div class="bd-eod-grid">
                            <div><span>{{ $isReworkRecord ? 'Reworked Creatives' : 'Progress Added' }}</span><strong>{{ $record->completed_count }}</strong></div>
                            <div><span>Total Creatives</span><strong>{{ $record->total_creatives_snapshot }}</strong></div>
                            <div><span>Total Completed</span><strong>{{ $record->cumulative_completed }}</strong></div>
                            <div><span>Remaining</span><strong>{{ $record->remaining_creatives }}</strong></div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No Progress Updates records have been submitted yet.</div>
                @endforelse
            </div>
        </div>
    </section>
    @endif

    @if($task->status === 'completed')
    <section class="bd-tab-panel" x-show="tab==='ratings'" x-cloak><div class="panel"><div class="panel-header"><div><div class="panel-title">Ratings</div><div style="font-size:9px;color:#667085;margin-top:3px">Final BD rating submitted when the task was completed.</div></div></div><div class="panel-body">
        @if(! $taskRating)
            <div class="empty-state">No rating available.</div>
        @else
            @php $overallRatingValue = max(0, min(5, (float) $taskRating->overall_rating)); @endphp
            <div class="rating-summary-shell">
                <div class="rating-summary-top">
                    <div>
                        <div class="rating-summary-kicker">Overall Rating</div>
                        <div class="rating-summary-stars" aria-label="{{ number_format($overallRatingValue, 2) }} out of 5 stars">
                            @for($starIndex = 1; $starIndex <= 5; $starIndex++)
                                @php $starFill = $overallRatingValue >= $starIndex ? 100 : ($overallRatingValue >= ($starIndex - 0.5) ? 50 : 0); @endphp
                                <span class="rating-static-star" style="--star-fill:{{ $starFill }}%;" aria-hidden="true">★</span>
                            @endfor
                        </div>
                    </div>
                    <div class="rating-summary-score">{{ \App\Models\DesignTaskBdReview::formatRating($overallRatingValue) }} / 5</div>
                </div>
                <div class="rating-compact-grid">
                    @foreach([
                        'Designer Attitude' => $taskRating->designer_attitude,
                        'Design Satisfaction' => $taskRating->design_satisfaction,
                        'Rework Iteration' => $taskRating->rework_iteration,
                        'Meeting Deadline' => $taskRating->meeting_deadline,
                        'Client Satisfaction' => $taskRating->client_satisfaction,
                        'Overall Rating' => $taskRating->overall_rating,
                    ] as $label => $value)
                        @php $ratingValue = max(0, min(5, (float) $value)); @endphp
                        <div class="rating-compact-item {{ $label === 'Overall Rating' ? 'rating-overall-item' : '' }}">
                            <div class="rating-compact-head">
                                <span class="rating-compact-label">{{ $label }}</span>
                                <span class="rating-compact-score">{{ \App\Models\DesignTaskBdReview::formatRating($ratingValue) }} / 5</span>
                            </div>
                            <div class="rating-compact-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                @for($starIndex = 1; $starIndex <= 5; $starIndex++)
                                    @php $starFill = $ratingValue >= $starIndex ? 100 : ($ratingValue >= ($starIndex - 0.5) ? 50 : 0); @endphp
                                    <span class="rating-static-star" style="--star-fill:{{ $starFill }}%;" aria-hidden="true">★</span>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="rating-meta-row">
                    <div class="rating-comment-compact"><strong>Comments</strong><br>{{ $taskRating->comment ?: 'No comments added.' }}</div>
                    <div class="rating-submitted-compact">Submitted by <strong>{{ $taskRating->submitter?->name ?? 'BD' }}</strong><br><span>{{ $taskRating->created_at?->format('d M Y') }}</span></div>
                </div>
            </div>
        @endif
    </div></div></section>
    @endif
</div>

<x-formal-confirm-dialog />
@endsection
