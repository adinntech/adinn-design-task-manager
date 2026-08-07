<div class="designer-task-detail"
     x-data="{ tab: 'overview', toast: '' }"
     x-on:task-status-changed.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
     x-on:comment-added.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)">

    <style>
        :root {
            --adinn-red: #e30613;
            --adinn-black: #111111;
            --adinn-bg: #f5f7fb;
            --adinn-border: #e2e7ef;
            --adinn-muted: #667085;
        }
        .detail-shell { background:#fff; border:1px solid var(--adinn-border); border-radius:18px; overflow:hidden; }
        .detail-header { padding:20px 22px; display:flex; justify-content:space-between; gap:18px; align-items:center; border-bottom:1px solid var(--adinn-border); }
        .detail-title { font-size:23px; font-weight:900; color:var(--adinn-black); }
        .detail-id { margin-top:4px; color:var(--adinn-muted); font-size:13px; }
        .status-pill { display:inline-flex; padding:7px 11px; border-radius:999px; background:#fff1f2; color:var(--adinn-red); font-size:12px; font-weight:800; }
        .primary-action { border:0; border-radius:11px; background:var(--adinn-red); color:#fff; padding:12px 18px; font-weight:800; cursor:pointer; }
        .primary-action:disabled { opacity:.45; cursor:not-allowed; }
        .detail-tabs { display:flex; gap:28px; padding:0 22px; border-bottom:1px solid var(--adinn-border); overflow:auto; }
        .detail-tab { padding:16px 2px 13px; border:0; background:transparent; font-weight:800; color:#7b8494; cursor:pointer; white-space:nowrap; border-bottom:3px solid transparent; }
        .detail-tab.active { color:var(--adinn-red); border-color:var(--adinn-red); }
        .detail-content { padding:22px; }
        .overview-layout { display:grid; grid-template-columns:minmax(0,1fr) 320px; gap:18px; }
        .detail-card { border:1px solid var(--adinn-border); border-radius:14px; background:#fff; padding:18px; margin-bottom:16px; }
        .detail-card-title { font-size:14px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; margin-bottom:15px; color:#475467; }
        .data-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .data-item label { display:block; color:#98a2b3; font-size:11px; font-weight:800; text-transform:uppercase; margin-bottom:4px; }
        .data-item div { color:#172033; font-weight:700; overflow-wrap:anywhere; }
        .requirement-list { display:grid; gap:10px; }
        .requirement-row { display:grid; grid-template-columns:210px 1fr; gap:12px; padding:11px 0; border-bottom:1px solid #eff1f5; }
        .requirement-key { font-weight:800; color:#667085; }
        .file-link { color:var(--adinn-red); font-weight:800; text-decoration:none; }
        .comment-box { border:1px solid var(--adinn-border); border-radius:14px; padding:16px; }
        .comment-textarea { width:100%; min-height:120px; resize:vertical; border:1px solid var(--adinn-border); border-radius:12px; padding:13px; outline:none; }
        .comment-textarea:focus { border-color:var(--adinn-red); box-shadow:0 0 0 3px rgba(227,6,19,.1); }
        .comment-actions { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; }
        .comment-item, .history-item { border:1px solid var(--adinn-border); border-radius:13px; padding:15px; margin-top:12px; }
        .history-line { border-left:3px solid var(--adinn-red); padding-left:14px; }
        .muted { color:var(--adinn-muted); font-size:12px; }
        .toast { position:fixed; right:22px; bottom:22px; z-index:9999; background:var(--adinn-black); color:#fff; border-left:4px solid var(--adinn-red); padding:13px 16px; border-radius:12px; }
        .error-box { margin-bottom:15px; padding:12px 14px; border-radius:11px; color:#b42318; background:#fee4e2; }
        @media(max-width:950px){ .overview-layout{grid-template-columns:1fr}.data-grid{grid-template-columns:1fr}.requirement-row{grid-template-columns:1fr}.detail-header{align-items:flex-start;flex-direction:column} }
    </style>

    @if($errors->any())
        <div class="error-box">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="detail-shell">
        <header class="detail-header">
            <div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <h1 class="detail-title">{{ $task->task_name }}</h1>
                    <span class="status-pill">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span>
                </div>
                <div class="detail-id">{{ $task->task_id }} · {{ ucwords(str_replace('_',' ',$task->vertical)) }}</div>
            </div>

            <div>
                @if($nextStatus)
                    <button class="primary-action" wire:click="moveToNextStatus" wire:loading.attr="disabled">
                        Move to {{ $statuses[$nextStatus] }}
                    </button>
                @else
                    <button class="primary-action" disabled>
                        Awaiting BD Action
                    </button>
                @endif
            </div>
        </header>

        <nav class="detail-tabs">
            <button class="detail-tab" :class="{ active: tab === 'overview' }" @click="tab='overview'">Overview</button>
            <button class="detail-tab" :class="{ active: tab === 'comments' }" @click="tab='comments'">Comments</button>
            <button class="detail-tab" :class="{ active: tab === 'history' }" @click="tab='history'">Pipeline History</button>
        </nav>

        <main class="detail-content">
            <section x-show="tab === 'overview'">
                <div class="overview-layout">
                    <div>
                        <div class="detail-card">
                            <div class="detail-card-title">Task Details</div>
                            <div class="data-grid">
                                <div class="data-item"><label>Client / Agency</label><div>{{ ucfirst($task->party_type) }} — {{ $task->party_name }}</div></div>
                                <div class="data-item"><label>Contact Person Name</label><div>{{ $task->contact_person }}</div></div>
                                <div class="data-item"><label>Mobile Number</label><div>{{ $task->mobile_number }}</div></div>
                                <div class="data-item"><label>Task Nature</label><div>{{ ucwords(str_replace('_',' ',$task->task_nature)) }}</div></div>
                                <div class="data-item"><label>Priority</label><div>{{ ucfirst($task->priority) }}</div></div>
                                <div class="data-item"><label>Due Date</label><div>{{ \Illuminate\Support\Carbon::parse($task->due_at)->format('d M Y, h:i A') }}</div></div>
                                <div class="data-item"><label>Assigned By</label><div>{{ $task->assigner?->name ?? 'BD' }}</div></div>
                                <div class="data-item"><label>Assigned At</label><div>{{ \Illuminate\Support\Carbon::parse($task->assigned_at)->format('d M Y, h:i A') }}</div></div>
                            </div>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">Requirement Details</div>
                            <div class="requirement-list">
                                @forelse(($task->requirements ?? []) as $key => $value)
                                    <div class="requirement-row">
                                        <div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div>
                                        <div>
                                            @if(is_array($value))
                                                @if(isset($value['square_feet']))
                                                    {{ $value['width'] }} × {{ $value['height'] }} feet = {{ $value['square_feet'] }} sq.ft
                                                @else
                                                    @foreach($value as $item)
                                                        @if(is_string($item) && str_contains($item, '/'))
                                                            <div><a class="file-link" href="{{ Storage::disk('spaces')->url($item) }}" target="_blank" rel="noopener">View {{ basename($item) }}</a></div>
                                                        @else
                                                            <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @elseif(is_string($value) && str_contains($value, '/'))
                                                <a class="file-link" href="{{ Storage::disk('spaces')->url($value) }}" target="_blank" rel="noopener">View {{ basename($value) }}</a>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="muted">No requirement data is available.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <aside>
                        <div class="detail-card">
                            <div class="detail-card-title">Current Stage</div>
                            <span class="status-pill">{{ $statuses[$task->status] ?? $task->status }}</span>
                            <div class="muted" style="margin-top:12px;">Updated {{ $task->updated_at->diffForHumans() }}</div>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">Quick Actions</div>
                            @if($nextStatus)
                                <button class="primary-action" style="width:100%;" wire:click="moveToNextStatus" wire:loading.attr="disabled">
                                    Move to Next Stage
                                </button>
                            @endif
                            <button style="width:100%;margin-top:10px;border:1px solid var(--adinn-border);border-radius:11px;background:#fff;padding:11px;font-weight:800;cursor:pointer;"
                                    @click="tab='comments'">
                                Add Comment
                            </button>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">Summary</div>
                            <div class="data-item"><label>Total Creatives</label><div>{{ $task->total_creatives }}</div></div>
                            <div class="data-item" style="margin-top:14px;"><label>Allocated to Me</label><div>{{ $task->total_creatives }}</div></div>
                            <div class="data-item" style="margin-top:14px;"><label>Last Updated</label><div>{{ $task->updated_at->diffForHumans() }}</div></div>
                        </div>
                    </aside>
                </div>
            </section>

            <section x-show="tab === 'comments'" style="display:none;">
                <div class="comment-box">
                    <div class="detail-card-title">Add a Comment · {{ $statuses[$task->status] ?? $task->status }}</div>
                    <textarea class="comment-textarea" wire:model="comment" placeholder="Type your comment here..."></textarea>

                    <div class="comment-actions">
                        <input type="file" wire:model="attachments" multiple>
                        <button class="primary-action" wire:click="addComment" wire:loading.attr="disabled">Add Comment</button>
                    </div>

                    <div class="muted" wire:loading wire:target="attachments">Preparing attachment...</div>
                </div>

                <div style="margin-top:20px;">
                    <div class="detail-card-title">Comments History</div>
                    @forelse($comments as $item)
                        <article class="comment-item">
                            <div style="display:flex;justify-content:space-between;gap:12px;">
                                <strong>{{ $item->user?->name ?? 'User' }}</strong>
                                <span class="status-pill">{{ $statuses[$item->status_at_comment] ?? $item->status_at_comment }}</span>
                            </div>
                            <p style="margin:12px 0 0;white-space:pre-wrap;">{{ $item->comment }}</p>

                            @if($item->attachments->isNotEmpty())
                                <div style="display:grid;gap:7px;margin-top:12px;">
                                    @foreach($item->attachments as $attachment)
                                        <a class="file-link" href="{{ $attachment->url }}" target="_blank" rel="noopener">
                                            {{ $attachment->original_name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="muted" style="margin-top:12px;">{{ $item->created_at->format('d M Y, h:i A') }}</div>
                        </article>
                    @empty
                        <div class="comment-item muted">No comments have been added yet.</div>
                    @endforelse
                </div>
            </section>

            <section x-show="tab === 'history'" style="display:none;">
                <div class="detail-card-title">Pipeline History · {{ $history->count() }} events</div>

                @forelse($history as $event)
                    <article class="history-item history-line">
                        <strong>
                            {{ $event->from_status ? ($statuses[$event->from_status] ?? $event->from_status).' → ' : '' }}
                            {{ $statuses[$event->to_status] ?? $event->to_status }}
                        </strong>
                        <div class="muted" style="margin-top:7px;">
                            By {{ $event->changedBy?->name ?? 'User' }}
                            · {{ $event->created_at->format('d M Y, h:i A') }}
                            · {{ ucwords(str_replace('_',' ',$event->change_source)) }}
                        </div>
                    </article>
                @empty
                    <div class="history-item muted">No pipeline activity has been recorded yet.</div>
                @endforelse
            </section>
        </main>
    </div>

    <div class="toast" x-show="toast" x-transition x-text="toast" style="display:none;"></div>
</div>
