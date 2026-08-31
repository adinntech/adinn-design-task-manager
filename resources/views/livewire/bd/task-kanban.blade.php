<div
    x-data="bdKanban()"
    x-init="init()"
    x-on:bd-task-updated.window="showToast($event.detail.message)"
>
    <style>
        .bd-toolbar{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(auto-fit,minmax(140px,.6fr));gap:9px;margin-bottom:14px}
        .bd-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .metric-active-breakdown{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}
        .metric-active-chip{font-size:8px;font-weight:800;color:#475467;background:#f2f4f7;border-radius:999px;padding:2px 7px;white-space:nowrap}
        .continuation-badge{margin-top:7px;padding:5px 8px;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;font-size:8px;font-weight:800;line-height:1.4}
        .applied-filters{display:flex;align-items:center;flex-wrap:wrap;gap:7px;margin-top:12px;padding-top:12px;border-top:1px solid #eef0f3}
        .applied-filters-label{font-size:9px;font-weight:900;color:#475467;text-transform:uppercase;letter-spacing:.04em}
        .applied-filter-chip{font-size:9px;font-weight:850;color:#101828;background:#f2f4f7;border:1px solid #e4e7ec;border-radius:999px;padding:5px 10px;white-space:nowrap}
        .applied-filters-clear{margin-left:auto;padding:6px 12px;font-size:10px;min-height:auto}

        .kanban-shell{overflow-x:auto;overflow-y:visible;padding-bottom:8px;scrollbar-width:none;-ms-overflow-style:none;cursor:grab;user-select:none;position:relative}
        .kanban-shell::-webkit-scrollbar{display:none}
        .kanban-shell.is-panning{cursor:grabbing}
        .kanban-board{display:grid;grid-template-columns:repeat(10,270px);grid-auto-flow:column;grid-auto-columns:270px;gap:10px;min-width:max-content}
        .kanban-column{border:1px solid #e7e9ef;border-radius:14px;background:#f9fafb;overflow:hidden}
        .kanban-column-header{padding:12px 12px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e7e9ef;background:#fff;border-top:4px solid #98a2b3}
        .kanban-column-title{font-size:10px;font-weight:900;color:#344054;text-transform:uppercase;letter-spacing:.04em}
        .kanban-count{min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#eef0f4;color:#344054;display:grid;place-items:center;font-size:10px;font-weight:900}
        .kanban-list{padding:9px;min-height:420px}
        .kanban-empty{height:105px;border:1px dashed #cfd4dd;border-radius:10px;display:grid;place-items:center;color:#9aa1ad;font-size:10px}

        .kanban-column.status-assigned_tasks .kanban-column-header{border-top-color:#667085;background:#f9fafb}
        .kanban-column.status-review_analysis .kanban-column-header{border-top-color:#2563eb;background:#eff6ff}
        .kanban-column.status-need_clarification .kanban-column-header{border-top-color:#d97706;background:#fffbeb}
        .kanban-column.status-yet_to_start .kanban-column-header{border-top-color:#7c3aed;background:#f5f3ff}
        .kanban-column.status-in_progress .kanban-column-header{border-top-color:#0891b2;background:#ecfeff}
        .kanban-column.status-waiting_confirmation .kanban-column-header{border-top-color:#db2777;background:#fdf2f8}
        .kanban-column.status-rework .kanban-column-header{border-top-color:#ea580c;background:#fff7ed}
        .kanban-column.status-completed .kanban-column-header{border-top-color:#16a34a;background:#f0fdf4}
        .kanban-column.status-swap_tasks .kanban-column-header{border-top-color:#0f766e;background:#f0fdfa}
        .kanban-column.status-decline_tasks .kanban-column-header{border-top-color:#b42318;background:#fff5f5}

        .kanban-column.status-assigned_tasks .kanban-count{background:#eaecf0;color:#475467}
        .kanban-column.status-review_analysis .kanban-count{background:#dbeafe;color:#1d4ed8}
        .kanban-column.status-need_clarification .kanban-count{background:#fef3c7;color:#b45309}
        .kanban-column.status-yet_to_start .kanban-count{background:#ede9fe;color:#6d28d9}
        .kanban-column.status-in_progress .kanban-count{background:#cffafe;color:#0e7490}
        .kanban-column.status-waiting_confirmation .kanban-count{background:#fce7f3;color:#be185d}
        .kanban-column.status-rework .kanban-count{background:#ffedd5;color:#c2410c}
        .kanban-column.status-completed .kanban-count{background:#dcfce7;color:#15803d}
        .kanban-column.status-swap_tasks .kanban-count{background:#ccfbf1;color:#0f766e}
        .kanban-column.status-decline_tasks .kanban-count{background:#fee4e2;color:#b42318}

        .task-card{border:1px solid #e3e6ec;border-left:5px solid #cbd5e1;border-radius:11px;background:#fff;padding:11px;margin-bottom:8px;color:inherit;box-shadow:0 4px 12px rgba(16,24,40,.04);transition:.16s}
        .task-card:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(16,24,40,.08);border-color:#d7dbe3}
        .bd-draggable-card{cursor:grab}.bd-draggable-card:active{cursor:grabbing}.sortable-ghost{opacity:.35}.sortable-chosen{box-shadow:0 12px 30px rgba(0,0,0,.14)}.kanban-invalid{animation:invalidDrop .35s ease}@keyframes invalidDrop{50%{background:#fee4e2}}
        .task-card.due-overdue{border-left-color:#dc2626;background:linear-gradient(90deg,#fff1f2 0,#fff 22%)}
        .task-card.due-today{border-left-color:#ea580c;background:linear-gradient(90deg,#fff7ed 0,#fff 22%)}
        .task-card.due-soon{border-left-color:#d97706;background:linear-gradient(90deg,#fffbeb 0,#fff 22%)}
        .task-card.due-safe{border-left-color:#16a34a;background:linear-gradient(90deg,#f0fdf4 0,#fff 22%)}
        .task-card.due-completed{border-left-color:#94a3b8;background:#fff}

        .task-card-link{display:block;color:inherit;text-decoration:none}
        .task-card-id{color:#7c8492;font-size:9px;font-weight:850}
        .task-card-name{margin-top:6px;font-size:12px;font-weight:900;line-height:1.35}
        .task-card-client{margin-top:4px;color:#5f6877;font-size:10px}.kanban-progress{margin-top:9px;padding:8px;border:1px solid #e7e9ef;border-radius:9px;background:#fff}.kanban-progress-head{display:flex;justify-content:space-between;gap:8px;align-items:center;font-size:8px;color:#667085}.kanban-progress-head strong{font-size:8px;color:#344054}.kanban-progress-track{height:6px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:6px}.kanban-progress-fill{height:100%;background:#e30613;border-radius:999px}
        .task-card-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
        .task-history-tag{display:inline-flex;align-items:center;min-height:22px;padding:3px 9px;border-radius:999px;font-size:8px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent}
        .task-tag-split{color:#6938ef;background:#f4f0ff;border-color:#d9d6fe}
        .task-tag-swap{color:#067647;background:#ecfdf3;border-color:#abefc6}
        .task-tag-decline{color:#b42318;background:#fff1f0;border-color:#fecdca}
        .task-tag-pending{color:#b54708;background:#fffaeb;border-color:#fedf89}

        .task-card-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:9px}
        .task-meta-item{border-radius:8px;background:#f7f8fa;padding:7px;font-size:9px;color:#616a78}
        .task-meta-item strong{display:block;color:#343b46;font-size:8px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}

        .due-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.035em}
        .due-pill.due-overdue{background:#fee2e2;color:#b91c1c}
        .due-pill.due-today{background:#ffedd5;color:#c2410c}
        .due-pill.due-soon{background:#fef3c7;color:#b45309}
        .due-pill.due-safe{background:#dcfce7;color:#15803d}
        .due-pill.due-completed{background:#f1f5f9;color:#64748b}

        .bd-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:9px;padding-top:9px;border-top:1px solid #eef0f3}
        .bd-card-action{border-radius:8px;padding:7px 8px;font-size:9px;font-weight:900;cursor:pointer}
        .bd-card-rework{border:1px solid #fed7aa;background:#fff7ed;color:#c2410c}
        .bd-card-complete{border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d}
        .bd-card-action[disabled]{opacity:.55;cursor:not-allowed}

        .bd-toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;box-shadow:0 15px 40px rgba(0,0,0,.2);font-size:11px}

        @media(max-width:900px){
            .bd-toolbar{grid-template-columns:1fr}
            .bd-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
    
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
        <div>
            <h1>BD Task Kanban</h1>
            <p>Monitor every design task created by you across the complete production pipeline.</p>
        </div>

        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('bd.tasks.create') }}">＋ Create New Task</a>
        </div>
    </div>

    <div class="bd-metrics">
        <div class="metric-card">
            <div class="metric-label">My Total Tasks</div>
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

    <div class="panel" style="margin-bottom:14px">
        <div class="panel-body">
            <div class="bd-toolbar">
                <input
                    class="premium-input"
                    type="search"
                    placeholder="Search Task ID, task name, client, Designer, BD..."
                    wire:model.live.debounce.350ms="search"
                >

                <select class="premium-select" wire:model.live="vertical">
                    <option value="">All Verticals</option>
                    <option value="outdoor">Outdoor</option>
                    <option value="roadshow">RoadShow</option>
                    <option value="fixtures">Fixtures</option>
                    <option value="signage">Signage</option>
                    <option value="pop_offsets">POP and Offsets</option>
                    <option value="digital_marketing">Digital Marketing</option>
                    <option value="events_activations">Events and Activations</option>
                </select>

                <select class="premium-select" wire:model.live="priority">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>

                <select class="premium-select" wire:model.live="designerId">
                    <option value="">All Designers</option>
                    @foreach($designers as $designerOption)
                        <option value="{{ $designerOption->id }}">{{ $designerOption->name }}</option>
                    @endforeach
                </select>

                <select class="premium-select" wire:model.live="period">
                    <option value="current_month">Current Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="custom">Custom Period</option>
                </select>

                @if($period === 'custom')
                    <input class="premium-input" type="date" wire:model.live="dateFrom" max="{{ $dateTo ?: '' }}">
                    <input class="premium-input" type="date" wire:model.live="dateTo" min="{{ $dateFrom ?: '' }}">
                @endif

                <a class="btn btn-secondary" href="{{ route('bd.tasks.export', [
                    'search' => $search, 'vertical' => $vertical, 'priority' => $priority,
                    'designer_id' => $designerId, 'period' => $period,
                    'date_from' => $dateFrom, 'date_to' => $dateTo,
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
        </div>
    </div>

    <div class="kanban-shell" data-bd-kanban-shell>
        <div class="kanban-board">
            @foreach($statuses as $statusKey => $statusLabel)
                @php
                    $columnTasks = $tasks->where('status', $statusKey);
                @endphp

                <section class="kanban-column status-{{ $statusKey }}" wire:key="bd-column-{{ $statusKey }}">
                    <header class="kanban-column-header">
                        <span class="kanban-column-title">{{ $statusLabel }}</span>
                        <span class="kanban-count">{{ $columnTasks->count() }}</span>
                    </header>

                    <div class="kanban-list" data-bd-kanban-list data-status="{{ $statusKey }}">
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

                            <article class="task-card {{ $dueClass }}" data-task-id="{{ $task->id }}" data-task-status="{{ $task->status }}" wire:key="bd-task-{{ $task->id }}">
                                <a class="task-card-link" href="{{ route('bd.tasks.show', $task) }}" draggable="false">
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
                                        <div class="task-meta-item">
                                            <strong>Designer</strong>
                                            {{ $task->designer?->name ?? '—' }}
                                        </div>

                                        <div class="task-meta-item">
                                            <strong>Creatives</strong>
                                            {{ $task->total_creatives }}
                                        </div>

                                        <div class="task-meta-item">
                                            <strong>Vertical</strong>
                                            {{ ucwords(str_replace('_',' ',$task->vertical)) }}
                                        </div>

                                        <div class="task-meta-item">
                                            <strong>Due</strong>
                                            {{ $dueAt->format('d M Y') }}
                                        </div>

                                        <div class="task-meta-item">
                                            <strong>Created</strong>
                                            {{ $task->created_at->format('d M Y') }}
                                        </div>
                                    </div>
                                </a>

                                @if($task->status === 'waiting_confirmation')
                                    <div class="bd-card-actions">
                                        <a
                                            href="{{ route('bd.tasks.show', ['task' => $task, 'tab' => 'eod']) }}"
                                            class="bd-card-action bd-card-complete"
                                            style="text-decoration:none;text-align:center;width:100%"
                                        >
                                            Review Task
                                        </a>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="kanban-empty">No matching tasks</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="bd-toast" x-show="toast" x-transition x-text="toast" style="display:none"></div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            function bdKanban(){
                return {
                    toast: '',
                    toastTimer: null,
                    cleanupPan: null,
                    pointerEdgeCleanup: null,
                    sortables: [],

                    init(){
                        this.$nextTick(() => {
                            this.enablePan();
                            this.enablePointerEdgeScroll();
                            this.refreshSortable();
                        });

                        document.addEventListener('livewire:init', () => {
                            Livewire.hook('morph.updated', () => {
                                this.$nextTick(() => {
                                    this.enablePan();
                                    this.enablePointerEdgeScroll();
                                    this.refreshSortable();
                                });
                            });
                        });
                    },

                    refreshSortable(){
                        // BD Kanban is intentionally view-only. Rework and Completion
                        // require structured review data from the task detail page.
                        this.sortables.forEach(item => item.destroy());
                        this.sortables = [];
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

                    enablePan(){
                        if (this.cleanupPan) {
                            this.cleanupPan();
                            this.cleanupPan = null;
                        }

                        const shell = this.$root.querySelector('[data-bd-kanban-shell]');
                        if (!shell) return;

                        let isDown = false;
                        let startX = 0;
                        let startScrollLeft = 0;

                        const ignoreTarget = target => {
                            return !!target.closest('a, button, input, select, textarea');
                        };

                        const pointerDown = event => {
                            if (event.button !== 0 || ignoreTarget(event.target)) return;

                            isDown = true;
                            startX = event.clientX;
                            startScrollLeft = shell.scrollLeft;
                            shell.classList.add('is-panning');
                            shell.setPointerCapture?.(event.pointerId);
                        };

                        const pointerMove = event => {
                            if (!isDown) return;

                            shell.scrollLeft = startScrollLeft - (event.clientX - startX);
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

                        const wheel = event => {
                            // Normal wheel stays vertical page scrolling.
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

                        shell.addEventListener('pointerdown', pointerDown);
                        shell.addEventListener('pointermove', pointerMove);
                        shell.addEventListener('pointerup', stop);
                        shell.addEventListener('pointercancel', stop);
                        shell.addEventListener('mouseleave', stop);
                        shell.addEventListener('wheel', wheel, { passive: false });

                        this.cleanupPan = () => {
                            shell.removeEventListener('pointerdown', pointerDown);
                            shell.removeEventListener('pointermove', pointerMove);
                            shell.removeEventListener('pointerup', stop);
                            shell.removeEventListener('pointercancel', stop);
                            shell.removeEventListener('mouseleave', stop);
                            shell.removeEventListener('wheel', wheel);
                        };
                    },

                    showToast(message){
                        this.toast = message || 'Task updated successfully.';
                        clearTimeout(this.toastTimer);
                        this.toastTimer = setTimeout(() => this.toast = '', 2600);
                    }
                };
            }
        </script>
    @endonce
</div>
