<div
    x-data="{ tab: 'overview', toast: '' }"
    x-on:task-status-changed.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:comment-added.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:request-created.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600); tab = 'requests'"
>
    <style>
        .task-operation-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent;vertical-align:middle;white-space:nowrap}.task-operation-pill-split{color:#6938ef;background:linear-gradient(135deg,#f4f0ff,#ede9fe);border-color:#d9d6fe}.task-operation-pill-swap{color:#175cd3;background:linear-gradient(135deg,#eff8ff,#e6f1ff);border-color:#b2ddff}
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
        .muted{color:#7c8492;font-size:10px}
        .attachment-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
        .attachment-download{display:inline-flex;align-items:center;justify-content:center;padding:6px 9px;border:1px solid #dfe3ea;border-radius:8px;background:#fff;color:#344054;font-size:9px;font-weight:850;text-decoration:none;white-space:nowrap}
        .attachment-download:hover{background:#f7f8fa;border-color:#cfd4dc;color:#111827}
        .toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;font-size:11px;box-shadow:0 15px 40px rgba(0,0,0,.2)}
        @media(max-width:900px){.comment-actions{align-items:flex-start;flex-direction:column}.special-detail-grid{grid-template-columns:1fr}}
    </style>

    <div class="page-head">
        <div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h1 style="margin:0">{{ $task->display_task_name }}</h1>
                @foreach($task->operation_pills as $pill)
                    <span class="{{ $pill['class'] }}">{{ $pill['label'] }}</span>
                @endforeach
            </div>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? $task->status }}</p>
        </div>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('designer.tasks.index') }}">Back to My Tasks</a>

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
                >
                    Move to {{ $statuses[$nextStatus] ?? ucwords(str_replace('_', ' ', $nextStatus)) }}
                </button>
            @endif
        </div>
    </div>

    <div class="detail-tabs">
        <button class="detail-tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
        <button class="detail-tab" :class="{ active: tab === 'attachments' }" @click="tab = 'attachments'">
            Attachments
            <span class="tab-count">{{ $attachmentCount }}</span>
        </button>
        <button class="detail-tab" :class="{ active: tab === 'comments' }" @click="tab = 'comments'">Comments</button>
        <button class="detail-tab" :class="{ active: tab === 'requests' }" @click="tab = 'requests'">
            Requests
            <span class="tab-count">{{ $requests->count() }}</span>
        </button>
        @if($splitRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'split-details' }" @click="tab = 'split-details'">Split Details</button>
        @endif
        @if($swapRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'swap-details' }" @click="tab = 'swap-details'">Swap Details</button>
        @endif
        <button class="detail-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'">Pipeline History</button>
    </div>

    <section x-show="tab === 'overview'">
        <div class="detail-grid">
            <div>
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Task Information</div>
                    </div>

                    <div class="panel-body">
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
                </div>

                <div class="panel" style="margin-top:14px">
                    <div class="panel-header">
                        <div class="panel-title">Requirement Details</div>
                    </div>

                    <div class="panel-body">
                        <div class="requirement-list">
                            @forelse(($task->requirements ?? []) as $key => $value)
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
                                                            <a class="file-link" href="{{ Storage::disk('spaces')->url($item) }}">{{ basename($item) }}</a>
                                                            <a class="attachment-download" href="{{ Storage::disk('spaces')->url($item) }}" download="{{ basename($item) }}">Download</a>
                                                        </div>
                                                    @else
                                                        <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        @elseif(is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                            <span class="attachment-actions">
                                                <a class="file-link" href="{{ Storage::disk('spaces')->url($value) }}">{{ basename($value) }}</a>
                                                <a class="attachment-download" href="{{ Storage::disk('spaces')->url($value) }}" download="{{ basename($value) }}">Download</a>
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
                </div>
            </div>

            <aside>
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Current Stage</div>
                    </div>

                    <div class="panel-body">
                        <span class="badge badge-red">{{ $statuses[$task->status] ?? $task->status }}</span>

                        <div class="activity-item" style="margin-top:12px">
                            <strong>Last Updated</strong>
                            <p>{{ $task->updated_at->diffForHumans() }}</p>
                        </div>

                        <div class="activity-item" style="margin-top:8px">
                            <strong>Attachments</strong>
                            <p>{{ $attachmentCount }} file{{ $attachmentCount === 1 ? '' : 's' }} available across requirements and comments.</p>
                        </div>

                        @if($nextStatus)
                            <button
                                class="btn btn-primary"
                                style="width:100%;margin-top:10px"
                                wire:click="moveToNextStatus"
                                wire:loading.attr="disabled"
                            >
                                Move to Next Stage
                            </button>
                        @endif

                        <button class="btn btn-secondary" style="width:100%;margin-top:8px" @click="tab = 'attachments'">View Attachments</button>
                        <button class="btn btn-secondary" style="width:100%;margin-top:8px" @click="tab = 'comments'">Add Comment</button>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section x-show="tab === 'attachments'" style="display:none">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Attachment Library</div>
                    <div class="muted" style="margin-top:3px">All files connected to this task, organized by where they came from.</div>
                </div>
                <span class="badge badge-red">{{ $attachmentCount }} Files</span>
            </div>

            <div class="panel-body">
                <div class="attachment-summary">
                    <div class="attachment-stat">
                        <span>Total Files</span>
                        <strong>{{ $attachmentCount }}</strong>
                    </div>
                    <div class="attachment-stat">
                        <span>Requirement Files</span>
                        <strong>{{ $requirementAttachmentCount }}</strong>
                    </div>
                    <div class="attachment-stat">
                        <span>Comment Files</span>
                        <strong>{{ $commentAttachmentCount }}</strong>
                    </div>
                </div>

                <div class="attachment-section">
                    <div class="attachment-section-head">
                        <div>
                            <h3>Requirement Attachments</h3>
                            <p>Files originally provided with the BD task requirement.</p>
                        </div>
                        <span class="badge badge-dark">{{ $requirementAttachmentCount }}</span>
                    </div>

                    @forelse($requirementAttachmentGroups as $group)
                        <div class="attachment-group">
                            <div class="attachment-group-title">
                                <span>{{ $group['label'] }}</span>
                                <span class="attachment-group-meta">{{ count($group['files']) }} file{{ count($group['files']) === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="attachment-files">
                                @foreach($group['files'] as $file)
                                    <div class="attachment-card">
                                        <div class="attachment-type">{{ $file['extension'] }}</div>
                                        <div class="attachment-copy">
                                            <span class="attachment-name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                                            <span class="attachment-meta">BD requirement · {{ $group['label'] }}</span>
                                        </div>
                                        <div class="attachment-actions">
                                            <a class="attachment-open" href="{{ $file['url'] }}">Open</a>
                                            <a class="attachment-download" href="{{ $file['url'] }}" download="{{ $file['name'] }}">Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding:28px 20px">No requirement attachments were uploaded for this task.</div>
                    @endforelse
                </div>

                <div class="attachment-section" style="margin-top:22px">
                    <div class="attachment-section-head">
                        <div>
                            <h3>Comment Attachments</h3>
                            <p>Files added during Designer comments and task discussions.</p>
                        </div>
                        <span class="badge badge-dark">{{ $commentAttachmentCount }}</span>
                    </div>

                    @php $commentsWithAttachments = $comments->filter(fn($comment) => $comment->attachments->isNotEmpty()); @endphp

                    @forelse($commentsWithAttachments as $item)
                        <div class="attachment-group">
                            <div class="attachment-group-title">
                                <div>
                                    <span>{{ $item->user?->name ?? 'User' }}</span>
                                    <div class="attachment-group-meta" style="margin-top:3px">
                                        {{ $statuses[$item->status_at_comment] ?? $item->status_at_comment }} · {{ $item->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </div>
                                <span class="attachment-group-meta">{{ $item->attachments->count() }} file{{ $item->attachments->count() === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="attachment-files">
                                @foreach($item->attachments as $attachment)
                                    @php
                                        $extension = strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION) ?: 'FILE');
                                        $size = $attachment->size >= 1048576
                                            ? number_format($attachment->size / 1048576, 1).' MB'
                                            : number_format(max($attachment->size, 1) / 1024, 1).' KB';
                                    @endphp

                                    <div class="attachment-card">
                                        <div class="attachment-type">{{ $extension }}</div>
                                        <div class="attachment-copy">
                                            <span class="attachment-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span>
                                            <span class="attachment-meta">{{ $size }} · {{ $attachment->mime_type ?: 'Attachment' }}</span>
                                        </div>
                                        <div class="attachment-actions">
                                            <a class="attachment-open" href="{{ $attachment->url }}">Open</a>
                                            <a class="attachment-download" href="{{ $attachment->url }}" download="{{ $attachment->original_name }}">Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="empty-state" style="padding:28px 20px">No files have been added through comments yet.</div>
                    @endforelse
                </div>
            </div>
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

                        <button class="btn btn-primary" wire:click="addComment" wire:loading.attr="disabled">Add Comment</button>
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
                                            href="{{ $attachment->url }}"
                                            style="display:inline-flex;align-items:center;gap:5px;max-width:280px;padding:5px 8px;border:1px solid #e5e7eb;border-radius:8px;background:#fff7f7;font-size:10px;line-height:1.2;font-weight:750;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                            title="{{ $attachment->original_name }}"
                                        >
                                            <span style="font-size:10px">📎</span>
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $attachment->original_name }}</span>
                                        </a>
                                        <a
                                            class="attachment-download"
                                            href="{{ $attachment->url }}"
                                            download="{{ $attachment->original_name }}"
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

    <section x-show="tab === 'requests'" style="display:none">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Requests · {{ $requests->count() }}</div>
            </div>

            <div class="panel-body">
                <div class="activity-list">
                    @forelse($requests as $item)
                        @php
                            $statusBadge = match($item->overall_status) {
                                'approved' => 'badge-success',
                                'rejected' => 'badge-danger',
                                default => 'badge-warning',
                            };
                            $decider = $item->adminActor ?: $item->designerHeadActor;
                        @endphp
                        <div class="activity-item">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>{{ ucfirst($item->request_type) }} Request</strong>
                                <span class="badge {{ $statusBadge }}">{{ $item->status_label }}</span>
                            </div>
                            <p>
                                By {{ $item->requester?->name ?? 'Designer' }} ·
                                {{ $item->created_at->format('d M Y, h:i A') }}
                            </p>
                            <p style="margin-top:6px;white-space:pre-wrap">{{ $item->reason }}</p>

                            @if($item->request_type === 'split' && !empty($item->split_details['creative_count']))
                                <p style="margin-top:6px"><strong>Split creatives:</strong> {{ $item->split_details['creative_count'] }}</p>
                                @if(!empty($item->split_details['details']))<p class="muted" style="margin-top:3px">{{ $item->split_details['details'] }}</p>@endif
                            @endif

                            @if($item->targetDesigner)
                                <p style="margin-top:6px"><strong>Preferred Designer:</strong> {{ $item->targetDesigner->name }}</p>
                            @endif

                            @if($item->approvedDesigner)
                                <p style="margin-top:6px"><strong>Approved Designer:</strong> {{ $item->approvedDesigner->name }}</p>
                            @endif

                            @if(!empty($item->attachments))
                                <div style="margin-top:7px;display:flex;gap:7px;flex-wrap:wrap">
                                    @foreach($item->attachments as $path)
                                        <span class="attachment-actions">
                                            <a class="file-link" href="{{ Storage::disk('spaces')->url($path) }}">{{ basename($path) }}</a>
                                            <a class="attachment-download" href="{{ Storage::disk('spaces')->url($path) }}" download="{{ basename($path) }}">Download</a>
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if(in_array($item->overall_status, ['approved','rejected'], true))
                                <p class="muted" style="margin-top:8px">
                                    {{ ucfirst($item->overall_status) }} by {{ $decider?->name ?? 'Approver' }}
                                    · {{ $item->admin_action_at?->format('d M Y, h:i A') ?? $item->designer_head_action_at?->format('d M Y, h:i A') ?? '' }}
                                </p>
                            @else
                                <p class="muted" style="margin-top:8px">Waiting for either Designer Head or Admin.</p>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">No requests have been raised for this task yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    @if($splitRequests->isNotEmpty())
        <section x-show="tab === 'split-details'" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Split Details</div></div>
                <div class="panel-body">
                    @if($splitOriginTask)
                        <div class="activity-item" style="margin-bottom:12px">
                            <strong>This is a split task</strong>
                            <p>Original task: {{ $splitOriginTask->task_id }} · {{ $splitOriginTask->task_name }}</p>
                        </div>
                    @endif
                    @foreach($splitRequests as $splitRequest)
                        @php
                            $splitChild = $splitChildren->get($splitRequest->split_details['created_task_id'] ?? null);
                            $splitDecider = $splitRequest->adminActor ?: $splitRequest->designerHeadActor;
                        @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Approved Split Request</strong>
                                <span class="badge badge-success">Approved</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Requested By</span><strong>{{ $splitRequest->requester?->name ?? 'Designer' }}</strong></div>
                                <div class="special-detail-card"><span>Requested Split</span><strong>{{ $splitRequest->split_details['creative_count'] ?? '—' }} creatives</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $splitRequest->targetDesigner?->name ?? 'No preference' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $splitRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Original Remaining</span><strong>{{ $splitRequest->split_details['original_remaining_creatives'] ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Created Split Task</span><strong>{{ $splitChild?->task_id ?? ($splitRequest->split_details['created_task_code'] ?? '—') }}</strong></div>
                                <div class="special-detail-card"><span>Approved By</span><strong>{{ $splitDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved At</span><strong>{{ $splitRequest->admin_action_at?->format('d M Y, h:i A') ?? $splitRequest->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Reason</strong><p style="white-space:pre-wrap">{{ $splitRequest->reason }}</p></div>
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
                <div class="panel-header"><div class="panel-title">Swap Details</div></div>
                <div class="panel-body">
                    @foreach($swapRequests as $swapRequest)
                        @php $swapDecider = $swapRequest->adminActor ?: $swapRequest->designerHeadActor; @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Approved Swap Request</strong>
                                <span class="badge badge-success">Approved</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Previous Designer</span><strong>{{ $swapRequest->requester?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $swapRequest->targetDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $swapRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved By</span><strong>{{ $swapDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved At</span><strong>{{ $swapRequest->admin_action_at?->format('d M Y, h:i A') ?? $swapRequest->designer_head_action_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Current Task Designer</span><strong>{{ $task->designer?->name ?? '—' }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Reason</strong><p style="white-space:pre-wrap">{{ $swapRequest->reason }}</p></div>
                            @if(!empty($swapRequest->split_details['notes']))<div style="margin-top:8px"><strong>Notes</strong><p style="white-space:pre-wrap">{{ $swapRequest->split_details['notes'] }}</p></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section x-show="tab === 'history'" style="display:none">
        <div class="panel">
            <div class="panel-body">
                <div class="history-header">
                    <div class="history-header-title">Pipeline History</div>
                    <div class="history-count">{{ $pipelineEvents->count() }} Events</div>
                </div>

                <div class="history-list">
                    @forelse($pipelineEvents as $event)
                        @php $historyRole = $event['role'] ?? 'default'; @endphp
                        <div class="history-item role-{{ $historyRole }}">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <div class="history-event-title">{{ $event['title'] }}</div>
                                <span class="role-pill role-{{ $historyRole === 'default' ? 'default' : $historyRole }}">
                                    {{ $historyRole === 'default' ? 'System' : ucwords(str_replace('_', ' ', $historyRole)) }}
                                </span>
                            </div>
                            <div class="history-meta">
                                <span class="history-description">{{ $event['description'] }}</span>
                                <span class="history-time"> · {{ $event['created_at']->format('d M Y, h:i A') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No pipeline activity has been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <div class="toast" x-show="toast" x-transition x-text="toast" style="display:none"></div>

    <livewire:designer.task-request-modal :task="$task" :key="'task-request-modal-'.$task->id" />
</div>