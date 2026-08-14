@extends('layouts.app')

@section('title','Designer Head Kanban')
@section('workspace-title','Designer Head Kanban')
@section('workspace-subtitle','View all Designer tasks and review pending requests')

@section('content')
<style>
    .dh-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px}
    .dh-head h1{font-size:22px;line-height:1.2;font-weight:800;color:#101828;margin:0 0 5px}
    .dh-head p{margin:0;color:#667085;font-size:12px}
    .dh-stats{display:flex;gap:8px;flex-wrap:wrap}
    .dh-stat{border:1px solid #eaecf0;background:#fff;border-radius:12px;padding:9px 12px;min-width:110px}
    .dh-stat span{display:block;color:#667085;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
    .dh-stat strong{display:block;color:#101828;font-size:18px;margin-top:2px}
    .dh-readonly{display:inline-flex;align-items:center;gap:6px;border:1px solid #d0d5dd;background:#f9fafb;color:#475467;padding:7px 10px;border-radius:999px;font-size:10px;font-weight:800}
    .dh-board-wrap{overflow-x:auto;padding:2px 2px 14px;scrollbar-width:thin}
    .dh-board{display:flex;gap:12px;align-items:flex-start;min-width:max-content}
    .dh-column{width:292px;flex:0 0 292px;border:1px solid #e4e7ec;background:#f8fafc;border-radius:16px;overflow:hidden}
    .dh-column.request-column{background:#fff8f7;border-color:#fecaca}
    .dh-col-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 13px;border-bottom:1px solid #e4e7ec;background:#fff}
    .request-column .dh-col-head{background:#fff1f0;border-bottom-color:#fecaca}
    .dh-col-title{font-weight:800;color:#101828;font-size:12px}
    .dh-count{display:inline-flex;align-items:center;justify-content:center;min-width:25px;height:22px;padding:0 7px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:9px;font-weight:800}
    .request-column .dh-count{background:#fee4e2;color:#b42318}
    .dh-col-body{padding:10px;display:flex;flex-direction:column;gap:9px;min-height:520px;max-height:calc(100vh - 240px);overflow-y:auto}
    .dh-card{display:block;text-decoration:none;border:1px solid #e4e7ec;background:#fff;border-radius:13px;padding:11px;box-shadow:0 1px 2px rgba(16,24,40,.03);transition:.16s ease}
    .dh-card:hover{border-color:#cfd4dc;box-shadow:0 4px 12px rgba(16,24,40,.07);transform:translateY(-1px)}
    .dh-request{border-left:4px solid #e11d48}
    .dh-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:7px}
    .dh-task-id{font-size:9px;font-weight:900;color:#d92d20;letter-spacing:.02em}
    .dh-type{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;border-radius:999px;padding:4px 7px}
    .dh-type.split{background:#eef4ff;color:#3538cd}
    .dh-type.swap{background:#f4ebff;color:#7f56d9}
    .dh-type.decline{background:#fff1f3;color:#c01048}
    .dh-task-name{font-size:12px;font-weight:800;line-height:1.35;color:#101828;margin-bottom:8px}
    .dh-meta{display:grid;gap:5px;color:#667085;font-size:9px}
    .dh-meta-row{display:flex;justify-content:space-between;gap:8px}
    .dh-meta-row span:first-child{color:#98a2b3}
    .dh-meta-row strong{color:#344054;text-align:right;font-weight:700}
    .dh-reason{margin-top:8px;padding-top:8px;border-top:1px solid #f2f4f7;color:#475467;font-size:9px;line-height:1.45}
    .dh-open{margin-top:9px;color:#d92d20;font-size:9px;font-weight:800}
    .dh-empty{border:1px dashed #d0d5dd;border-radius:12px;padding:24px 10px;text-align:center;color:#98a2b3;font-size:10px;background:rgba(255,255,255,.6)}
    .dh-priority{font-size:8px;font-weight:800;text-transform:uppercase;border-radius:999px;padding:3px 6px;background:#f2f4f7;color:#475467}
    .dh-due-overdue{color:#b42318!important}
    .dh-due-today{color:#b54708!important}
</style>

<div class="dh-head">
    <div>
        <h1>Designer Head Kanban</h1>
        <p>View all Designer tasks. Requests must be opened before they can be accepted or declined.</p>
    </div>
    <div class="dh-stats">
        <div class="dh-stat">
            <span>Pending Requests</span>
            <strong>{{ $pendingRequestCount }}</strong>
        </div>
        <div class="dh-stat">
            <span>Total Tasks</span>
            <strong>{{ $totalTasks }}</strong>
        </div>
        <div class="dh-readonly">View Only Kanban</div>
    </div>
</div>

<div class="dh-board-wrap">
    <div class="dh-board">
        <section class="dh-column request-column">
            <div class="dh-col-head">
                <div class="dh-col-title">Requests</div>
                <div class="dh-count">{{ $pendingRequests->count() }}</div>
            </div>
            <div class="dh-col-body">
                @forelse($pendingRequests as $request)
                    <a
                        class="dh-card dh-request"
                        href="{{ $request->task ? route('designer-head.tasks.show', $request->task) . '#request-' . $request->id : '#' }}"
                    >
                        <div class="dh-card-top">
                            <div class="dh-task-id">{{ $request->task?->task_id ?? 'TASK REMOVED' }}</div>
                            <div class="dh-type {{ $request->request_type }}">{{ ucfirst($request->request_type) }}</div>
                        </div>

                        <div class="dh-task-name">{{ $request->task?->task_name ?? 'Task unavailable' }}</div>

                        <div class="dh-meta">
                            <div class="dh-meta-row">
                                <span>Requested By</span>
                                <strong>{{ $request->requester?->name ?? '—' }}</strong>
                            </div>
                            <div class="dh-meta-row">
                                <span>Current Designer</span>
                                <strong>{{ $request->task?->designer?->name ?? '—' }}</strong>
                            </div>

                            @if($request->request_type === 'split')
                                <div class="dh-meta-row">
                                    <span>Proposed Split</span>
                                    <strong>{{ data_get($request->split_details, 'creative_count', '—') }} creative(s)</strong>
                                </div>
                            @endif

                            @if(in_array($request->request_type, ['split','swap'], true))
                                <div class="dh-meta-row">
                                    <span>Preferred Designer</span>
                                    <strong>{{ $request->targetDesigner?->name ?? 'Not specified' }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="dh-reason">{{ \Illuminate\Support\Str::limit($request->reason, 120) }}</div>
                        <div class="dh-open">Open task to review →</div>
                    </a>
                @empty
                    <div class="dh-empty">No pending Decline, Split or Swap requests.</div>
                @endforelse
            </div>
        </section>

        @foreach($columns as $status => $label)
            @php
                $columnTasks = $tasksByStatus[$status] ?? collect();
            @endphp

            <section class="dh-column">
                <div class="dh-col-head">
                    <div class="dh-col-title">{{ $label }}</div>
                    <div class="dh-count">{{ $columnTasks->count() }}</div>
                </div>

                <div class="dh-col-body">
                    @forelse($columnTasks as $task)
                        @php
                            $dueClass = '';
                            if ($task->status !== 'completed' && $task->due_at) {
                                if ($task->due_at->isPast() && ! $task->due_at->isToday()) {
                                    $dueClass = 'dh-due-overdue';
                                } elseif ($task->due_at->isToday()) {
                                    $dueClass = 'dh-due-today';
                                }
                            }
                        @endphp

                        <a class="dh-card" href="{{ route('designer-head.tasks.show', $task) }}">
                            <div class="dh-card-top">
                                <div class="dh-task-id">{{ $task->task_id }}</div>
                                <div class="dh-priority">{{ $task->priority }}</div>
                            </div>

                            <div class="dh-task-name">{{ $task->task_name }}</div>

                            <div class="dh-meta">
                                <div class="dh-meta-row">
                                    <span>Designer</span>
                                    <strong>{{ $task->designer?->name ?? 'Unassigned' }}</strong>
                                </div>
                                <div class="dh-meta-row">
                                    <span>Vertical</span>
                                    <strong>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</strong>
                                </div>
                                <div class="dh-meta-row">
                                    <span>Creatives</span>
                                    <strong>{{ $task->total_creatives }}</strong>
                                </div>
                                <div class="dh-meta-row">
                                    <span>Due</span>
                                    <strong class="{{ $dueClass }}">{{ $task->due_at?->format('d M Y') ?? '—' }}</strong>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="dh-empty">No tasks in this stage.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
