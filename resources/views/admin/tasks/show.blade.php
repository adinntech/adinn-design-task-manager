@extends('layouts.app')
@section('title',$task->task_id)
@section('workspace-title','Task Detail')
@section('workspace-subtitle','Administrative task view and control')
@section('content')


<style>
    .admin-edit-history{margin-top:14px}
    .admin-edit-batch{border:1px solid #e8eaee;border-radius:11px;overflow:hidden;margin-bottom:9px}
    .admin-edit-batch-head{padding:9px 11px;background:#f8f9fb;font-size:10px;font-weight:800;color:#475467}
    .admin-edit-row{padding:9px 11px;border-top:1px solid #eef0f3}
    .admin-edit-field{font-size:9px;font-weight:900;text-transform:uppercase;color:#667085;margin-bottom:5px}
    .admin-edit-values{display:flex;gap:7px;align-items:center;flex-wrap:wrap;font-size:10px}
    .admin-old{padding:5px 7px;border-radius:6px;background:#fff1f1;color:#b42318}
    .admin-new{padding:5px 7px;border-radius:6px;background:#ecfdf3;color:#067647;font-weight:700}
</style>

<div class="page-head">
    <div>
        <h1>{{ $task->display_task_name ?? $task->task_name }}</h1>
        <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</p>
    </div>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a class="btn btn-secondary" href="{{ route('admin.tasks.index') }}">Back to Tasks</a>
        <a class="btn btn-secondary" href="{{ route('admin.tasks.edit',$task) }}">Edit Task</a>

        <form
                                        method="POST"
                                        action="{{ route('admin.tasks.destroy',$task) }}"
                                        data-formal-confirm
                                        data-confirm-title="Delete Task?"
                                        data-confirm-message="Are you sure you want to delete {{ $task->task_id }} — {{ $task->display_task_name ?? $task->task_name }}?"
                                        data-confirm-label="Yes, Delete"
                                        data-processing-label="Deleting..."
                                        data-confirm-tone="danger"
                                    >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn" style="background:#fff1f2;color:#b42318;border:1px solid #fecdd3">Delete Task</button>
        </form>
    </div>
</div>

<div class="detail-grid">
    <div>
        <section class="panel">
            <div class="panel-header"><div class="panel-title">Task Information</div></div>
            <div class="panel-body">
                <div class="info-grid">
                    @foreach([
                        'Client / Agency'=>ucfirst($task->party_type).' · '.$task->party_name,
                        'Contact'=>$task->contact_person.' · '.$task->mobile_number,
                        'Vertical'=>ucwords(str_replace('_',' ',$task->vertical)),
                        'Task Nature'=>ucwords(str_replace('_',' ',$task->task_nature)),
                        'Priority'=>ucfirst($task->priority),
                        'Designer'=>$task->designer?->name ?? '—',
                        'Assigned By'=>$task->assigner?->name ?? '—',
                        'Due Date'=>$task->due_at?->format('d M Y, h:i A'),
                        'Total Creatives'=>$task->total_creatives,
                        'Assigned At'=>$task->assigned_at?->format('d M Y, h:i A')
                    ] as $k=>$v)
                        <div class="info-item"><span>{{ $k }}</span><strong>{{ $v }}</strong></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel" style="margin-top:14px">
            <div class="panel-header"><div class="panel-title">Requirement Details</div></div>
            <div class="panel-body">
                <div class="requirement-list">
                    @forelse(($task->requirements ?? []) as $key=>$value)
                        @continue(str_starts_with((string)$key,'_'))
                        <div class="requirement-row">
                            <div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div>
                            <div>
                                @if(is_array($value))
                                    @if(isset($value['square_feet']))
                                        {{ $value['width'] ?? '' }} × {{ $value['height'] ?? '' }} feet = {{ $value['square_feet'] }} sq.ft
                                    @else
                                        @foreach($value as $item)
                                            @if(is_string($item) && str_contains($item,'/'))
                                                <div><a class="file-link" href="{{ Storage::disk('spaces')->url($item) }}" target="_blank">{{ basename($item) }}</a></div>
                                            @else
                                                <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                            @endif
                                        @endforeach
                                    @endif
                                @elseif(is_string($value) && str_contains($value,'/'))
                                    <a class="file-link" href="{{ Storage::disk('spaces')->url($value) }}" target="_blank">{{ basename($value) }}</a>
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
        </section>

        @if($editHistory->isNotEmpty())
            <section class="panel admin-edit-history">
                <div class="panel-header"><div class="panel-title">Edit History</div></div>
                <div class="panel-body">
                    @foreach($editHistory as $changes)
                        @php
                            $first = $changes->first();
                        @endphp
                        <div class="admin-edit-batch">
                            <div class="admin-edit-batch-head">
                                {{ $first->editor?->name ?? 'User' }} · {{ $first->created_at?->format('d M Y, h:i A') }}
                            </div>
                            @foreach($changes as $change)
                                <div class="admin-edit-row">
                                    <div class="admin-edit-field">{{ $change->field_name }}</div>
                                    <div class="admin-edit-values">
                                        <span class="admin-old"><del>{{ $change->old_value ?: '—' }}</del></span>
                                        <span>→</span>
                                        <span class="admin-new">{{ $change->new_value ?: '—' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <aside>
        <section class="panel">
            <div class="panel-header"><div class="panel-title">Pipeline History</div></div>
            <div class="panel-body">
                <div class="activity-list">
                    @forelse($history as $event)
                        <div class="activity-item">
                            <strong>{{ $event->from_status ? ($statuses[$event->from_status] ?? $event->from_status).' → ' : '' }}{{ $statuses[$event->to_status] ?? $event->to_status }}</strong>
                            <p>{{ $event->changedBy?->name ?? 'User' }} · {{ $event->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                    @empty
                        <div class="empty-state">No pipeline activity.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="panel" style="margin-top:14px">
            <div class="panel-header"><div class="panel-title">Comments</div></div>
            <div class="panel-body">
                <div class="activity-list">
                    @forelse($comments as $comment)
                        <div class="activity-item">
                            <strong>{{ $comment->user?->name ?? 'User' }}</strong>
                            <p style="white-space:pre-wrap">{{ $comment->comment }}</p>
                            <p>{{ $comment->created_at->format('d M Y, h:i A') }}</p>
                            @foreach($comment->attachments as $attachment)
                                <div><a class="file-link" href="{{ $attachment->url }}" target="_blank">{{ $attachment->original_name }}</a></div>
                            @endforeach
                        </div>
                    @empty
                        <div class="empty-state">No comments yet.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </aside>
</div>

<x-formal-confirm-dialog />
@endsection
