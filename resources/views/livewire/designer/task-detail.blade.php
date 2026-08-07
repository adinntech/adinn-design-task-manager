<div
    x-data="{ tab: 'overview', toast: '' }"
    x-on:task-status-changed.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:comment-added.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:request-created.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600); tab = 'requests'"
>
    <style>
        .detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;margin-bottom:14px;max-width:100%;overflow:auto}
        .detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center}
        .detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}
        .comment-box{border:1px solid #e7e9ef;border-radius:13px;padding:14px;background:#fff}
        .comment-textarea{width:100%;min-height:115px;border:1px solid #dfe2e8;border-radius:10px;padding:11px;font:inherit;font-size:11px;resize:vertical;outline:none}
        .comment-textarea:focus{border-color:#e30613;box-shadow:0 0 0 3px rgba(227,6,19,.08)}
        .comment-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
        .comment-item{padding:12px;border:1px solid #e7e9ef;border-radius:11px;background:#fff;margin-top:8px}
        .muted{color:#7c8492;font-size:10px}
        .toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;font-size:11px;box-shadow:0 15px 40px rgba(0,0,0,.2)}
        @media(max-width:900px){.comment-actions{align-items:flex-start;flex-direction:column}}
    </style>

    <div class="page-head">
        <div>
            <h1>{{ $task->task_name }}</h1>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? $task->status }}</p>
        </div>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('designer.tasks.index') }}">Back to My Tasks</a>

            @if(in_array('decline', $allowedRequestTypes, true))
                <button class="btn btn-danger" wire:click="$dispatch('open-request-modal', { type: 'decline' })">Decline</button>
            @endif

            @if(in_array('split', $allowedRequestTypes, true))
                <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'split' })">Request Split</button>
            @endif

            @if(in_array('swap', $allowedRequestTypes, true))
                <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'swap' })">Request Swap</button>
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
                                                        <div><a class="file-link" href="{{ Storage::disk('spaces')->url($item) }}" target="_blank" rel="noopener">{{ basename($item) }}</a></div>
                                                    @else
                                                        <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        @elseif(is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                            <a class="file-link" href="{{ Storage::disk('spaces')->url($value) }}" target="_blank" rel="noopener">{{ basename($value) }}</a>
                                        @elseif(is_string($value) && filter_var($value, FILTER_VALIDATE_URL))
                                            <a class="file-link" href="{{ $value }}" target="_blank" rel="noopener">{{ $value }}</a>
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
                                        <a class="attachment-open" href="{{ $file['url'] }}" target="_blank" rel="noopener">Open</a>
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
                                        <a class="attachment-open" href="{{ $attachment->url }}" target="_blank" rel="noopener">Open</a>
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
                        <article class="comment-item">
                            <div style="display:flex;justify-content:space-between;gap:10px">
                                <strong style="font-size:11px">{{ $item->user?->name ?? 'User' }}</strong>
                                <span class="badge badge-dark">{{ $statuses[$item->status_at_comment] ?? $item->status_at_comment }}</span>
                            </div>

                            <p style="font-size:11px;white-space:pre-wrap">{{ $item->comment }}</p>

                            @if($item->attachments->isNotEmpty())
                                <div style="display:grid;gap:5px">
                                    @foreach($item->attachments as $attachment)
                                        <a class="file-link" href="{{ $attachment->url }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="muted" style="margin-top:8px">{{ $item->created_at->format('d M Y, h:i A') }}</div>
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
                        @endphp
                        <div class="activity-item">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>{{ ucfirst($item->request_type) }} Request</strong>
                                <span class="badge {{ $statusBadge }}">{{ ucwords(str_replace('_', ' ', $item->overall_status)) }}</span>
                            </div>
                            <p>
                                By {{ $item->requester?->name ?? 'Designer' }} ·
                                {{ $item->created_at->format('d M Y, h:i A') }}
                            </p>
                            <p style="margin-top:6px;white-space:pre-wrap">{{ $item->reason }}</p>
                        </div>
                    @empty
                        <div class="empty-state">No requests have been raised for this task yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section x-show="tab === 'history'" style="display:none">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">Pipeline History · {{ $history->count() }} Events</div>
            </div>

            <div class="panel-body">
                <div class="activity-list">
                    @forelse($history as $event)
                        <div class="activity-item">
                            <strong>
                                {{ $event->from_status ? ($statuses[$event->from_status] ?? $event->from_status).' → ' : '' }}
                                {{ $statuses[$event->to_status] ?? $event->to_status }}
                            </strong>
                            <p>
                                By {{ $event->changedBy?->name ?? 'User' }} ·
                                {{ $event->created_at->format('d M Y, h:i A') }} ·
                                {{ ucwords(str_replace('_', ' ', $event->change_source)) }}
                            </p>
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
