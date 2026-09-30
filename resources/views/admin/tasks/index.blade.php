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
    <div class="page-actions">
        <x-refresh-button :needs-refresh="$needsRefresh" />
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" id="task-monitoring-filters" class="filter-bar" style="margin-bottom:14px">
            <input class="premium-input" name="search" value="{{ request('search') }}" placeholder="Search Task ID, Zoho Project Number, task name or client" autocomplete="off">

            <input class="premium-input" name="project_number" value="{{ request('project_number') }}" placeholder="Zoho Project Number" autocomplete="off">

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
        </form>

        <div id="task-monitoring-results" style="transition:opacity .15s ease">
        <div class="table-wrap">
            <table class="premium-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project Number</th>
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
                            <td>{{ $task->zoho_project_number ?: '-' }}</td>
                            <td>{{ $task->party_name }}</td>
                            <td>{{ $task->assigner?->name ?? '—' }}</td>
                            <td>{{ $task->designer?->name ?? '—' }}</td>
                            <td>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</td>
                            <td><span class="badge priority-{{ $task->priority }}">{{ $task->priority }}</span></td>
                            <td><span class="badge badge-dark">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span> @if(data_get($task->requirements, '_split_from_task_id'))<span class="badge badge-success">Split Task</span>@endif</td>
                            <td>{{ $task->due_at?->format('d M Y · h:i A') }}</td>
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
                        <tr><td colspan="10" class="empty-state">No tasks match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">{{ $tasks->links() }}</div>
        </div>
    </div>
</div>

<x-formal-confirm-dialog />

<script>
// Real-time filtering: re-fetches this same page with the current filters and
// swaps only #task-monitoring-results. Filtering itself stays server-side in
// TaskMonitoringController@index; the URL is kept in sync so Refresh, Back and
// pagination links keep the active filters.
(function () {
    var form = document.getElementById('task-monitoring-filters');
    var results = document.getElementById('task-monitoring-results');
    if (!form || !results) return;

    var debounceTimer = null;
    var controller = null;
    var requestSeq = 0;

    function buildUrl() {
        var params = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            var trimmed = String(value).trim();
            if (trimmed !== '') params.append(key, trimmed);
        });
        var query = params.toString();
        return window.location.pathname + (query ? '?' + query : '');
    }

    function refresh() {
        clearTimeout(debounceTimer);
        var url = buildUrl();
        var seq = ++requestSeq;

        if (controller) controller.abort();
        controller = new AbortController();

        results.style.opacity = '.55';
        results.setAttribute('aria-busy', 'true');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                if (seq !== requestSeq) return; // a newer request superseded this one
                var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('task-monitoring-results');
                if (!fresh) throw new Error('Results container missing');
                results.innerHTML = fresh.innerHTML;
                history.replaceState(history.state, '', url);
                results.style.opacity = '';
                results.removeAttribute('aria-busy');
            })
            .catch(function (error) {
                if (error.name === 'AbortError' || seq !== requestSeq) return;
                window.location = url;
            });
    }

    form.addEventListener('input', function (event) {
        if (event.target.tagName !== 'INPUT') return;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 350);
    });

    form.addEventListener('change', function (event) {
        if (event.target.tagName === 'SELECT') refresh();
    });

    // With several text inputs and no submit button, the browser never submits
    // implicitly on Enter, so handle it explicitly (and any other submit path).
    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
            event.preventDefault();
            refresh();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        refresh();
    });
})();
</script>
@endsection
