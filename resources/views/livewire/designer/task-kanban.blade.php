<div x-data="designerKanban()" x-init="init()" x-on:task-status-changed.window="showToast($event.detail.message)">
    <style>
        .designer-toolbar{display:grid;grid-template-columns:minmax(260px,1.5fr) minmax(160px,.65fr) minmax(150px,.55fr);gap:9px;margin-bottom:14px}.kanban-shell{overflow:auto;padding-bottom:8px}.kanban-board{display:grid;grid-template-columns:repeat(8,270px);gap:10px;min-width:max-content}.kanban-column{border:1px solid #e7e9ef;border-radius:14px;background:#f9fafb;overflow:hidden}.kanban-column-header{padding:12px 12px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e7e9ef;background:#fff}.kanban-column-title{font-size:10px;font-weight:900;color:#4b5361;text-transform:uppercase;letter-spacing:.04em}.kanban-count{min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#eef0f4;display:grid;place-items:center;font-size:10px;font-weight:900}.kanban-list{padding:9px;min-height:420px}.kanban-empty{height:105px;border:1px dashed #cfd4dd;border-radius:10px;display:grid;place-items:center;color:#9aa1ad;font-size:10px}.task-card{display:block;border:1px solid #e3e6ec;border-radius:11px;background:#fff;padding:11px;margin-bottom:8px;color:inherit;text-decoration:none;box-shadow:0 4px 12px rgba(16,24,40,.04);cursor:grab;transition:.16s}.task-card:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(16,24,40,.08);border-color:#d7dbe3}.task-card-id{color:#7c8492;font-size:9px;font-weight:850}.task-card-name{margin-top:6px;font-size:12px;font-weight:900;line-height:1.35}.task-card-client{margin-top:4px;color:#5f6877;font-size:10px}.task-card-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:9px}.task-meta-item{border-radius:8px;background:#f7f8fa;padding:7px;font-size:9px;color:#616a78}.task-meta-item strong{display:block;color:#343b46;font-size:8px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}.sortable-ghost{opacity:.35}.sortable-chosen{box-shadow:0 12px 30px rgba(0,0,0,.14)}.kanban-invalid{animation:invalidDrop .35s ease}@keyframes invalidDrop{50%{background:#fee4e2}}.designer-toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;box-shadow:0 15px 40px rgba(0,0,0,.2);font-size:11px}@media(max-width:900px){.designer-toolbar{grid-template-columns:1fr}}
    </style>

    <div class="page-head">
        <div><h1>My Tasks</h1><p>Manage assigned design tasks across the complete production pipeline.</p></div>
        <div class="page-actions"><span class="badge badge-dark">{{ $tasks->count() }} visible tasks</span></div>
    </div>

    <div class="panel" style="margin-bottom:14px"><div class="panel-body">
        <div class="designer-toolbar">
            <input class="premium-input" type="search" placeholder="Search Task ID, task name, client, vertical..." wire:model.live.debounce.350ms="search">
            <select class="premium-select" wire:model.live="vertical"><option value="">All Verticals</option><option value="outdoor">Outdoor</option><option value="roadshow">RoadShow</option><option value="fixtures">Fixtures</option><option value="signage">Signage</option><option value="pop_offsets">POP and Offsets</option><option value="digital_marketing">Digital Marketing</option><option value="events_activations">Events and Activations</option></select>
            <select class="premium-select" wire:model.live="priority"><option value="">All Priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select>
        </div>
    </div></div>

    <div class="kanban-shell">
        <div class="kanban-board">
            @foreach($statuses as $statusKey => $statusLabel)
                @php($columnTasks = $tasks->where('status', $statusKey))
                <section class="kanban-column" wire:key="column-{{ $statusKey }}">
                    <header class="kanban-column-header"><span class="kanban-column-title">{{ $statusLabel }}</span><span class="kanban-count">{{ $columnTasks->count() }}</span></header>
                    <div class="kanban-list" data-kanban-list data-status="{{ $statusKey }}">
                        @forelse($columnTasks as $task)
                            <a href="{{ route('designer.tasks.show', $task) }}" class="task-card" data-task-id="{{ $task->id }}" data-task-status="{{ $task->status }}" wire:key="task-{{ $task->id }}">
                                <div style="display:flex;justify-content:space-between;gap:8px;align-items:start"><span class="task-card-id">{{ $task->task_id }}</span><span class="badge priority-{{ $task->priority }}">{{ $task->priority }}</span></div>
                                <div class="task-card-name">{{ $task->task_name }}</div>
                                <div class="task-card-client">{{ ucfirst($task->party_type) }} · {{ $task->party_name }}</div>
                                <div class="task-card-meta">
                                    <div class="task-meta-item"><strong>Vertical</strong>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</div>
                                    <div class="task-meta-item"><strong>Creatives</strong>{{ $task->total_creatives }}</div>
                                    <div class="task-meta-item"><strong>Due</strong>{{ \Illuminate\Support\Carbon::parse($task->due_at)->format('d M, h:i A') }}</div>
                                    <div class="task-meta-item"><strong>Assigned by</strong>{{ $task->assigner?->name ?? 'BD' }}</div>
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
            function designerKanban(){return{sortables:[],toast:'',toastTimer:null,init(){this.$nextTick(()=>this.refreshSortable());document.addEventListener('livewire:init',()=>{Livewire.hook('morph.updated',()=>this.$nextTick(()=>this.refreshSortable()))})},refreshSortable(){this.sortables.forEach(i=>i.destroy());this.sortables=[];document.querySelectorAll('[data-kanban-list]').forEach(list=>{this.sortables.push(new Sortable(list,{group:'designer-kanban',animation:180,ghostClass:'sortable-ghost',chosenClass:'sortable-chosen',fallbackOnBody:true,onEnd:(event)=>{const card=event.item;const taskId=Number(card.dataset.taskId);const fromStatus=card.dataset.taskStatus;const targetStatus=event.to.dataset.status;if(!taskId||fromStatus===targetStatus)return;event.to.removeChild(card);event.from.insertBefore(card,event.from.children[event.oldIndex]??null);this.$wire.moveTask(taskId,targetStatus).catch(()=>{event.to.classList.add('kanban-invalid');setTimeout(()=>event.to.classList.remove('kanban-invalid'),400)})}}))})},showToast(message){this.toast=message||'Updated successfully.';clearTimeout(this.toastTimer);this.toastTimer=setTimeout(()=>this.toast='',2600)}}}
        </script>
    @endonce
</div>
