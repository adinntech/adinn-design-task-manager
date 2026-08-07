@extends('layouts.app')

@section('title','Assigned Tasks')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Assigned Tasks</h1>
        <p class="page-subtitle">Track every design task assigned by you and its current production stage.</p>
    </div>

    <a class="btn btn-primary" href="{{ route('bd.tasks.create') }}">Create New Task</a>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
            <input class="premium-input"
                   style="max-width:420px;"
                   type="search"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search Task ID, task name or client">
            <button class="btn btn-dark" type="submit">Search</button>
            @if(request('search'))
                <a class="btn btn-secondary" href="{{ route('bd.tasks.index') }}">Clear</a>
            @endif
        </form>

        <div class="table-wrap">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Client</th>
                        <th>Designer</th>
                        <th>Vertical</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td>
                            <a href="{{ route('bd.tasks.show',$task) }}" style="text-decoration:none;">
                                <strong>{{ $task->task_name }}</strong>
                                <div style="color:#667085;font-size:12px;margin-top:3px;">{{ $task->task_id }}</div>
                            </a>
                        </td>
                        <td>{{ $task->party_name }}</td>
                        <td>{{ $task->designer?->name ?? 'Not assigned' }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</td>
                        <td><span class="badge badge-warning">{{ $task->priority }}</span></td>
                        <td><span class="badge badge-red">{{ ucwords(str_replace('_',' ',$task->status)) }}</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($task->due_at)->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state">No assigned tasks found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $tasks->links() }}</div>
    </div>
</div>
@endsection
