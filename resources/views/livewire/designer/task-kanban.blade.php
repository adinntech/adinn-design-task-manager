<div class="adinn-designer-section"
     x-data="designerKanban()"
     x-init="init()"
     x-on:kanban-updated.window="refreshSortable()"
     x-on:task-status-changed.window="showToast($event.detail.message)">

    <style>
        :root {
            --adinn-red: #e30613;
            --adinn-black: #111111;
            --adinn-surface: #ffffff;
            --adinn-bg: #f5f7fb;
            --adinn-border: #e3e7ef;
            --adinn-muted: #667085;
        }

        .adinn-designer-section { color: #172033; }
        .designer-page-title { font-size: 30px; font-weight: 800; color: var(--adinn-black); }
        .designer-page-subtitle { margin-top: 4px; color: var(--adinn-muted); }
        .designer-toolbar {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) 220px 180px;
            gap: 12px;
            margin: 22px 0 16px;
        }
        .designer-input {
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--adinn-border);
            border-radius: 12px;
            padding: 0 14px;
            background: #fff;
            outline: none;
        }
        .designer-input:focus { border-color: var(--adinn-red); box-shadow: 0 0 0 3px rgba(227,6,19,.10); }
        .kanban-shell {
            overflow-x: auto;
            padding: 4px 2px 18px;
            scrollbar-color: #9aa3b2 transparent;
        }
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(8, 310px);
            gap: 14px;
            min-width: max-content;
        }
        .kanban-column {
            border: 1px solid var(--adinn-border);
            border-radius: 16px;
            background: #f9fafc;
            min-height: 620px;
            overflow: hidden;
        }
        .kanban-column-header {
            min-height: 58px;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--adinn-black);
            color: white;
        }
        .kanban-column-header.is-active { background: var(--adinn-red); }
        .kanban-column-title { font-size: 14px; font-weight: 800; }
        .kanban-count {
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.16);
            font-weight: 800;
        }
        .kanban-list {
            min-height: 548px;
            padding: 12px;
        }
        .kanban-empty {
            height: 120px;
            border: 1px dashed #ccd3de;
            border-radius: 12px;
            color: #98a2b3;
            display: grid;
            place-items: center;
            font-size: 13px;
        }
        .task-card {
            display: block;
            border: 1px solid var(--adinn-border);
            border-left: 4px solid var(--adinn-red);
            border-radius: 14px;
            background: #fff;
            padding: 14px;
            margin-bottom: 12px;
            color: inherit;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(16,24,40,.05);
            cursor: grab;
        }
        .task-card:active { cursor: grabbing; }
        .task-card:hover { transform: translateY(-1px); box-shadow: 0 7px 18px rgba(16,24,40,.09); }
        .task-card-id { color: #7a8494; font-size: 12px; font-weight: 700; }
        .task-card-name { margin-top: 7px; font-size: 16px; font-weight: 800; color: var(--adinn-black); }
        .task-card-client { margin-top: 4px; color: #475467; font-size: 13px; }
        .task-card-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .task-meta-item { border-radius: 9px; background: #f7f8fa; padding: 8px; font-size: 12px; }
        .priority-badge {
            display: inline-flex;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .priority-urgent { background: #fee4e2; color: #b42318; }
        .priority-high { background: #fff0e0; color: #b54708; }
        .priority-medium { background: #fff8db; color: #8a6100; }
        .priority-low { background: #e9f8ef; color: #067647; }
        .sortable-ghost { opacity: .35; }
        .sortable-chosen { box-shadow: 0 12px 30px rgba(0,0,0,.14); }
        .kanban-invalid { animation: invalidDrop .35s ease; }
        @keyframes invalidDrop { 50% { background: #fee4e2; } }
        .designer-toast {
            position: fixed; right: 22px; bottom: 22px; z-index: 9999;
            background: var(--adinn-black); color: #fff; border-left: 4px solid var(--adinn-red);
            padding: 13px 16px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,.2);
        }
        @media (max-width: 900px) {
            .designer-toolbar { grid-template-columns: 1fr; }
        }
    </style>

    <div>
        <h1 class="designer-page-title">Designer Tasks</h1>
        <p class="designer-page-subtitle">Manage assigned design tasks across the complete production pipeline.</p>
    </div>

    <div class="designer-toolbar">
        <input class="designer-input"
               type="search"
               placeholder="Search Task ID, task name, client, vertical..."
               wire:model.live.debounce.350ms="search">

        <select class="designer-input" wire:model.live="vertical">
            <option value="">All Verticals</option>
            <option value="outdoor">Outdoor</option>
            <option value="roadshow">RoadShow</option>
            <option value="fixtures">Fixtures</option>
            <option value="signage">Signage</option>
            <option value="pop_offsets">POP and Offsets</option>
            <option value="digital_marketing">Digital Marketing</option>
            <option value="events_activations">Events and Activations</option>
        </select>

        <select class="designer-input" wire:model.live="priority">
            <option value="">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
    </div>

    <div class="kanban-shell">
        <div class="kanban-board">
            @foreach($statuses as $statusKey => $statusLabel)
                @php($columnTasks = $tasks->where('status', $statusKey))
                <section class="kanban-column" wire:key="column-{{ $statusKey }}">
                    <header class="kanban-column-header {{ $statusKey === 'assigned_tasks' ? 'is-active' : '' }}">
                        <span class="kanban-column-title">{{ $statusLabel }}</span>
                        <span class="kanban-count">{{ $columnTasks->count() }}</span>
                    </header>

                    <div class="kanban-list"
                         data-kanban-list
                         data-status="{{ $statusKey }}">
                        @forelse($columnTasks as $task)
                            <a href="{{ route('designer.tasks.show', $task) }}"
                               class="task-card"
                               data-task-id="{{ $task->id }}"
                               data-task-status="{{ $task->status }}"
                               wire:key="task-{{ $task->id }}">
                                <div style="display:flex;justify-content:space-between;gap:10px;align-items:start;">
                                    <span class="task-card-id">{{ $task->task_id }}</span>
                                    <span class="priority-badge priority-{{ $task->priority }}">{{ $task->priority }}</span>
                                </div>

                                <div class="task-card-name">{{ $task->task_name }}</div>
                                <div class="task-card-client">{{ ucfirst($task->party_type) }}: {{ $task->party_name }}</div>

                                <div class="task-card-meta">
                                    <div class="task-meta-item">
                                        <strong>Vertical</strong><br>
                                        {{ ucwords(str_replace('_', ' ', $task->vertical)) }}
                                    </div>
                                    <div class="task-meta-item">
                                        <strong>Creatives</strong><br>
                                        {{ $task->total_creatives }}
                                    </div>
                                    <div class="task-meta-item">
                                        <strong>Due</strong><br>
                                        {{ optional($task->due_at)->format('d M, h:i A') ?? \Illuminate\Support\Carbon::parse($task->due_at)->format('d M, h:i A') }}
                                    </div>
                                    <div class="task-meta-item">
                                        <strong>Assigned by</strong><br>
                                        {{ $task->assigner?->name ?? 'BD' }}
                                    </div>
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

    <div class="designer-toast" x-show="toast" x-transition x-text="toast" style="display:none;"></div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            function designerKanban() {
                return {
                    sortables: [],
                    toast: '',
                    toastTimer: null,

                    init() {
                        this.$nextTick(() => this.refreshSortable());

                        document.addEventListener('livewire:init', () => {
                            Livewire.hook('morph.updated', () => {
                                this.$nextTick(() => this.refreshSortable());
                            });
                        });
                    },

                    refreshSortable() {
                        this.sortables.forEach(instance => instance.destroy());
                        this.sortables = [];

                        document.querySelectorAll('[data-kanban-list]').forEach(list => {
                            this.sortables.push(new Sortable(list, {
                                group: 'designer-kanban',
                                animation: 180,
                                ghostClass: 'sortable-ghost',
                                chosenClass: 'sortable-chosen',
                                fallbackOnBody: true,

                                onEnd: (event) => {
                                    const card = event.item;
                                    const taskId = Number(card.dataset.taskId);
                                    const fromStatus = card.dataset.taskStatus;
                                    const targetStatus = event.to.dataset.status;

                                    if (!taskId || fromStatus === targetStatus) {
                                        return;
                                    }

                                    event.to.removeChild(card);
                                    event.from.insertBefore(card, event.from.children[event.oldIndex] ?? null);

                                    this.$wire.moveTask(taskId, targetStatus)
                                        .catch(() => {
                                            event.to.classList.add('kanban-invalid');
                                            setTimeout(() => event.to.classList.remove('kanban-invalid'), 400);
                                        });
                                }
                            }));
                        });
                    },

                    showToast(message) {
                        this.toast = message || 'Updated successfully.';
                        clearTimeout(this.toastTimer);
                        this.toastTimer = setTimeout(() => this.toast = '', 2600);
                    }
                }
            }
        </script>
    @endonce
</div>
