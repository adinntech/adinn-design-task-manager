<div
    x-data="bdKanban()"
    x-init="init()"
    x-on:designer-head-task-updated.window="showToast($event.detail.message)"
>
    <style>
        .bd-toolbar{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(auto-fit,minmax(140px,.6fr));gap:9px;margin-bottom:14px}
        .bd-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .metric-active-breakdown{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}
        .metric-active-chip{font-size:8px;font-weight:800;color:#475467;background:#f2f4f7;border-radius:999px;padding:2px 7px;white-space:nowrap}
        .continuation-badge{margin-top:7px;padding:5px 8px;border-radius:8px;background:#eef2ff;border:1px solid #c7d2fe;color:#3730a3;font-size:8px;font-weight:800;line-height:1.4}
        .previous-month-badge{margin-top:7px;padding:5px 8px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:8px;font-weight:800;line-height:1.4}
        .bd-period-strip{display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 13px;background:#fff;border:1px solid #eaecf0;border-radius:12px;margin-bottom:14px}
        .bd-period-viewing{font-size:9px;font-weight:900;color:#101828;white-space:nowrap}
        .bd-period-chip{font-size:8px;font-weight:850;color:#344054;background:#f7f8fa;border-radius:999px;padding:4px 10px;white-space:nowrap}
        .bd-period-chip strong{color:#101828;margin-left:3px}
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

        .kanban-column.kanban-column-focus{animation:kanbanColumnFocusBlink .5s ease-in-out 2}
        @keyframes kanbanColumnFocusBlink{
            0%,100%{box-shadow:none;border-color:#e7e9ef}
            50%{box-shadow:0 0 0 3px rgba(227,6,19,.55);border-color:#e30613}
        }

        .task-card{border:1px solid #e3e6ec;border-left:5px solid #cbd5e1;border-radius:11px;background:#fff;padding:11px;margin-bottom:8px;color:inherit;box-shadow:0 4px 12px rgba(16,24,40,.04);transition:.16s}
        .task-card:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(16,24,40,.08);border-color:#d7dbe3}
        .bd-draggable-card{cursor:grab}.bd-draggable-card:active{cursor:grabbing}.sortable-ghost{opacity:.35}.sortable-chosen{box-shadow:0 12px 30px rgba(0,0,0,.14)}.kanban-invalid{animation:invalidDrop .35s ease}@keyframes invalidDrop{50%{background:#fee4e2}}
        .task-card-highlight{animation:taskCardHighlightPulse .7s ease-in-out 3}
        @keyframes taskCardHighlightPulse{0%,100%{box-shadow:0 4px 12px rgba(16,24,40,.04);border-color:#e3e6ec}50%{box-shadow:0 0 0 4px rgba(227,6,19,.35);border-color:#e30613}}
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

        .request-column{border-color:#fecaca;background:#fff8f7}
        .request-column .kanban-column-header{background:#fff1f0;border-bottom-color:#fecaca}
        .request-card{border-left-color:#e30613!important;background:linear-gradient(90deg,#fff1f1 0,#fff 22%)}
        .request-type-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:950;text-transform:uppercase;letter-spacing:.04em}
        .request-type-decline{background:#fff1f0;color:#b42318;border:1px solid #fecdca}
        .request-type-split{background:#f4f0ff;color:#6938ef;border:1px solid #d9d6fe}
        .request-type-swap{background:#ecfdf3;color:#067647;border:1px solid #abefc6}
        .request-open-label{margin-top:9px;padding-top:8px;border-top:1px solid #eef0f3;color:#e30613;font-size:9px;font-weight:900}
    
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
            <h1>All Tasks</h1>
            <p>Monitor all Designer tasks across the complete production pipeline.</p>
        </div>
    </div>

    <div class="bd-metrics">
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

                <select class="premium-select" wire:model.live="bdId">
                    <option value="">All BDs</option>
                    @foreach($bds as $bdOption)
                        <option value="{{ $bdOption->id }}">{{ $bdOption->name }}</option>
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

                <a class="btn btn-secondary" href="{{ route('designer-head.tasks.export', [
                    'search' => $search, 'vertical' => $vertical, 'priority' => $priority,
                    'designer_id' => $designerId, 'bd_id' => $bdId, 'period' => $period,
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

    <div class="bd-period-strip">
        <span class="bd-period-viewing">Viewing: {{ $periodLabel }}</span>
        <span class="bd-period-chip">Total in period<strong>{{ $periodStats['total'] }}</strong></span>
        <span class="bd-period-chip">Completed<strong>{{ $periodStats['completed'] }}</strong></span>
        <span class="bd-period-chip">Swapped<strong>{{ $periodStats['swapped'] }}</strong></span>
        <span class="bd-period-chip">Split<strong>{{ $periodStats['split'] }}</strong></span>
        <span class="bd-period-chip">Declined<strong>{{ $periodStats['declined'] }}</strong></span>
    </div>

    <div class="kanban-shell" data-designer-head-kanban-shell>
        <div class="kanban-board">
            <section class="kanban-column request-column" data-status="requests" wire:key="designer-head-requests">
                <header class="kanban-column-header">
                    <span class="kanban-column-title">Requests</span>
                    <span class="kanban-count">{{ $pendingRequests->count() }}</span>
                </header>

                <div class="kanban-list">
                    @forelse($pendingRequests as $request)
                        @if($request->task)
                            <article class="task-card request-card">
                                <a
                                    class="task-card-link"
                                    href="{{ route('designer-head.tasks.show', ['task' => $request->task, 'tab' => $request->request_type === 'split' ? 'split-details' : ($request->request_type === 'swap' ? 'swap-details' : 'decline-details')]) }}"
                                    draggable="false"
                                >
                                    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
                                        <div class="task-card-id">{{ $request->task->task_id }}</div>
                                        <span class="request-type-pill request-type-{{ $request->request_type }}">{{ ucfirst($request->request_type) }}</span>
                                    </div>

                                    <div class="task-card-name">{{ $request->task->display_task_name ?? $request->task->task_name }}</div>
                                    <div class="task-card-client">{{ $request->task->party_name }}</div>

                                    <div class="task-card-meta">
                                        <div class="task-meta-item"><strong>Requested By</strong>{{ $request->requester?->name ?? '—' }}</div>
                                        <div class="task-meta-item"><strong>Designer</strong>{{ $request->task->designer?->name ?? '—' }}</div>

                                        @if($request->request_type === 'split')
                                            <div class="task-meta-item"><strong>Requested Split</strong>{{ data_get($request,'split_count') ?? data_get($request,'split_details.requested_count') ?? data_get($request,'split_details.creative_count') ?? '—' }}</div>
                                        @endif

                                        @if(in_array($request->request_type, ['split','swap'], true))
                                            <div class="task-meta-item"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div>
                                        @endif
                                    </div>

                                    <div class="request-open-label">Open task to review</div>
                                </a>
                            </article>
                        @endif
                    @empty
                        <div class="empty-state">No pending requests.</div>
                    @endforelse
                </div>
            </section>

            @foreach($statuses as $statusKey => $statusLabel)
                @php
                    $columnTasks = $tasks->where('status', $statusKey);
                    if ($statusKey === 'assigned_tasks') {
                        $columnTasks = $columnTasks->sortByDesc('created_at');
                    }
                @endphp

                <section class="kanban-column status-{{ $statusKey }}" data-status="{{ $statusKey }}" wire:key="designer-head-column-{{ $statusKey }}">
                    <header class="kanban-column-header">
                        <span class="kanban-column-title">{{ $statusLabel }}</span>
                        <span class="kanban-count">{{ $columnTasks->count() }}</span>
                    </header>

                    <div class="kanban-list" data-designer-head-kanban-list data-status="{{ $statusKey }}">
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

                            <article class="task-card {{ $dueClass }}" data-task-id="{{ $task->id }}" data-task-code="{{ $task->task_id }}" data-task-status="{{ $task->status }}" wire:key="designer-head-task-{{ $task->id }}">
                                <a class="task-card-link" href="{{ route('designer-head.tasks.show', $task) }}" draggable="false">
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
                                    @if($task->is_previous_month_task ?? false)
                                        <div class="previous-month-badge">{{ $task->previous_month_label }}</div>
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
                                            {{ $dueAt->format('d M, h:i A') }}
                                        </div>

                                        <div class="task-meta-item">
                                            <strong>Created</strong>
                                            {{ $task->created_at->format('d M Y • h:i A') }}
                                        </div>
                                    </div>
                                </a>


                            </article>
                        @empty
                            <div class="kanban-empty">No matching tasks</div>
                        @endforelse
                    </div>
                </section>

                @if($statusKey === 'completed')
                    <section class="kanban-column request-column" data-status="split_log" wire:key="designer-head-split-log">
                        <header class="kanban-column-header">
                            <span class="kanban-column-title">Split Tasks ({{ $periodLabel }})</span>
                            <span class="kanban-count">{{ $splitLogRows->count() }}</span>
                        </header>

                        <div class="kanban-list">
                            @forelse($splitLogRows as $row)
                                @php $childTask = $row['childTask']; $request = $row['request']; @endphp
                                <article class="task-card request-card">
                                    <a class="task-card-link" href="{{ route('designer-head.tasks.show', $childTask) }}" draggable="false">
                                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
                                            <div class="task-card-id">{{ $childTask->task_id }}</div>
                                            <span class="request-type-pill request-type-split">Split</span>
                                        </div>

                                        <div class="task-card-name">{{ $childTask->display_task_name ?? $childTask->task_name }}</div>

                                        <div class="task-card-meta">
                                            <div class="task-meta-item"><strong>Split From</strong>{{ $request->task?->task_id ?? '—' }}</div>
                                            <div class="task-meta-item"><strong>Designer</strong>{{ $childTask->designer?->name ?? '—' }}</div>
                                            <div class="task-meta-item"><strong>Creatives</strong>{{ $childTask->total_creatives }}</div>
                                            <div class="task-meta-item"><strong>Approved</strong>{{ optional($request->responded_at)->format('d M Y') ?? '—' }}</div>
                                        </div>

                                        <div class="request-open-label">Open task</div>
                                    </a>
                                </article>
                            @empty
                                <div class="empty-state">No splits approved in {{ $periodLabel }}.</div>
                            @endforelse
                        </div>
                    </section>
                @endif
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
                            this.focusRequestedColumn();
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

                    // Dashboard cards link here with ?focus=<status>[&task=<task_id>];
                    // scroll the board horizontally to the right column once, on initial
                    // page load only (never on Livewire re-renders, so it can't fight the
                    // user's scroll or re-fire the highlight pulse on every drag/update).
                    // When a task id is present, its own column (found via the DOM) takes
                    // priority over ?focus, so a task shown under a synthetic column
                    // (e.g. Declined) still scrolls to where it actually renders. The
                    // lookup retries briefly (bounded, not a blind delay) in case the
                    // card isn't in the DOM on the very first tick, then gives up safely
                    // if the task truly isn't on this board.
                    focusRequestedColumn(){
                        const params = new URLSearchParams(window.location.search);
                        const focus = params.get('focus');
                        const taskCode = params.get('task');
                        if (!focus && !taskCode) return;

                        const attempt = (tries) => {
                            const shell = this.$root.querySelector('[data-designer-head-kanban-shell]');
                            const target = taskCode ? this.$root.querySelector('[data-task-code="' + CSS.escape(taskCode) + '"]') : null;

                            if (taskCode && !target && tries < 20) {
                                setTimeout(() => attempt(tries + 1), 100);
                                return;
                            }

                            const column = target ? target.closest('.kanban-column')
                                : (focus ? this.$root.querySelector('.kanban-column[data-status="' + focus + '"]') : null);

                            const afterScroll = () => {
                                if (column) {
                                    column.classList.add('kanban-column-focus');
                                    column.addEventListener('animationend', function onColumnBlinkEnd(event) {
                                        if (event.animationName !== 'kanbanColumnFocusBlink') return;
                                        column.classList.remove('kanban-column-focus');
                                        column.removeEventListener('animationend', onColumnBlinkEnd);
                                    });
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

                            if (shell && column) {
                                this.scrollShellTo(shell, Math.max(0, column.offsetLeft - 12), afterScroll);
                            } else {
                                afterScroll();
                            }
                        };

                        attempt(0);
                    },

                    // Smooth-scrolls the shell, then calls `done` once movement actually
                    // stops (native `scrollend` where supported, otherwise a scroll-event
                    // debounce) so the blink never starts mid-scroll. A generous safety
                    // timer guarantees `done` still fires if neither signal arrives.
                    scrollShellTo(shell, left, done){
                        if (Math.abs(shell.scrollLeft - left) < 2) {
                            shell.scrollTo({ left, behavior: 'smooth' });
                            done();
                            return;
                        }

                        let finished = false;
                        let debounce = null;

                        const finish = () => {
                            if (finished) return;
                            finished = true;
                            clearTimeout(debounce);
                            clearTimeout(safety);
                            shell.removeEventListener('scroll', onScroll);
                            shell.removeEventListener('scrollend', finish);
                            done();
                        };

                        const onScroll = () => {
                            clearTimeout(debounce);
                            debounce = setTimeout(finish, 120);
                        };

                        shell.addEventListener('scroll', onScroll, { passive: true });
                        shell.addEventListener('scrollend', finish);
                        const safety = setTimeout(finish, 1500);

                        shell.scrollTo({ left, behavior: 'smooth' });
                    },

                    refreshSortable(){
                        this.sortables.forEach(item => item.destroy());
                        this.sortables = [];

                        document.querySelectorAll('[data-designer-head-kanban-list]').forEach(list => {
                            this.sortables.push(new Sortable(list, {
                                group: 'bd-kanban',
                                animation: 180,
                                ghostClass: 'sortable-ghost',
                                chosenClass: 'sortable-chosen',
                                fallbackOnBody: true,

                                draggable: '.bd-draggable-card',

                                onMove: event => {
                                    const card = event.dragged;
                                    const fromStatus = card?.dataset?.taskStatus;
                                    const targetStatus = event.to?.dataset?.status;

                                    return fromStatus === 'waiting_confirmation'
                                        && ['rework', 'completed'].includes(targetStatus);
                                },

                                onEnd: event => {
                                    const card = event.item;
                                    const taskId = Number(card.dataset.taskId);
                                    const fromStatus = card.dataset.taskStatus;
                                    const targetStatus = event.to.dataset.status;

                                    const valid = fromStatus === 'waiting_confirmation'
                                        && ['rework', 'completed'].includes(targetStatus);

                                    if (!valid) {
                                        if (event.to !== event.from) {
                                            event.to.removeChild(card);
                                            event.from.insertBefore(
                                                card,
                                                event.from.children[event.oldIndex] ?? null
                                            );
                                        }

                                        event.to.classList.add('kanban-invalid');
                                        setTimeout(() => event.to.classList.remove('kanban-invalid'), 400);

                                        this.showToast('BD can move tasks only from Waiting for Confirmation to Rework or Completed.');
                                        return;
                                    }

                                    event.to.removeChild(card);
                                    event.from.insertBefore(
                                        card,
                                        event.from.children[event.oldIndex] ?? null
                                    );

                                    this.$wire.moveTask(taskId, targetStatus)
                                        .catch(() => {
                                            this.showToast('Task could not be moved.');
                                        });
                                }
                            }));
                        });
                    },


                    enablePointerEdgeScroll(){
                        const shell = this.$root.querySelector('[data-kanban-shell], [data-designer-head-kanban-shell]');
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

                        const shell = this.$root.querySelector('[data-designer-head-kanban-shell]');
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
