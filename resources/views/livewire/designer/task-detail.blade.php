<div
    x-data="{
        tab: 'overview',
        toast: '',
        attachmentPreviewOpen: false,
        attachmentPreviewUrl: '',
        attachmentPreviewName: '',
        attachmentDownloadUrl: '',
        openAttachment(url, name, downloadUrl) {
            this.attachmentPreviewUrl = url;
            this.attachmentPreviewName = name || 'Attachment';
            this.attachmentDownloadUrl = downloadUrl || '';
            this.attachmentPreviewOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeAttachment() {
            this.attachmentPreviewOpen = false;
            this.attachmentPreviewUrl = '';
            this.attachmentPreviewName = '';
            this.attachmentDownloadUrl = '';
            document.body.style.overflow = '';
        }
    }"
    x-on:task-status-changed.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:comment-added.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:eod-updated.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:request-created.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
>
    <style>
        .task-operation-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent;vertical-align:middle;white-space:nowrap}.task-operation-pill-split{color:#6938ef;background:linear-gradient(135deg,#f4f0ff,#ede9fe);border-color:#d9d6fe}.task-operation-pill-swap{color:#175cd3;background:linear-gradient(135deg,#eff8ff,#e6f1ff);border-color:#b2ddff}.task-operation-pill-pending{color:#9a6700;background:#fff8e6;border-color:#f5d680}.task-operation-pill-declined{color:#b42318;background:#fff1f0;border-color:#f7b4ae}.task-operation-pill-approved{box-shadow:0 2px 8px rgba(16,24,40,.05)}
        .detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;margin-bottom:14px;max-width:100%;overflow:auto}
        .detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center}
        .detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}
        .comment-box{border:1px solid #e7e9ef;border-radius:13px;padding:14px;background:#fff}
        .comment-textarea{width:100%;min-height:115px;border:1px solid #dfe2e8;border-radius:10px;padding:11px;font:inherit;font-size:11px;resize:vertical;outline:none}
        .comment-textarea:focus{border-color:#e30613;box-shadow:0 0 0 3px rgba(227,6,19,.08)}
        .comment-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
        .comment-item{padding:12px;border:1px solid #e7e9ef;border-left:4px solid #d0d5dd;border-radius:11px;background:#fff;margin-top:8px}
        .comment-item.role-designer{border-left-color:#2563eb}.comment-item.role-bd{border-left-color:#16a34a}.comment-item.role-designer_head{border-left-color:#7c3aed}.comment-item.role-admin{border-left-color:#e30613}
        .role-pill{display:inline-flex;align-items:center;border-radius:999px;padding:3px 7px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .role-pill.role-designer{background:#eff6ff;color:#1d4ed8}.role-pill.role-bd{background:#ecfdf3;color:#15803d}.role-pill.role-designer_head{background:#f5f3ff;color:#6d28d9}.role-pill.role-admin{background:#fff1f2;color:#e30613}.role-pill.role-default{background:#f2f4f7;color:#667085}
        .history-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border:1px solid #ebe7ff;background:linear-gradient(180deg,#fbfaff 0%,#f7f5ff 100%);border-radius:12px;margin-bottom:12px}.history-header-title{font-size:13px;font-weight:900;color:#4f2db8;letter-spacing:-.01em}.history-count{display:inline-flex;align-items:center;justify-content:center;min-width:62px;padding:5px 9px;border-radius:999px;background:#efe9ff;color:#6d28d9;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}.history-list{display:flex;flex-direction:column;gap:9px}.history-item{border:1px solid #e7e9ef;border-left:4px solid #d0d5dd;border-radius:12px;padding:12px 14px;background:#fff;box-shadow:0 2px 8px rgba(16,24,40,.025)}.history-item.role-designer{border-left-color:#2563eb}.history-item.role-bd{border-left-color:#e30613}.history-item.role-designer_head{border-left-color:#7c3aed}.history-item.role-admin{border-left-color:#111827}.history-event-title{font-size:12px;font-weight:900;color:#17191f;line-height:1.35}.history-meta{margin-top:5px;font-size:10px;color:#7a8494;line-height:1.55}.history-description{color:#4f5b6b;font-weight:600}.history-time{color:#98a2b3}.history-item:hover{border-color:#dfe3ea;box-shadow:0 5px 16px rgba(16,24,40,.045)}
        .special-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.special-detail-card{border:1px solid #e7e9ef;border-radius:12px;padding:12px;background:#fff}.special-detail-card span{display:block;font-size:9px;text-transform:uppercase;color:#7c8492;font-weight:800;letter-spacing:.05em}.special-detail-card strong{display:block;margin-top:5px;font-size:12px;color:#16181d}
        .eod-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .eod-stat{border:1px solid #e7e9ef;border-radius:12px;padding:13px;background:#fff}
        .eod-stat span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#7c8492;font-weight:850}
        .eod-stat strong{display:block;margin-top:5px;font-size:18px;color:#111827}
        .eod-entry-form{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;padding:14px;border:1px solid #e7e9ef;border-radius:12px;background:#f9fafb;margin-bottom:14px}
        .eod-field{min-width:220px;flex:1}
        .eod-history{display:flex;flex-direction:column;gap:9px}
        .eod-record{display:grid;grid-template-columns:1.1fr repeat(4,minmax(90px,.7fr));gap:10px;align-items:center;padding:12px 14px;border:1px solid #e7e9ef;border-radius:12px;background:#fff}
        .eod-record-main strong{display:block;font-size:11px;color:#111827}
        .eod-record-main span{display:block;margin-top:3px;font-size:9px;color:#7c8492}
        .eod-record-cell span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
        .eod-record-cell strong{display:block;margin-top:3px;font-size:11px;color:#344054}
        .eod-zero{color:#15803d!important}
        .muted{color:#7c8492;font-size:10px}
        .attachment-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
        .attachment-download{display:inline-flex;align-items:center;justify-content:center;padding:6px 9px;border:1px solid #dfe3ea;border-radius:8px;background:#fff;color:#344054;font-size:9px;font-weight:850;text-decoration:none;white-space:nowrap}
        .attachment-download:hover{background:#f7f8fa;border-color:#cfd4dc;color:#111827}
        .attachment-preview-backdrop{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.62);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:24px}
        .attachment-preview-modal{width:min(1120px,96vw);height:min(820px,92vh);background:#fff;border-radius:16px;box-shadow:0 28px 90px rgba(0,0,0,.3);overflow:hidden;display:flex;flex-direction:column}
        .attachment-preview-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 15px;border-bottom:1px solid #e7e9ef;background:#fff}
        .attachment-preview-title{min-width:0;font-size:12px;font-weight:900;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .attachment-preview-actions{display:flex;align-items:center;gap:7px;flex-shrink:0}
        .attachment-preview-download,.attachment-preview-close{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:7px 11px;border-radius:9px;font-size:10px;font-weight:850;text-decoration:none;cursor:pointer}
        .attachment-preview-download{background:#111827;color:#fff;border:1px solid #111827}
        .attachment-preview-close{background:#fff;color:#344054;border:1px solid #dfe3ea}
        .attachment-preview-body{flex:1;min-height:0;background:#f3f4f6;padding:10px}
        .attachment-preview-frame{width:100%;height:100%;border:0;border-radius:10px;background:#fff}
        @media(max-width:700px){.attachment-preview-backdrop{padding:10px}.attachment-preview-modal{width:100%;height:92vh;border-radius:13px}.attachment-preview-head{padding:10px}.attachment-preview-download,.attachment-preview-close{padding:6px 9px;font-size:9px}}
        .swap-readonly-note{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:750;margin-bottom:12px}
        .toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;font-size:11px;box-shadow:0 15px 40px rgba(0,0,0,.2)}
        .btn[disabled],button[disabled]{opacity:.55;cursor:not-allowed!important;pointer-events:none;transform:none!important}
        .btn.is-loading{position:relative}
        .btn.is-loading::after{content:'';width:11px;height:11px;margin-left:7px;border:2px solid currentColor;border-right-color:transparent;border-radius:999px;display:inline-block;vertical-align:-2px;animation:btn-spin .65s linear infinite}
        @keyframes btn-spin{to{transform:rotate(360deg)}}
        @media(max-width:900px){.comment-actions{align-items:flex-start;flex-direction:column}.special-detail-grid{grid-template-columns:1fr}.eod-summary{grid-template-columns:1fr}.eod-record{grid-template-columns:1fr 1fr}.eod-entry-form{align-items:stretch;flex-direction:column}.eod-field{min-width:0;width:100%}}

        .edit-history-list{display:flex;flex-direction:column;gap:10px}
        .edit-history-batch{border:1px solid #e7e9ee;border-radius:12px;background:#fff;overflow:hidden}
        .edit-history-batch-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:#f8f9fb;border-bottom:1px solid #eceef2}
        .edit-history-editor{font-size:10px;font-weight:850;color:#344054}.edit-history-time{font-size:9px;color:#7b8493}
        .edit-history-row{padding:11px 12px;border-bottom:1px solid #f0f1f3}.edit-history-row:last-child{border-bottom:0}
        .edit-history-field{font-size:9px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;color:#667085;margin-bottom:7px}
        .edit-history-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}
        .edit-history-old,.edit-history-new{padding:9px 10px;border-radius:9px;font-size:10px;line-height:1.45;overflow-wrap:anywhere}
        .edit-history-old{background:#fff1f1;border:1px solid #fecaca;color:#9b1c1c}.edit-history-old del{text-decoration-thickness:1.5px}
        .edit-history-new{background:#ecfdf3;border:1px solid #abefc6;color:#067647;font-weight:750}.edit-history-arrow{text-align:center;color:#98a2b3;font-weight:900}

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

    </style>

    <div class="page-head">
        <div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h1 style="margin:0">{{ $task->display_task_name }}</h1>
                @foreach($task->operation_pills as $pill)
                    <span class="{{ $pill['class'] }}">{{ $pill['label'] }}</span>
                @endforeach
                @if($swapInitiatorReadOnly)
                    <span class="badge badge-dark">Comment Only</span>
                @endif
            </div>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? $task->status }}</p>
        </div>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('designer.tasks.index') }}">Back to My Tasks</a>

            @unless($swapInitiatorReadOnly)

            @if(in_array('decline', $allowedRequestTypes, true))
                @if(in_array('decline', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Decline Pending</span>
                @else
                    <button class="btn btn-danger" wire:click="$dispatch('open-request-modal', { type: 'decline' })">Decline</button>
                @endif
            @endif

            @if(in_array('split', $allowedRequestTypes, true))
                @if(in_array('split', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Split Pending</span>
                @else
                    <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'split' })">Request Split</button>
                @endif
            @endif

            @if(in_array('swap', $allowedRequestTypes, true))
                @if(in_array('swap', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Swap Pending</span>
                @else
                    <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'swap' })">Request Swap</button>
                @endif
            @endif

            @if($nextStatus)
                <button
                    class="btn btn-primary"
                    wire:click="moveToNextStatus"
                    wire:loading.attr="disabled"
                    wire:target="moveToNextStatus"
                    wire:loading.class="is-loading"
                >
                    Move to {{ $statuses[$nextStatus] ?? ucwords(str_replace('_', ' ', $nextStatus)) }}
                </button>
            @endif
            @endunless
        </div>
    </div>

    @if($swapInitiatorReadOnly)
        <div class="swap-readonly-note">
            Swap approved. You can view this task and add comments only.
        </div>
    @endif

    <div class="detail-tabs">
        <button class="detail-tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
        <button class="detail-tab" :class="{ active: tab === 'comments' }" @click="tab = 'comments'">Comments</button>
@if($splitRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'split-details' }" @click="tab = 'split-details'">Split Details</button>
        @endif
        @if($swapRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'swap-details' }" @click="tab = 'swap-details'">Swap Details</button>
        @endif
        <button class="detail-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'">History</button>
        @if(in_array($task->status, ['in_progress','rework'], true))
            <button class="detail-tab" :class="{ active: tab === 'eod' }" @click="tab = 'eod'">Task Updation</button>
        @endif
    </div>

    <section x-show="tab === 'overview'">
        <div class="detail-grid">
            <div>
                <details class="collapse-panel">
                    <summary>Task Information</summary>
                    <div class="collapse-body">
                        <div class="info-grid">
                            @foreach([
                                'Client / Agency' => ucfirst($task->party_type).' · '.$task->party_name,
                                'Contact Person' => $task->contact_person,
                                'Mobile Number' => $task->mobile_number,
                                'Vertical' => ucwords(str_replace('_', ' ', $task->vertical)),
                                'Task Nature' => ucwords(str_replace('_', ' ', $task->task_nature)),
                                'Priority' => ucfirst($task->priority),
                                'Due Date' => \Illuminate\Support\Carbon::parse($task->due_at)->format('d M Y, h:i A'),
                                'Assigned By' => $task->assigner?->name ?? 'BD',
                                'Assigned At' => \Illuminate\Support\Carbon::parse($task->assigned_at)->format('d M Y, h:i A'),
                                'Total Creatives' => $task->total_creatives,
                            ] as $key => $value)
                                <div class="info-item">
                                    <span>{{ $key }}</span>
                                    <strong>{{ $value }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>

                <details class="collapse-panel">
                    <summary>Requirement Details</summary>
                    <div class="collapse-body">
                        <div class="requirement-list">
                            @forelse(($task->requirements ?? []) as $key => $value)
                                @php
                                    $isRequirementFile = (is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                        || (is_array($value) && collect($value)->contains(fn ($item) => is_string($item) && str_contains($item, '/') && !filter_var($item, FILTER_VALIDATE_URL)));
                                @endphp
                                @continue(str_starts_with((string) $key, '_') || $isRequirementFile)
                                <div class="requirement-row">
                                    <div class="requirement-key">{{ ucwords(str_replace('_', ' ', $key)) }}</div>

                                    <div>
                                        @if(is_array($value))
                                            @if(isset($value['square_feet']))
                                                {{ $value['width'] ?? '' }} × {{ $value['height'] ?? '' }} feet = {{ $value['square_feet'] }} sq.ft
                                            @else
                                                @foreach($value as $item)
                                                    @if(is_string($item) && str_contains($item, '/') && !filter_var($item, FILTER_VALIDATE_URL))
                                                        <div class="attachment-actions" style="margin-bottom:4px">
                                                            <a class="file-link" href="#" @click.prevent="openAttachment('{{ Storage::disk('spaces')->url($item) }}', '{{ basename($item) }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($item)]) }}')">{{ basename($item) }}</a>
                                                            <a class="attachment-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($item)]) }}">Download</a>
                                                        </div>
                                                    @else
                                                        <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        @elseif(is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                            <span class="attachment-actions">
                                                <a class="file-link" href="#" @click.prevent="openAttachment('{{ Storage::disk('spaces')->url($value) }}', '{{ basename($value) }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($value)]) }}')">{{ basename($value) }}</a>
                                                <a class="attachment-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($value)]) }}">Download</a>
                                            </span>
                                        @elseif(is_string($value) && filter_var($value, FILTER_VALIDATE_URL))
                                            <a class="file-link" href="{{ $value }}">{{ $value }}</a>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">No requirement data available.</div>
                            @endforelse
                        </div>
                    </div>
                </details>

                <details class="collapse-panel">
                    <summary>Attachments <span class="tab-count">{{ $requirementAttachmentCount }}</span></summary>
                    <div class="collapse-body">
                        @forelse($requirementAttachmentGroups as $group)
                            <div class="attachment-group">
                                <h3>{{ $group['label'] }}</h3>
                                @foreach($group['files'] as $file)
                                    <div class="attachment-file">
                                        <span>{{ $file['name'] }}</span>
                                        <div class="attachment-actions">
                                            <a class="file-link" href="#" @click.prevent="openAttachment('{{ $file['url'] }}', '{{ $file['name'] }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($file['path'])]) }}')">Open</a>
                                            <a class="attachment-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($file['path'])]) }}">Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <div class="empty-state">No task creation/edit attachments.</div>
                        @endforelse
                    </div>
                </details>
            </div>

            <aside>
                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Current Stage</div></div>
                    <div class="panel-body">
                        <span class="badge badge-red">{{ $statuses[$task->status] ?? $task->status }}</span>

                        <div class="progress-card progress-{{ $progressColorKey }}">
                            <div class="progress-head">
                                <span class="progress-title">Creative Progress</span>
                                <span class="progress-value">{{ $eodCompletedTotal }} / {{ $task->total_creatives }} · {{ $progressPercentage }}%</span>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div>
                        </div>

                        <div class="activity-item" style="margin-top:12px">
                            <strong>Last Updated</strong>
                            <p>{{ $task->updated_at->diffForHumans() }}</p>
                        </div>

                        @if($nextStatus)
                            @php
                                $waitingGateBlocked = $nextStatus === 'waiting_confirmation'
                                    && ($progressPercentage < 100 || ($task->status === 'rework' && ! $currentReworkHasUpload));
                            @endphp

                            <button
                                class="btn btn-primary"
                                style="width:100%;margin-top:10px"
                                wire:click="moveToNextStatus"
                                wire:loading.attr="disabled"
                                wire:target="moveToNextStatus"
                                @disabled($waitingGateBlocked)
                            >
                                {{ $nextStatus === 'waiting_confirmation' ? 'Move to Waiting for Confirmation' : 'Move to Next Stage' }}
                            </button>

                            @if($waitingGateBlocked)
                                <div class="muted" style="margin-top:7px;color:#b45309">
                                    @if($progressPercentage < 100)
                                        Complete 100% creative progress first.
                                    @else
                                        Upload the current Rework ZIP first.
                                    @endif
                                </div>
                            @endif
                        @endif

                        <button class="btn btn-secondary" style="width:100%;margin-top:8px" @click="tab = 'comments'">Add Comment</button>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section x-show="tab === 'comments'" style="display:none">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Comments & Attachments</div>
            </div>

            <div class="panel-body">
                <div class="comment-box">
                    <label class="label">Add comment at {{ $statuses[$task->status] ?? $task->status }}</label>
                    <textarea class="comment-textarea" wire:model="comment" placeholder="Type your update, clarification or design note..."></textarea>

                    @error('comment')
                        <div class="muted" style="color:#b4232f;margin-top:6px">{{ $message }}</div>
                    @enderror

                    <div class="comment-actions">
                        <div>
                            <input type="file" wire:model="attachments" multiple>
                            <div class="muted" style="margin-top:4px">Up to 10 files, maximum 100 MB each.</div>
                            <div class="muted" wire:loading wire:target="attachments">Preparing attachment...</div>
                            @error('attachments.*')
                                <div class="muted" style="color:#b4232f">{{ $message }}</div>
                            @enderror
                        </div>

                        <button
                            class="btn btn-primary"
                            wire:click="addComment"
                            wire:loading.attr="disabled"
                            wire:target="addComment,attachments"
                            wire:loading.class="is-loading"
                        >
                            Add Comment
                        </button>
                    </div>
                </div>

                <div style="margin-top:14px">
                    @forelse($comments as $item)
                        @php $commentRole = $item->user?->role ?? 'default'; @endphp
                        <article class="comment-item role-{{ $commentRole }}">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
                                    <strong style="font-size:11px">{{ $item->user?->name ?? 'User' }}</strong>
                                    <span class="role-pill role-{{ $commentRole }}">{{ ucwords(str_replace('_', ' ', $commentRole)) }}</span>
                                </div>
                                <span class="badge badge-dark">{{ $statuses[$item->status_at_comment] ?? $item->status_at_comment }}</span>
                            </div>

                            <div style="margin-top:12px">
                                <p style="margin:0;font-size:18px;line-height:1.6;font-weight:700;color:#111827;white-space:pre-wrap;letter-spacing:-.01em">{{ $item->comment }}</p>
                            </div>

                            @if($item->attachments->isNotEmpty())
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px">
                                    @foreach($item->attachments as $attachment)
                                        <a
                                            class="file-link"
                                            href="#"
                                            @click.prevent="openAttachment('{{ $attachment->url }}', '{{ $attachment->original_name }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}')"
                                            style="display:inline-flex;align-items:center;gap:5px;max-width:280px;padding:5px 8px;border:1px solid #e5e7eb;border-radius:8px;background:#fff7f7;font-size:10px;line-height:1.2;font-weight:750;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                            title="{{ $attachment->original_name }}"
                                        >
                                            <span style="font-size:10px">📎</span>
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $attachment->original_name }}</span>
                                        </a>
                                        <a
                                            class="attachment-download"
                                            href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}"
                                            title="Download {{ $attachment->original_name }}"
                                        >
                                            Download
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="muted" style="margin-top:10px;font-size:10px">{{ $item->created_at->format('d M Y, h:i A') }}</div>
                        </article>
                    @empty
                        <div class="empty-state">No comments have been added yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>


    <section x-show="tab === 'eod'" style="display:none">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Task Updation</div>
                    <div class="muted" style="margin-top:3px">
                        Update creative progress with a mandatory ZIP attachment.
                    </div>
                </div>
                <span class="badge badge-dark">{{ $eodRecords->count() }} Updates</span>
            </div>

            <div class="panel-body">
                <div class="eod-summary">
                    <div class="eod-stat">
                        <span>Total Creatives</span>
                        <strong>{{ $task->total_creatives }}</strong>
                    </div>
                    <div class="eod-stat">
                        <span>Completed</span>
                        <strong>{{ $eodCompletedTotal }}</strong>
                    </div>
                    <div class="eod-stat">
                        <span>Remaining</span>
                        <strong class="{{ $eodRemaining === 0 ? 'eod-zero' : '' }}">{{ $eodRemaining }}</strong>
                    </div>
                </div>


                @if($task->status === 'rework')
                    <div class="rework-box">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong>Rework Creative Upload</strong><span class="badge badge-danger">Rework #{{ $reworkCount }}</span></div>
                        <p class="muted" style="margin:6px 0 10px">Upload the corrected creative as a ZIP file for the current rework cycle.</p>
                        <input class="field" type="file" accept=".zip,application/zip" wire:model="reworkAttachment">
                        @error('reworkAttachment')<div class="error">{{ $message }}</div>@enderror
                        <button class="btn btn-primary" style="margin-top:10px" wire:click="submitReworkUpdate" wire:loading.attr="disabled" wire:target="submitReworkUpdate,reworkAttachment">Upload Rework Creative</button>
                        @if($currentReworkHasUpload)<div style="margin-top:8px;color:#15803d;font-size:10px;font-weight:800">Current rework ZIP uploaded.</div>@endif
                    </div>
                @endif
                @if($swapInitiatorReadOnly)
                    <div class="empty-state" style="margin-bottom:14px">
                        Task Updation history is view-only after an approved swap.
                    </div>
                @elseif($task->status === 'in_progress' && $eodRemaining > 0)
                    <div class="eod-entry-form">
                        <div class="eod-field">
                            <label class="label" for="eodCompletedCount">Number of Creatives</label>
                            <input
                                id="eodCompletedCount"
                                class="premium-input"
                                type="number"
                                min="1"
                                max="{{ $eodRemaining }}"
                                wire:model="eodCompletedCount"
                                placeholder="Enter completed creatives"
                             required>
                            @error('eodCompletedCount')
                                <div class="muted" style="color:#b4232f;margin-top:5px">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="eod-field">
                            <label class="label">Task Updation ZIP *</label>
                            <input class="field" type="file" accept=".zip,application/zip" wire:model="taskUpdateAttachment">
                            @error('taskUpdateAttachment')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <button
                            class="btn btn-primary"
                            wire:click="submitEod"
                            wire:loading.attr="disabled"
                            wire:target="submitEod"
                            wire:loading.class="is-loading"
                        >
                            Submit Task Updation
                        </button>
                    </div>
                @else
                    <div class="empty-state" style="margin-bottom:14px">
                        All creatives for this task have been completed.
                    </div>
                @endif

                <div class="eod-history">
                    @forelse($eodRecords as $record)
                        <div class="eod-record">
                            <div class="eod-record-main">
                                <strong>Submitted by {{ $record->designer?->name ?? 'Designer' }}</strong>
                                <span>{{ $record->submitted_at->format('d M Y, h:i A') }}</span>
                                @if($record->attachment_url)
                                    <a class="update-file" target="_blank" href="{{ $record->attachment_url }}">{{ $record->attachment_original_name ?? 'Download ZIP' }}</a>
                                @endif
                                @if(($record->update_type ?? 'progress') === 'rework')
                                    <span class="badge badge-danger" style="margin-top:5px">Rework #{{ $record->rework_count_snapshot }}</span>
                                @endif
                            </div>

                            <div class="eod-record-cell">
                                <span>{{ ($record->update_type ?? 'progress') === 'rework' ? 'Update Type' : 'Number of Creatives' }}</span>
                                <strong>{{ ($record->update_type ?? 'progress') === 'rework' ? 'Rework Upload' : $record->completed_count }}</strong>
                            </div>

                            <div class="eod-record-cell">
                                <span>Total Creatives</span>
                                <strong>{{ $record->total_creatives_snapshot }}</strong>
                            </div>

                            <div class="eod-record-cell">
                                <span>Total Completed</span>
                                <strong>{{ $record->cumulative_completed }}</strong>
                            </div>

                            <div class="eod-record-cell">
                                <span>Remaining</span>
                                <strong class="{{ $record->remaining_creatives === 0 ? 'eod-zero' : '' }}">{{ $record->remaining_creatives }}</strong>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No Task Updation records have been submitted yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>


    @if($splitRequests->isNotEmpty())
        <section x-show="tab === 'split-details'" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Split Details · {{ $splitRequests->count() }} Attempt{{ $splitRequests->count() === 1 ? '' : 's' }}</div></div>
                <div class="panel-body">
                    @if($splitOriginTask)
                        <div class="activity-item" style="margin-bottom:12px">
                            <strong>This task was created from a split</strong>
                            <p>Original task: {{ $splitOriginTask->task_id }} · {{ $splitOriginTask->display_task_name ?? $splitOriginTask->task_name }}</p>
                        </div>
                    @endif
                    @foreach($splitRequests as $splitRequest)
                        @php
                            $splitChild = $splitChildren->get($splitRequest->split_details['created_task_id'] ?? null);
                            $splitDecider = $splitRequest->adminActor ?: $splitRequest->designerHeadActor;
                            $splitPending = in_array($splitRequest->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                            $splitBadge = $splitRequest->overall_status === 'approved' ? 'badge-success' : ($splitRequest->overall_status === 'rejected' ? 'badge-danger' : 'badge-warning');
                            $requestedSplit = $splitRequest->split_details['requested_creative_count'] ?? $splitRequest->split_details['creative_count'] ?? '—';
                            $approvedSplit = $splitRequest->split_details['approved_creative_count'] ?? '—';
                        @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Split Request</strong>
                                <span class="badge {{ $splitBadge }}">{{ $splitPending ? 'Pending' : ($splitRequest->overall_status === 'rejected' ? 'Declined' : 'Approved') }}</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Requested By</span><strong>{{ $splitRequest->requester?->name ?? 'Designer' }}</strong></div>
                                <div class="special-detail-card"><span>Requested Split</span><strong>{{ $requestedSplit }} creatives</strong></div>
                                <div class="special-detail-card"><span>Approved Split</span><strong>{{ $approvedSplit === '—' ? '—' : $approvedSplit.' creatives' }}</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $splitRequest->targetDesigner?->name ?? 'No preference' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $splitRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Created Split Task</span><strong>{{ $splitChild?->task_id ?? ($splitRequest->split_details['created_task_code'] ?? '—') }}</strong></div>
                                <div class="special-detail-card"><span>Decision By</span><strong>{{ $splitDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Decision At</span><strong>{{ $splitRequest->admin_action_at?->format('d M Y, h:i A') ?? $splitRequest->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Request Reason</strong><p style="white-space:pre-wrap">{{ $splitRequest->reason }}</p></div>
                            @if($splitRequest->overall_status === 'rejected')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#fff5f5;color:#991b1b"><strong>Decline Reason</strong><p style="margin:4px 0 0;white-space:pre-wrap">{{ $splitRequest->decision_reason }}</p></div>
                            @endif
                            @if(!empty($splitRequest->split_details['details']))<div style="margin-top:8px"><strong>Split Notes</strong><p style="white-space:pre-wrap">{{ $splitRequest->split_details['details'] }}</p></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($swapRequests->isNotEmpty())
        <section x-show="tab === 'swap-details'" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Swap Details · {{ $swapRequests->count() }} Attempt{{ $swapRequests->count() === 1 ? '' : 's' }}</div></div>
                <div class="panel-body">
                    @foreach($swapRequests as $swapRequest)
                        @php
                            $swapDecider = $swapRequest->adminActor ?: $swapRequest->designerHeadActor;
                            $swapPending = in_array($swapRequest->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                            $swapBadge = $swapRequest->overall_status === 'approved' ? 'badge-success' : ($swapRequest->overall_status === 'rejected' ? 'badge-danger' : 'badge-warning');
                        @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Swap Request</strong>
                                <span class="badge {{ $swapBadge }}">{{ $swapPending ? 'Pending' : ($swapRequest->overall_status === 'rejected' ? 'Declined' : 'Approved') }}</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Requested By</span><strong>{{ $swapRequest->requester?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $swapRequest->targetDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $swapRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Decision By</span><strong>{{ $swapDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Decision At</span><strong>{{ $swapRequest->admin_action_at?->format('d M Y, h:i A') ?? $swapRequest->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Current Task Designer</span><strong>{{ $task->designer?->name ?? '—' }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Request Reason</strong><p style="white-space:pre-wrap">{{ $swapRequest->reason }}</p></div>
                            @if($swapRequest->overall_status === 'rejected')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#fff5f5;color:#991b1b"><strong>Decline Reason</strong><p style="margin:4px 0 0;white-space:pre-wrap">{{ $swapRequest->decision_reason }}</p></div>
                            @endif
                            @if(!empty($swapRequest->split_details['notes']))<div style="margin-top:8px"><strong>Notes</strong><p style="white-space:pre-wrap">{{ $swapRequest->split_details['notes'] }}</p></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section x-show="tab === 'history'" style="display:none" x-data="{ historyView: 'pipeline' }">
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
                                · {{ $event['created_at']->format('d M Y, h:i A') }}
                            </div>
                        </div>
                    @empty
                        <div class="history-nothing">No pipeline activity has been recorded yet.</div>
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


    <div
        class="attachment-preview-backdrop"
        x-show="attachmentPreviewOpen"
        x-transition.opacity
        x-cloak
        @click.self="closeAttachment()"
        @keydown.escape.window="if (attachmentPreviewOpen) closeAttachment()"
        style="display:none"
    >
        <div class="attachment-preview-modal">
            <div class="attachment-preview-head">
                <div class="attachment-preview-title" x-text="attachmentPreviewName"></div>
                <div class="attachment-preview-actions">
                    <a
                        class="attachment-preview-download"
                        :href="attachmentDownloadUrl"
                    >
                        Download
                    </a>
                    <button type="button" class="attachment-preview-close" @click="closeAttachment()">Close</button>
                </div>
            </div>

            <div class="attachment-preview-body">
                <iframe
                    class="attachment-preview-frame"
                    :src="attachmentPreviewOpen ? attachmentPreviewUrl : 'about:blank'"
                    :title="attachmentPreviewName"
                ></iframe>
            </div>
        </div>
    </div>

    <div class="toast" x-show="toast" x-transition x-text="toast" style="display:none"></div>

    @unless($swapInitiatorReadOnly)
        <livewire:designer.task-request-modal :task="$task" :key="'task-request-modal-'.$task->id" />
    @endunless
</div>
