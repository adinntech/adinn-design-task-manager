<div x-data="designerKanban()" x-init="init()" x-on:task-status-changed.window="showToast($event.detail.message)" x-on:task-move-blocked.window="showToast($event.detail.message)">
    <style>
        .designer-toolbar{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(auto-fit,minmax(140px,.6fr));gap:9px;margin-bottom:14px}.designer-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.metric-active-breakdown{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}.metric-active-chip{font-size:8px;font-weight:800;color:#475467;background:#f2f4f7;border-radius:999px;padding:2px 7px;white-space:nowrap}.continuation-badge{margin-top:7px;padding:5px 8px;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;font-size:8px;font-weight:800;line-height:1.4}.applied-filters{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-top:12px;padding-top:12px;border-top:1px solid #eef0f3}.applied-filters-label{font-size:9px;font-weight:900;color:#475467;text-transform:uppercase;letter-spacing:.04em}.applied-filter-chip{font-size:9px;font-weight:850;color:#101828;background:#f2f4f7;border:1px solid #e4e7ec;border-radius:999px;padding:5px 10px;white-space:nowrap}.applied-filters-clear{margin-left:auto;padding:6px 12px;font-size:10px;min-height:auto}@media(max-width:900px){.designer-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}.kanban-shell{overflow-x:auto;overflow-y:visible;padding-bottom:8px;scrollbar-width:none;-ms-overflow-style:none;cursor:grab;user-select:none}.kanban-shell::-webkit-scrollbar{display:none}.kanban-shell.is-panning{cursor:grabbing}
.kanban-shell{position:relative}
body[data-kanban-dragging="1"] .kanban-shell::before,
body[data-kanban-dragging="1"] .kanban-shell::after{content:'';position:sticky;z-index:50;top:0;width:34px;height:100%;pointer-events:none;opacity:.2}
.kanban-shell .task-card,.kanban-shell input,.kanban-shell select,.kanban-shell button,.kanban-shell a{user-select:auto}.kanban-board{display:grid;grid-template-columns:repeat(10,270px);gap:10px;min-width:max-content}.kanban-column{border:1px solid #e7e9ef;border-radius:14px;background:#f9fafb;overflow:hidden}.kanban-column-header{padding:12px 12px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e7e9ef;background:#fff;border-top:4px solid #98a2b3}.kanban-column-title{font-size:10px;font-weight:900;color:#344054;text-transform:uppercase;letter-spacing:.04em}.kanban-count{min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#eef0f4;color:#344054;display:grid;place-items:center;font-size:10px;font-weight:900}.kanban-list{padding:9px;min-height:420px}.kanban-empty{height:105px;border:1px dashed #cfd4dd;border-radius:10px;display:grid;place-items:center;color:#9aa1ad;font-size:10px}

.kanban-column.status-assigned_tasks .kanban-column-header{border-top-color:#667085;background:#f9fafb}
.kanban-column.status-review_analysis .kanban-column-header{border-top-color:#2563eb;background:#eff6ff}
.kanban-column.status-need_clarification .kanban-column-header{border-top-color:#d97706;background:#fffbeb}
.kanban-column.status-yet_to_start .kanban-column-header{border-top-color:#7c3aed;background:#f5f3ff}
.kanban-column.status-in_progress .kanban-column-header{border-top-color:#0891b2;background:#ecfeff}
.kanban-column.status-waiting_confirmation .kanban-column-header{border-top-color:#db2777;background:#fdf2f8}
.kanban-column.status-rework .kanban-column-header{border-top-color:#ea580c;background:#fff7ed}
.kanban-column.status-completed .kanban-column-header{border-top-color:#16a34a;background:#f0fdf4}
.kanban-column.status-swap_tasks .kanban-column-header{border-top-color:#0f766e;background:#f0fdfa}
.kanban-column.status-self_declined .kanban-column-header{border-top-color:#6b7280;background:#f9fafb}

.kanban-column.status-assigned_tasks .kanban-count{background:#eaecf0;color:#475467}
.kanban-column.status-review_analysis .kanban-count{background:#dbeafe;color:#1d4ed8}
.kanban-column.status-need_clarification .kanban-count{background:#fef3c7;color:#b45309}
.kanban-column.status-yet_to_start .kanban-count{background:#ede9fe;color:#6d28d9}
.kanban-column.status-in_progress .kanban-count{background:#cffafe;color:#0e7490}
.kanban-column.status-waiting_confirmation .kanban-count{background:#fce7f3;color:#be185d}
.kanban-column.status-rework .kanban-count{background:#ffedd5;color:#c2410c}
.kanban-column.status-completed .kanban-count{background:#dcfce7;color:#15803d}
.kanban-column.status-swap_tasks .kanban-count{background:#ccfbf1;color:#0f766e}
.kanban-column.status-self_declined .kanban-count{background:#e5e7eb;color:#4b5563}

.task-card{display:block;border:1px solid #e3e6ec;border-left:5px solid #cbd5e1;border-radius:11px;background:#fff;padding:11px;margin-bottom:8px;color:inherit;text-decoration:none;box-shadow:0 4px 12px rgba(16,24,40,.04);cursor:grab;transition:.16s}
.task-card:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(16,24,40,.08);border-color:#d7dbe3}
.task-card-highlight{animation:taskCardHighlightPulse .7s ease-in-out 3}
@keyframes taskCardHighlightPulse{0%,100%{box-shadow:0 4px 12px rgba(16,24,40,.04);border-color:#e3e6ec}50%{box-shadow:0 0 0 4px rgba(227,6,19,.35);border-color:#e30613}}
.task-card.due-overdue{border-left-color:#dc2626;background:linear-gradient(90deg,#fff1f2 0,#fff 22%)}
.task-card.due-today{border-left-color:#ea580c;background:linear-gradient(90deg,#fff7ed 0,#fff 22%)}
.task-card.due-soon{border-left-color:#d97706;background:linear-gradient(90deg,#fffbeb 0,#fff 22%)}
.task-card.due-safe{border-left-color:#16a34a;background:linear-gradient(90deg,#f0fdf4 0,#fff 22%)}
.task-card.due-completed{border-left-color:#94a3b8;background:#fff}
.due-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.035em}
.due-pill.due-overdue{background:#fee2e2;color:#b91c1c}
.due-pill.due-today{background:#ffedd5;color:#c2410c}
.due-pill.due-soon{background:#fef3c7;color:#b45309}
.due-pill.due-safe{background:#dcfce7;color:#15803d}
.due-pill.due-completed{background:#f1f5f9;color:#64748b}.task-card-id{color:#7c8492;font-size:9px;font-weight:850}.task-card-name{margin-top:6px;font-size:12px;font-weight:900;line-height:1.35}.task-card-client{margin-top:4px;color:#5f6877;font-size:10px}.kanban-progress{margin-top:9px;padding:8px;border:1px solid #e7e9ef;border-radius:9px;background:#fff}.kanban-progress-head{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:8px;color:#667085}.kanban-progress-head strong{font-size:8px;color:#344054}.kanban-progress-track{height:6px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:6px}.kanban-progress-fill{height:100%;background:#e30613;border-radius:999px}.task-card-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.task-history-tag{display:inline-flex;align-items:center;min-height:22px;padding:3px 9px;border-radius:999px;font-size:8px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent;box-shadow:0 2px 7px rgba(16,24,40,.05)}.task-tag-split{color:#6938ef;background:linear-gradient(135deg,#f4f0ff,#ede9fe);border-color:#d9d6fe}.task-tag-swap{color:#067647;background:linear-gradient(135deg,#ecfdf3,#dcfae6);border-color:#abefc6}.task-tag-decline{color:#b42318;background:linear-gradient(135deg,#fff1f0,#fee4e2);border-color:#fecdca}.task-tag-pending{color:#b54708;background:linear-gradient(135deg,#fffaeb,#fff4d6);border-color:#fedf89}.task-card-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:9px}.task-meta-item{border-radius:8px;background:#f7f8fa;padding:7px;font-size:9px;color:#616a78}.task-meta-item strong{display:block;color:#343b46;font-size:8px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}.sortable-ghost{opacity:.35}.sortable-chosen{box-shadow:0 12px 30px rgba(0,0,0,.14)}.kanban-invalid{animation:invalidDrop .35s ease}@keyframes invalidDrop{50%{background:#fee4e2}}.designer-toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;box-shadow:0 15px 40px rgba(0,0,0,.2);font-size:11px}@media(max-width:900px){.designer-toolbar{grid-template-columns:1fr}}
    
    .kanban-rating{
        margin-top:8px;
        padding:8px 9px;
        border:1px solid #f2e2a4;
        border-radius:9px;
        background:#fffaf0;
    }
    .kanban-rating-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        margin-bottom:4px;
    }
    .kanban-rating-label{
        font-size:8px;
        font-weight:900;
        color:#7c5c00;
        text-transform:uppercase;
        letter-spacing:.03em;
    }
    .kanban-rating-value{
        font-size:9px;
        font-weight:900;
        color:#6b4f00;
        white-space:nowrap;
    }
    .kanban-rating-stars{
        display:flex;
        align-items:center;
        gap:2px;
        line-height:1;
    }
    .kanban-rating-star{
        --star-fill:0%;
        display:inline-block;
        width:14px;
        height:14px;
        flex:0 0 14px;
        font-size:14px;
        line-height:14px;
        font-family:Arial,"Segoe UI Symbol",sans-serif;
        background:linear-gradient(
            90deg,
            #f5b301 0%,
            #f5b301 var(--star-fill),
            #d8dee8 var(--star-fill),
            #d8dee8 100%
        );
        -webkit-background-clip:text;
        background-clip:text;
        -webkit-text-fill-color:transparent;
        color:transparent;
    }


        /* Latest request status shown directly on each Kanban task card */
        .task-card-tags{
            display:flex;
            flex-wrap:wrap;
            gap:5px;
            margin-top:8px;
            min-width:0;
        }
        .task-request-status{
            display:inline-flex;
            align-items:center;
            max-width:100%;
            min-height:22px;
            padding:4px 8px;
            border-radius:999px;
            border:1px solid transparent;
            font-size:8px;
            font-weight:950;
            letter-spacing:.02em;
            line-height:1.2;
            white-space:normal;
            overflow-wrap:anywhere;
        }
        .task-request-pending{
            color:#9a6700;
            background:#fffaeb;
            border-color:#fedf89;
        }
        .task-request-approved{
            color:#067647;
            background:#ecfdf3;
            border-color:#abefc6;
        }
        .task-request-declined{
            color:#b42318;
            background:#fff1f0;
            border-color:#fecdca;
        }
        @media(max-width:520px){
            .task-request-status{
                font-size:7.5px;
                padding:4px 7px;
            }
        }


        /* Clean request indicator in the card header */
        .task-card-head-row{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;min-width:0}
        .task-card-badges{display:flex;flex-direction:column;align-items:flex-end;gap:6px;min-width:0;max-width:68%}
        .task-card-badges-main{display:flex;gap:5px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
        .task-request-top{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            max-width:100%;
            min-height:22px;
            padding:4px 8px;
            border-radius:999px;
            border:1px solid transparent;
            font-size:7.5px;
            line-height:1.2;
            font-weight:950;
            letter-spacing:.025em;
            text-transform:uppercase;
            text-align:center;
            overflow-wrap:anywhere;
        }
        .task-request-top.task-request-pending{color:#9a6700;background:#fffaeb;border-color:#fedf89}
        .task-request-top.task-request-approved{color:#067647;background:#ecfdf3;border-color:#abefc6}
        .task-request-top.task-request-declined{color:#b42318;background:#fff1f0;border-color:#fecdca}
        @media(max-width:520px){
            .task-card-head-row{gap:7px}
            .task-card-badges{max-width:72%}
            .task-request-top{font-size:7px;padding:4px 6px}
        }


        /* Request status aligned with Task Name */
        .task-name-request-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-top:10px;
            min-width:0;
        }
        .task-name-request-row .task-card-name{
            margin:0!important;
            flex:1;
            min-width:0;
        }
        .task-request-inline{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex:0 0 auto;
            max-width:52%;
            min-height:21px;
            padding:4px 8px;
            border-radius:999px;
            border:1px solid transparent;
            font-size:7.5px;
            line-height:1.1;
            font-weight:950;
            letter-spacing:.02em;
            text-transform:uppercase;
            text-align:center;
            white-space:nowrap;
        }
        .task-request-inline.task-request-pending{color:#9a6700;background:#fffaeb;border-color:#fedf89}
        .task-request-inline.task-request-approved{color:#067647;background:#ecfdf3;border-color:#abefc6}
        .task-request-inline.task-request-declined{color:#b42318;background:#fff1f0;border-color:#fecdca}
        @media(max-width:520px){
            .task-name-request-row{
                align-items:flex-start;
                gap:7px;
            }
            .task-request-inline{
                font-size:7px;
                padding:4px 6px;
                max-width:48%;
            }
        }

</style>

    <div class="page-head">
        <div><h1>My Tasks</h1><p>Manage assigned design tasks across the complete production pipeline.</p></div>
        <div class="page-actions"><span class="badge badge-dark">{{ $tasks->count() }} visible tasks</span></div>
    </div>

    <div class="designer-metrics">
        <div class="metric-card">
            <div class="metric-label">Total Tasks</div>
            <div class="metric-value">{{ $stats['total'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Active</div>
            <div class="metric-value">{{ $stats['active'] }}</div>
            <div class="metric-active-breakdown">
                @foreach($activeBreakdown as $row)
                    @if($row['count'] > 0)
                        <span class="metric-active-chip">{{ $row['label'] }}: {{ $row['count'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Waiting Confirmation</div>
            <div class="metric-value">{{ $stats['waiting'] }}</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">Completed</div>
            <div class="metric-value">{{ $stats['completed'] }}</div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:14px"><div class="panel-body">
        <div class="designer-toolbar">
            <input class="premium-input" type="search" placeholder="Search Task ID, task name, client, vertical..." wire:model.live.debounce.350ms="search">
            <select class="premium-select" wire:model.live="vertical"><option value="">All Verticals</option><option value="outdoor">Outdoor</option><option value="roadshow">RoadShow</option><option value="fixtures">Fixtures</option><option value="signage">Signage</option><option value="pop_offsets">POP and Offsets</option><option value="digital_marketing">Digital Marketing</option><option value="events_activations">Events and Activations</option></select>
            <select class="premium-select" wire:model.live="priority"><option value="">All Priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select>

            <select class="premium-select" wire:model.live="period">
                <option value="current_month">Current Month</option>
                <option value="last_month">Last Month</option>
                <option value="custom">Custom Period</option>
            </select>

            @if($period === 'custom')
                <input class="premium-input" type="date" wire:model.live="dateFrom" max="{{ $dateTo ?: '' }}">
                <input class="premium-input" type="date" wire:model.live="dateTo" min="{{ $dateFrom ?: '' }}">
            @endif

            <a class="btn btn-secondary" href="{{ route('designer.tasks.export', [
                'search' => $search, 'vertical' => $vertical, 'priority' => $priority,
                'period' => $period, 'date_from' => $dateFrom, 'date_to' => $dateTo,
            ]) }}">Export Report</a>
        </div>

        @if($appliedFilters->isNotEmpty())
            <div class="applied-filters">
                <span class="applied-filters-label">Applied Filters:</span>
                @foreach($appliedFilters as $chip)
                    <span class="applied-filter-chip">{{ $chip['label'] }}: {{ $chip['value'] }}</span>
                @endforeach
                <button type="button" class="btn btn-secondary applied-filters-clear" wire:click="clearFilters">Clear Filters</button>
            </div>
        @endif
    </div></div>

    <div class="kanban-shell" data-kanban-shell>
        <div class="kanban-board">
            @foreach($statuses as $statusKey => $statusLabel)
                @php
                    $columnTasks = $tasks->where('status', $statusKey);
                @endphp
                <section class="kanban-column status-{{ $statusKey }}" data-status="{{ $statusKey }}" wire:key="column-{{ $statusKey }}">
                    <header class="kanban-column-header"><span class="kanban-column-title">{{ $statusLabel }}</span><span class="kanban-count">{{ $columnTasks->count() }}</span></header>
                    <div class="kanban-list" data-kanban-list data-status="{{ $statusKey }}">
                        @forelse($columnTasks as $task)
                            @php
                                $dueAt = \Illuminate\Support\Carbon::parse($task->due_at);
                                $now = now();

                                if ($task->status === 'completed') {
                                    $dueClass = 'due-completed';
                                    $dueLabel = 'Completed';
                                } elseif ($dueAt->isPast()) {
                                    $dueClass = 'due-overdue';
                                    $dueLabel = 'Overdue';
                                } elseif ($dueAt->isToday()) {
                                    $dueClass = 'due-today';
                                    $dueLabel = 'Due Today';
                                } elseif ($now->diffInHours($dueAt, false) <= 48) {
                                    $dueClass = 'due-soon';
                                    $dueLabel = 'Due Soon';
                                } else {
                                    $dueClass = 'due-safe';
                                    $dueLabel = 'On Track';
                                }
                            @endphp
                            <a href="{{ route('designer.tasks.show', $task) }}" class="task-card {{ $dueClass }}" data-task-id="{{ $task->id }}" data-task-code="{{ $task->task_id }}" data-task-status="{{ $task->status }}" wire:key="task-{{ $task->id }}">
                                <div class="task-card-head-row">
                                        <span class="task-card-id">{{ $task->task_id }}</span>

                                        <div class="task-card-badges">
                                            <div class="task-card-badges-main">
                                                <span class="due-pill {{ $dueClass }}">{{ $dueLabel }}</span>
                                                <span class="badge priority-{{ $task->priority }}">{{ $task->priority }}</span>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="task-name-request-row">
                                        <div class="task-card-name">{{ $task->display_task_name ?? $task->task_name }}</div>

                                        @if(!empty($taskTags[$task->id][0]))
                                            @php
                                                $inlineRequest = $taskTags[$task->id][0];
                                            @endphp
                                            <span class="task-request-inline {{ $inlineRequest['class'] }}">
                                                {{ $inlineRequest['label'] }}
                                            </span>
                                        @endif
                                    </div>
                                <div class="task-card-client">{{ ucfirst($task->party_type) }} · {{ $task->party_name }}</div>
                                    @if($statusKey === 'self_declined')
                                        <div class="task-card-client">Now with: {{ $task->designer?->name ?? '—' }}</div>
                                    @endif
                                    @if($task->continuation_label ?? null)
                                        <div class="continuation-badge">{{ $task->continuation_label }}@if($task->continuation_event_label ?? null) · {{ $task->continuation_event_label }}@endif</div>
                                    @endif
                                    @include('partials.kanban-task-progress', ['task' => $task])

                                

                            @if($task->status === 'completed' && $task->bdReview && $task->bdReview->overall_rating !== null)
                                @php
                                    $kanbanRating = max(0, min(5, \App\Models\DesignTaskBdReview::roundToHalfStar($task->bdReview->overall_rating)));
                                @endphp
                                <div class="kanban-rating">
                                    <div class="kanban-rating-head">
                                        <span class="kanban-rating-label">Overall Rating</span>
                                        <span class="kanban-rating-value">{{ \App\Models\DesignTaskBdReview::formatRating($kanbanRating) }} / 5</span>
                                    </div>
                                    <div class="kanban-rating-stars" aria-label="{{ number_format($kanbanRating, 1) }} out of 5 stars">
                                        @for($ratingStarIndex = 1; $ratingStarIndex <= 5; $ratingStarIndex++)
                                            @php
                                                $ratingStarFill = $kanbanRating >= $ratingStarIndex
                                                    ? 100
                                                    : ($kanbanRating >= ($ratingStarIndex - 0.5) ? 50 : 0);
                                            @endphp
                                            <span
                                                class="kanban-rating-star"
                                                style="--star-fill:{{ $ratingStarFill }}%;"
                                                aria-hidden="true"
                                            >★</span>
                                        @endfor
                                    </div>
                                </div>
                            @endif


                                <div class="task-card-meta">
                                    <div class="task-meta-item"><strong>Vertical</strong>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</div>
                                    <div class="task-meta-item"><strong>Creatives</strong>{{ $task->total_creatives }}</div>
                                    <div class="task-meta-item"><strong>Due</strong>{{ \Illuminate\Support\Carbon::parse($task->due_at)->format('d M, h:i A') }}</div>
                                    <div class="task-meta-item"><strong>Assigned by</strong>{{ $task->assigner?->name ?? 'BD' }}</div>
                                    <div class="task-meta-item"><strong>Created</strong>{{ $task->created_at->format('d M Y') }}</div>
                                </div>
                            </a>
                        @empty
                            <div class="kanban-empty">No matching tasks</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="designer-toast" x-show="toast" x-transition x-text="toast" style="display:none"></div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            function designerKanban(){
                return {
                    sortables: [],
                    toast: '',
                    toastTimer: null,
                    panCleanup: null,
                    pointerEdgeCleanup: null,
                    edgeScrollFrame: null,
                    edgeScrollPointerX: null,
                    boardPointerMoveHandler: null,

                    init(){
                        this.$nextTick(() => {
                            this.refreshSortable();
                            this.enableBoardPan();
                            this.enablePointerEdgeScroll();
                            this.focusRequestedColumn();
                        });

                        document.addEventListener('livewire:init', () => {
                            Livewire.hook('morph.updated', () => {
                                this.$nextTick(() => {
                                    this.refreshSortable();
                                    this.enableBoardPan();
                                    this.enablePointerEdgeScroll();
                                });
                            });
                        });
                    },

                    // Dashboard cards link here with ?focus=<status>[&task=<task_id>];
                    // scroll the board horizontally to the right column once, on initial
                    // page load only (never on Livewire re-renders, so it can't fight the
                    // user's scroll or re-fire the highlight pulse on every drag/update).
                    // When a task id is present, its own column (found via the DOM) takes
                    // priority over ?focus, so a task shown under a synthetic column
                    // (e.g. Self Declined) still scrolls to where it actually renders. The
                    // lookup retries briefly (bounded, not a blind delay) in case the
                    // card isn't in the DOM on the very first tick, then gives up safely
                    // if the task truly isn't on this board.
                    focusRequestedColumn(){
                        const params = new URLSearchParams(window.location.search);
                        const focus = params.get('focus');
                        const taskCode = params.get('task');
                        if (!focus && !taskCode) return;

                        const attempt = (tries) => {
                            const shell = this.$root.querySelector('[data-kanban-shell]');
                            const target = taskCode ? this.$root.querySelector('[data-task-code="' + CSS.escape(taskCode) + '"]') : null;

                            if (taskCode && !target && tries < 20) {
                                setTimeout(() => attempt(tries + 1), 100);
                                return;
                            }

                            const column = target ? target.closest('.kanban-column')
                                : (focus ? this.$root.querySelector('.kanban-column[data-status="' + focus + '"]') : null);

                            if (shell && column) {
                                shell.scrollTo({ left: Math.max(0, column.offsetLeft - 12), behavior: 'smooth' });
                            }

                            if (target) {
                                target.classList.add('task-card-highlight');
                                target.addEventListener('animationend', function onPulseEnd(event) {
                                    if (event.animationName !== 'taskCardHighlightPulse') return;
                                    target.classList.remove('task-card-highlight');
                                    target.removeEventListener('animationend', onPulseEnd);
                                });
                            }
                        };

                        attempt(0);
                    },

                    refreshSortable(){
                        this.sortables.forEach(item => item.destroy());
                        this.sortables = [];

                        document.querySelectorAll('[data-kanban-list]').forEach(list => {
                            const isSelfDeclined = list.dataset.status === 'self_declined';

                            this.sortables.push(new Sortable(list, {
                                group: isSelfDeclined ? { name: 'designer-kanban', pull: false, put: false } : 'designer-kanban',
                                sort: !isSelfDeclined,
                                animation: 180,
                                ghostClass: 'sortable-ghost',
                                chosenClass: 'sortable-chosen',
                                fallbackOnBody: true,
                                scroll: true,
                                scrollSensitivity: 110,
                                scrollSpeed: 18,
                                bubbleScroll: true,

                                onStart: event => {
                                    document.body.dataset.kanbanDragging = '1';
                                    this.edgeScrollPointerX = event.originalEvent?.clientX ?? this.edgeScrollPointerX;
                                    this.startEdgeScroll();
                                },

                                onMove: event => {
                                    this.edgeScrollPointerX = event.originalEvent?.clientX ?? null;
                                    this.startEdgeScroll();
                                    return true;
                                },

                                onEnd: event => {
                                    delete document.body.dataset.kanbanDragging;
                                    this.stopEdgeScroll();

                                    const card = event.item;
                                    const taskId = Number(card.dataset.taskId);
                                    const fromStatus = card.dataset.taskStatus;
                                    const targetStatus = event.to.dataset.status;

                                    if (!taskId || fromStatus === targetStatus) {
                                        return;
                                    }

                                    event.to.removeChild(card);
                                    event.from.insertBefore(
                                        card,
                                        event.from.children[event.oldIndex] ?? null
                                    );

                                    this.$wire.moveTask(taskId, targetStatus).catch(() => {
                                        event.to.classList.add('kanban-invalid');
                                        setTimeout(() => event.to.classList.remove('kanban-invalid'), 400);
                                    });
                                }
                            }));
                        });
                    },


                    enablePointerEdgeScroll(){
                        const shell = this.$root.querySelector('[data-kanban-shell], [data-bd-kanban-shell]');
                        if (!shell) return;

                        if (this.pointerEdgeCleanup) {
                            this.pointerEdgeCleanup();
                            this.pointerEdgeCleanup = null;
                        }

                        let pointerX = null;
                        let pointerInside = false;
                        let frame = null;

                        const edgeZone = 110;
                        const maxSpeed = 22;

                        const tick = () => {
                            if (!pointerInside || pointerX === null) {
                                frame = null;
                                return;
                            }

                            const rect = shell.getBoundingClientRect();
                            let speed = 0;

                            if (pointerX <= rect.left + edgeZone) {
                                const strength = Math.max(0, Math.min(1, (rect.left + edgeZone - pointerX) / edgeZone));
                                speed = -Math.max(5, maxSpeed * strength);
                            } else if (pointerX >= rect.right - edgeZone) {
                                const strength = Math.max(0, Math.min(1, (pointerX - (rect.right - edgeZone)) / edgeZone));
                                speed = Math.max(5, maxSpeed * strength);
                            }

                            if (speed !== 0) {
                                shell.scrollLeft += speed;
                                frame = requestAnimationFrame(tick);
                            } else {
                                frame = null;
                            }
                        };

                        const ensureTick = () => {
                            if (frame === null) {
                                frame = requestAnimationFrame(tick);
                            }
                        };

                        const onMove = event => {
                            pointerInside = true;
                            pointerX = event.clientX;
                            ensureTick();
                        };

                        const onEnter = event => {
                            pointerInside = true;
                            pointerX = event.clientX;
                            ensureTick();
                        };

                        const onLeave = () => {
                            pointerInside = false;
                            pointerX = null;

                            if (frame !== null) {
                                cancelAnimationFrame(frame);
                                frame = null;
                            }
                        };

                        shell.addEventListener('pointermove', onMove);
                        shell.addEventListener('pointerenter', onEnter);
                        shell.addEventListener('pointerleave', onLeave);

                        this.pointerEdgeCleanup = () => {
                            shell.removeEventListener('pointermove', onMove);
                            shell.removeEventListener('pointerenter', onEnter);
                            shell.removeEventListener('pointerleave', onLeave);

                            if (frame !== null) {
                                cancelAnimationFrame(frame);
                                frame = null;
                            }
                        };
                    },

                    enableBoardPan(){
                        if (this.panCleanup) {
                            this.panCleanup();
                            this.panCleanup = null;
                        }

                        const shell = this.$root.querySelector('[data-kanban-shell]');
                        if (!shell) return;

                        // Track pointer position over the Kanban continuously.
                        // During a task drag, reaching the left/right edge will
                        // automatically pan the board without requiring manual drag.
                        const trackPointer = event => {
                            this.edgeScrollPointerX = event.clientX;

                            if (document.body.dataset.kanbanDragging === '1') {
                                this.startEdgeScroll();
                            }
                        };

                        shell.addEventListener('pointermove', trackPointer);
                        this.boardPointerMoveHandler = trackPointer;

                        let isDown = false;
                        let startX = 0;
                        let startScrollLeft = 0;
                        let moved = false;

                        const shouldIgnore = target => {
                            return !!target.closest(
                                '.task-card, input, select, textarea, button, a, [data-kanban-list] > *'
                            );
                        };

                        const onPointerDown = event => {
                            if (event.button !== 0) return;
                            if (document.body.dataset.kanbanDragging === '1') return;
                            if (shouldIgnore(event.target)) return;

                            isDown = true;
                            moved = false;
                            startX = event.clientX;
                            startScrollLeft = shell.scrollLeft;
                            shell.classList.add('is-panning');
                            shell.setPointerCapture?.(event.pointerId);
                        };

                        const onPointerMove = event => {
                            if (!isDown) return;

                            const delta = event.clientX - startX;
                            if (Math.abs(delta) > 3) moved = true;

                            shell.scrollLeft = startScrollLeft - delta;
                            event.preventDefault();
                        };

                        const stop = event => {
                            if (!isDown) return;
                            isDown = false;
                            shell.classList.remove('is-panning');

                            try {
                                shell.releasePointerCapture?.(event.pointerId);
                            } catch (_) {}
                        };

                        const onWheel = event => {
                            // Keep normal mouse-wheel scrolling vertical.
                            // Horizontal Kanban scrolling is only intentional
                            // via Shift + wheel or a true horizontal trackpad gesture.
                            if (!shell.matches(':hover')) return;

                            if (event.shiftKey && Math.abs(event.deltaY) > 0) {
                                shell.scrollLeft += event.deltaY;
                                event.preventDefault();
                                return;
                            }

                            if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                                shell.scrollLeft += event.deltaX;
                                event.preventDefault();
                            }
                        };

                        shell.addEventListener('pointerdown', onPointerDown);
                        shell.addEventListener('pointermove', onPointerMove);
                        shell.addEventListener('pointerup', stop);
                        shell.addEventListener('pointercancel', stop);
                        shell.addEventListener('mouseleave', stop);
                        shell.addEventListener('wheel', onWheel, { passive: false });

                        this.panCleanup = () => {
                            shell.removeEventListener('pointerdown', onPointerDown);
                            shell.removeEventListener('pointermove', onPointerMove);
                            shell.removeEventListener('pointermove', trackPointer);
                            shell.removeEventListener('pointerup', stop);
                            shell.removeEventListener('pointercancel', stop);
                            shell.removeEventListener('mouseleave', stop);
                            shell.removeEventListener('wheel', onWheel);
                            this.boardPointerMoveHandler = null;
                        };
                    },

                    startEdgeScroll(){
                        if (this.edgeScrollFrame) return;

                        const tick = () => {
                            const shell = this.$root.querySelector('[data-kanban-shell]');

                            if (
                                !shell ||
                                document.body.dataset.kanbanDragging !== '1' ||
                                this.edgeScrollPointerX === null
                            ) {
                                this.stopEdgeScroll();
                                return;
                            }

                            const rect = shell.getBoundingClientRect();
                            const edge = 180;
                            const maxSpeed = 34;
                            let speed = 0;

                            if (this.edgeScrollPointerX < rect.left + edge) {
                                const strength = Math.min(
                                    1,
                                    (rect.left + edge - this.edgeScrollPointerX) / edge
                                );
                                speed = -Math.max(10, maxSpeed * strength);
                            } else if (this.edgeScrollPointerX > rect.right - edge) {
                                const strength = Math.min(
                                    1,
                                    (this.edgeScrollPointerX - (rect.right - edge)) / edge
                                );
                                speed = Math.max(10, maxSpeed * strength);
                            }

                            if (speed !== 0) {
                                shell.scrollLeft += speed;
                            }

                            this.edgeScrollFrame = requestAnimationFrame(tick);
                        };

                        this.edgeScrollFrame = requestAnimationFrame(tick);
                    },

                    stopEdgeScroll(){
                        if (this.edgeScrollFrame) {
                            cancelAnimationFrame(this.edgeScrollFrame);
                            this.edgeScrollFrame = null;
                        }

                        this.edgeScrollPointerX = null;
                    },

                    showToast(message){
                        this.toast = message || 'Updated successfully.';
                        clearTimeout(this.toastTimer);
                        this.toastTimer = setTimeout(() => this.toast = '', 2600);
                    }
                };
            }
        </script>
    @endonce
</div>
