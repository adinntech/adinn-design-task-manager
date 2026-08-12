@extends('layouts.app')
@section('title','Task Monitoring')
@section('workspace-title','Task Monitoring')
@section('workspace-subtitle','Monitor and administratively manage every design task')
@section('content')


<div class="page-head">
    <div>
        <h1>Task Monitoring</h1>
        <p>Search, update or remove tasks from the complete design pipeline.</p>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" class="filter-bar" style="margin-bottom:14px">
            <input class="premium-input" name="search" value="{{ request('search') }}" placeholder="Search Task ID, task name or client">

            <select class="premium-select" name="vertical">
                <option value="">All Verticals</option>
                @foreach(['outdoor'=>'Outdoor','roadshow'=>'RoadShow','fixtures'=>'Fixtures','signage'=>'Signage','pop_offsets'=>'POP and Offsets','digital_marketing'=>'Digital Marketing','events_activations'=>'Events and Activations'] as $k=>$v)
                    <option value="{{ $k }}" @selected(request('vertical')===$k)>{{ $v }}</option>
                @endforeach
            </select>

            <select class="premium-select" name="designer_id">
                <option value="">All Designers</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected((string)request('designer_id')===(string)$designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>

            <select class="premium-select" name="status">
                <option value="">All Statuses</option>
                @foreach($statuses as $k=>$v)
                    <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                @endforeach
            </select>

            <select class="premium-select" name="priority">
                <option value="">All Priorities</option>
                @foreach(['urgent'=>'Urgent','high'=>'High','medium'=>'Medium','low'=>'Low'] as $k=>$v)
                    <option value="{{ $k }}" @selected(request('priority')===$k)>{{ $v }}</option>
                @endforeach
            </select>

            <button class="btn btn-dark">Filter</button>
        </form>

        <div class="table-wrap">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Client</th>
                        <th>BD</th>
                        <th>Designer</th>
                        <th>Vertical</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td>
                                <strong>{{ $task->task_id }}</strong>
                                <div style="margin-top:3px">{{ $task->display_task_name ?? $task->task_name }}</div>
                            </td>
                            <td>{{ $task->party_name }}</td>
                            <td>{{ $task->assigner?->name ?? '—' }}</td>
                            <td>{{ $task->designer?->name ?? '—' }}</td>
                            <td>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</td>
                            <td><span class="badge priority-{{ $task->priority }}">{{ $task->priority }}</span></td>
                            <td><span class="badge badge-dark">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span></td>
                            <td>{{ $task->due_at?->format('d M, h:i A') }}</td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                    <a class="btn btn-secondary" href="{{ route('admin.tasks.show',$task) }}">View</a>
                                    <a class="btn btn-secondary" href="{{ route('admin.tasks.edit',$task) }}">Edit</a>

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
                                        <button type="submit" class="btn" style="background:#fff1f2;color:#b42318;border:1px solid #fecdd3">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">No tasks match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">{{ $tasks->links() }}</div>
    </div>
</div>

<x-formal-confirm-dialog />
@endsection
