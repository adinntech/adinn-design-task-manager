@extends('layouts.app')
@section('title','Task Monitoring')
@section('workspace-title','Task Monitoring')
@section('workspace-subtitle','Monitor and administratively manage every design task')
@section('topbar-actions')
    <x-refresh-button :needs-refresh="$needsRefresh" />
@endsection
@section('content')

<style>
    /* Refresh lives in the top bar on this page; icon-only on phones so the bar fits. */
    @media(max-width:600px){
        /* .btn sets font-size:14px!important in adinn-premium.css */
        .topbar-actions .refresh-btn{font-size:0!important;gap:0;padding:9px 11px;min-height:38px}
        .topbar-actions .refresh-btn .refresh-btn-icon{font-size:16px}
    }
    #task-monitoring-filters{grid-template-columns:minmax(200px,1.5fr) minmax(130px,.8fr) repeat(5,minmax(120px,.7fr))}
    @media(max-width:1280px){#task-monitoring-filters{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:900px){#task-monitoring-filters{grid-template-columns:minmax(0,1fr)}}

    .tm-rel{border:1px solid var(--line,#e6e8ec);border-radius:14px;background:linear-gradient(180deg,#fff,#fbfbfc);margin-bottom:14px;overflow:hidden}
    .tm-rel-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:14px 16px}
    .tm-rel-kicker{font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#98a2b3}
    .tm-rel-title{margin-top:3px;font-size:15px;font-weight:950;color:#101828;letter-spacing:-.01em}
    .tm-rel-title{display:flex;align-items:center;flex-wrap:wrap}
    .tm-rel-arrow{color:#e30613;margin:0 8px;flex:0 0 auto}
    .tm-rel-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;padding:0 16px 14px}
    .tm-rel-card{position:relative;display:block;min-width:0;padding:10px 12px 10px 14px;border:1px solid var(--line,#e6e8ec);border-radius:11px;background:#fff;text-decoration:none;color:inherit;box-shadow:inset 3px 0 0 var(--c);transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
    .tm-rel-card span{display:block;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.05em;color:#667085;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .tm-rel-card strong{display:block;margin-top:3px;font-size:20px;font-weight:950;color:#101828;font-variant-numeric:tabular-nums}
    .tm-rel-card.is-zero strong{color:#98a2b3}
    a.tm-rel-card:hover,a.tm-rel-card:focus-visible{transform:translateY(-2px);border-color:color-mix(in srgb,var(--c) 40%,#e6e8ec);box-shadow:inset 3px 0 0 var(--c),0 8px 18px rgba(16,24,40,.08);outline:none}
    a.tm-rel-card.is-active{background:color-mix(in srgb,var(--c) 9%,#fff);border-color:var(--c)}
    .tm-rel-card.is-alert{background:#fff5f5;border-color:#fecdd3}
    .tm-rel-card.is-alert strong{color:#b42318}
    @media(max-width:1180px){.tm-rel-cards{grid-template-columns:repeat(4,minmax(0,1fr))}}
    @media(max-width:700px){.tm-rel-cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(prefers-reduced-motion:reduce){.tm-rel-card{transition:none}a.tm-rel-card:hover{transform:none}}
    .tm-rel-pairs{border-top:1px solid var(--line,#e6e8ec)}
    .tm-rel-pairs summary{cursor:pointer;list-style:none;padding:10px 16px;font-size:11px;font-weight:900;color:#344054;display:flex;justify-content:space-between}
    .tm-rel-pairs summary::-webkit-details-marker{display:none}
    .tm-rel-pairs summary:after{content:'+';color:#98a2b3;font-size:15px;line-height:1}
    .tm-rel-pairs[open] summary:after{content:'−'}
    .tm-rel-table-wrap{max-height:260px;overflow:auto;border-top:1px solid #eef0f3}
    .tm-rel-table{width:100%;min-width:640px;border-collapse:collapse}
    .tm-rel-table th{position:sticky;top:0;background:#fafbfc;text-align:left;padding:8px 12px;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.06em;color:#767f90;white-space:nowrap}
    .tm-rel-table td{padding:8px 12px;border-top:1px solid #f0f2f5;font-size:11px!important;white-space:nowrap}
    .tm-rel-table td.num{font-weight:900;font-variant-numeric:tabular-nums}
    .tm-rel-table td.num.is-alert{color:#b42318}
    .tm-rel-table tr:hover td{background:#fcfcfd}
    .tm-rel-bar{width:110px;height:6px;border-radius:999px;background:#eef0f4;overflow:hidden;display:inline-block;vertical-align:middle;margin-right:6px}
    .tm-rel-bar span{display:block;height:100%;background:#08784b;border-radius:999px}
    .tm-rel-empty{padding:0 16px 14px;font-size:11px;color:#667085}
</style>


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

            <select class="premium-select" name="bd_id">
                <option value="">All BDs</option>
                @foreach($bds as $bd)
                    <option value="{{ $bd->id }}" @selected((string)request('bd_id')===(string)$bd->id)>{{ $bd->name }}</option>
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
        @php
            $relBd = request()->filled('bd_id') ? $bds->firstWhere('id', (int) request('bd_id'))?->name : null;
            $relDesigner = request()->filled('designer_id') ? $designers->firstWhere('id', (int) request('designer_id'))?->name : null;
            $relQuery = request()->except('page');
        @endphp
        <section class="tm-rel" aria-label="BD and Designer relationship summary">
            <div class="tm-rel-head">
                <div>
                    <div class="tm-rel-kicker">Relationship summary</div>
                    <div class="tm-rel-title">{{ $relBd ?? 'All BDs' }}<svg class="tm-rel-arrow" width="26" height="16" viewBox="0 0 26 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="and"><path d="M2 6h22l-5-5"/><path d="M24 10H2l5 5"/></svg>{{ $relDesigner ?? 'All Designers' }}</div>
                </div>
            </div>

            @php
                // [label, value, accent colour, status filter (null = no filter exists), hint]
                $relCards = [
                    ['Overall Tasks', $relationship['total'], '#344054', '', 'Every task for this combination'],
                    ['Active Tasks', $relationship['active'], '#2a78d6', null, 'Not completed, swapped or cancelled'],
                    [$statuses['in_progress'], $relationship['in_progress'], '#eb6834', 'in_progress', null],
                    [$statuses['waiting_confirmation'], $relationship['waiting_confirmation'], '#4a3aa7', 'waiting_confirmation', null],
                    [$statuses['prepare_printing_file'], $relationship['prepare_printing_file'], '#00a3bf', 'prepare_printing_file', null],
                    [$statuses['completed'], $relationship['completed'], '#008300', 'completed', null],
                    ['Cancelled', $relationship['cancelled'], '#667085', null, 'Approved decline requests (reassigned to another designer)'],
                    ['Swapped Tasks', $relationship['swapped'], '#b07cf0', 'swap_tasks', null],
                    ['Split Tasks', $relationship['split'], '#1baf7a', null, 'Tasks created by an approved split'],
                    ['Overdue', $relationship['overdue'], '#b42318', null, 'Open tasks past their due date'],
                ];
            @endphp
            <div class="tm-rel-cards">
                @foreach($relCards as [$cardLabel, $cardValue, $cardColor, $cardStatus, $cardHint])
                    @php
                        $cardActive = $cardStatus !== null && $cardStatus !== '' && request('status') === $cardStatus;
                        $cardClass = 'tm-rel-card'.($cardLabel === 'Overdue' && $cardValue > 0 ? ' is-alert' : '').($cardActive ? ' is-active' : '').($cardValue === 0 ? ' is-zero' : '');
                    @endphp
                    @if($cardStatus !== null)
                        <a class="{{ $cardClass }}" style="--c:{{ $cardColor }}" href="{{ route('admin.tasks.index', array_merge($relQuery, ['status' => $cardActive || $cardStatus === '' ? null : $cardStatus])) }}" data-filter-status="{{ $cardActive ? '' : $cardStatus }}" title="{{ $cardHint ?? ($cardActive ? 'Clear status filter' : 'Show only '.$cardLabel) }}">
                            <span>{{ $cardLabel }}</span><strong>{{ $cardValue }}</strong>
                        </a>
                    @else
                        <div class="{{ $cardClass }}" style="--c:{{ $cardColor }}" title="{{ $cardHint }}">
                            <span>{{ $cardLabel }}</span><strong>{{ $cardValue }}</strong>
                        </div>
                    @endif
                @endforeach
            </div>

            @if($relationship['total'] === 0)
                <div class="tm-rel-empty">No tasks for this combination.</div>
            @endif

            @if($relationship['pairs']->count() > 1)
                <details class="tm-rel-pairs" open>
                    <summary>BD × Designer breakdown ({{ $relationship['pairs']->count() }} pairs)</summary>
                    <div class="tm-rel-table-wrap">
                        <table class="tm-rel-table">
                            <thead><tr><th>BD</th><th>Designer</th><th>Total</th><th>Completed</th><th>Active</th><th>Overdue</th><th>Completion</th></tr></thead>
                            <tbody>
                                @foreach($relationship['pairs'] as $pair)
                                    @php $pct = $pair['total'] ? (int) round($pair['completed'] / $pair['total'] * 100) : 0; @endphp
                                    <tr>
                                        <td><a class="file-link" href="{{ route('admin.tasks.index', array_merge($relQuery, ['bd_id' => $pair['bd_id'], 'designer_id' => $pair['designer_id']])) }}" data-filter-bd="{{ $pair['bd_id'] }}" data-filter-designer="{{ $pair['designer_id'] }}" title="Show only {{ $pair['bd'] }} × {{ $pair['designer'] }}">{{ $pair['bd'] }}</a></td>
                                        <td>{{ $pair['designer'] }}</td>
                                        <td class="num">{{ $pair['total'] }}</td>
                                        <td class="num">{{ $pair['completed'] }}</td>
                                        <td class="num">{{ $pair['active'] }}</td>
                                        <td class="num{{ $pair['overdue'] > 0 ? ' is-alert' : '' }}">{{ $pair['overdue'] }}</td>
                                        <td><span class="tm-rel-bar"><span style="width:{{ $pct }}%"></span></span>{{ $pct }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </section>

        {{-- Today's submitted progress / designer activity logs — loaded via TaskActivityController with the BD / Designer filters above. --}}
        <section class="tm-act" id="tm-activity" aria-label="Designer progress and activity"
                 data-url="{{ route('admin.tasks.activity', array_filter(['bd_id' => request('bd_id'), 'designer_id' => request('designer_id')])) }}">
            <div class="empty-state" style="padding:18px">Loading progress details…</div>
        </section>

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
                if (window.tmActivityLoad) window.tmActivityLoad(); // re-load the tabs for the new BD / Designer filters
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

    // Relationship summary shortcuts (status chips, BD × Designer rows) set the
    // matching filters and refresh in place; their hrefs still work without JS.
    results.addEventListener('click', function (event) {
        var link = event.target.closest('[data-filter-status], [data-filter-bd]');
        if (!link || !results.contains(link)) return;
        event.preventDefault();

        if (link.hasAttribute('data-filter-status')) {
            form.elements.status.value = link.getAttribute('data-filter-status');
        } else {
            form.elements.bd_id.value = link.getAttribute('data-filter-bd');
            form.elements.designer_id.value = link.getAttribute('data-filter-designer');
        }
        refresh();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        refresh();
    });
})();

// Progress / activity tabs below the BD × Designer breakdown. Server-rendered
// fragments from admin.tasks.activity; tab, date, task and type choices are
// kept here so they survive the page's live filter refresh.
(function () {
    var state = { tab: 'progress', date: '', task: '', type: '' };
    var requestSeq = 0;
    var taskTimer = null;
    var refocusTask = false;

    function root() { return document.getElementById('tm-activity'); }

    function urlFor() {
        var el = root();
        var url = new URL(el.dataset.url, window.location.origin);
        Object.keys(state).forEach(function (key) {
            if (state[key]) url.searchParams.set(key, state[key]); else url.searchParams.delete(key);
        });
        if (state.tab !== 'logs') url.searchParams.delete('type');
        return url.toString();
    }

    function load(url) {
        var el = root();
        if (!el) return;
        var seq = ++requestSeq;
        el.setAttribute('aria-busy', 'true');
        el.style.opacity = '.6';
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(function (html) {
                if (seq !== requestSeq || !root()) return;
                root().innerHTML = html;
                if (window.tmDailyInit) window.tmDailyInit();
                if (refocusTask) {
                    refocusTask = false;
                    var input = root().querySelector('[data-tm-filter="task"]');
                    if (input) { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
                }
            })
            .catch(function () {
                if (seq !== requestSeq || !root()) return;
                root().dataset.retryUrl = url;
                root().innerHTML = '<div class="empty-state" style="padding:18px">Could not load progress / activity details. <button type="button" class="btn btn-secondary" data-tm-retry>Retry</button></div>';
            })
            .finally(function () {
                if (seq !== requestSeq || !root()) return;
                root().removeAttribute('aria-busy');
                root().style.opacity = '';
            });
    }

    window.tmActivityLoad = function () { if (root()) load(urlFor()); };

    document.addEventListener('click', function (event) {
        var el = root();
        if (!el || !el.contains(event.target)) return;

        var tab = event.target.closest('[data-tm-tab]');
        if (tab) { state.tab = tab.getAttribute('data-tm-tab'); window.tmActivityLoad(); return; }

        var retry = event.target.closest('[data-tm-retry]');
        if (retry) { load(el.dataset.retryUrl || urlFor()); return; }

        var pageLink = event.target.closest('[data-tm-pagination] a[href]');
        if (pageLink) { event.preventDefault(); load(pageLink.href); }
    });

    document.addEventListener('change', function (event) {
        var field = event.target.closest('#tm-activity [data-tm-filter]');
        if (!field || field.getAttribute('data-tm-filter') === 'task') return;
        state[field.getAttribute('data-tm-filter')] = field.value;
        window.tmActivityLoad();
    });

    document.addEventListener('input', function (event) {
        var field = event.target.closest('#tm-activity [data-tm-filter="task"]');
        if (!field) return;
        clearTimeout(taskTimer);
        taskTimer = setTimeout(function () { state.task = field.value.trim(); refocusTask = true; window.tmActivityLoad(); }, 400);
    });

    window.tmActivityLoad();
})();

// "Export Report" tab: show (and require) the From / To dates only for Custom Period.
document.addEventListener('change', function (event) {
    var period = event.target.closest('#tm-activity [data-tm-export-period]');
    if (!period) return;
    var custom = period.value === 'custom';
    period.form.querySelectorAll('[data-tm-export-date]').forEach(function (input) {
        input.hidden = !custom;
        input.disabled = !custom;
        input.required = custom;
    });
});

// "Daily Report" tab: calendar + Excel download. The tab's HTML is replaced on
// every tab load, so tmDailyInit() runs after each load and listeners are delegated.
(function () {
    var exportUrl = @json(route('admin.tasks.progress-excel'));
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var DOW = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    // Kept across tab reloads; dates are 'YYYY-MM-DD' strings in IST, so they compare as text.
    var cal = { period: false, from: '', to: '', pickingEnd: false, y: 0, m: 0, preset: 'today' };

    function root() { return document.querySelector('#tm-activity [data-xl-root]'); }
    function $(sel) { return root().querySelector(sel); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function key(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

    // Dates in IST regardless of the viewer's clock zone.
    function istDate(offsetDays) {
        var p = {};
        new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' })
            .formatToParts(new Date(Date.now() + offsetDays * 86400000)).forEach(function (x) { p[x.type] = x.value; });
        return p.year + '-' + p.month + '-' + p.day;
    }
    function pretty(k) {
        var d = k.split('-');
        return Number(d[2]) + ' ' + MONTHS[Number(d[1]) - 1].slice(0, 3) + ' ' + d[0];
    }

    function markPreset(name) {
        cal.preset = name;
        root().querySelectorAll('[data-xl-preset]').forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-xl-preset') === name); });
    }

    function select(from, to, period) {
        cal.from = from;
        cal.to = to;
        cal.period = period;
        cal.pickingEnd = false;
        cal.y = Number(to.slice(0, 4));
        cal.m = Number(to.slice(5, 7)) - 1;
        render();
    }

    function render() {
        var today = istDate(0);
        var first = new Date(Date.UTC(cal.y, cal.m, 1)).getUTCDay();
        var days = new Date(Date.UTC(cal.y, cal.m + 1, 0)).getUTCDate();
        var ranged = cal.period && cal.from && cal.to && cal.from !== cal.to;
        var html = DOW.map(function (d) { return '<div class="tm-xl-cal-dow">' + d.toUpperCase() + '</div>'; }).join('');
        for (var i = 0; i < first; i++) html += '<div class="tm-xl-cal-cell"></div>';
        for (var d = 1; d <= days; d++) {
            var k = key(cal.y, cal.m, d);
            var cell = ['tm-xl-cal-cell'];
            if (ranged && k > cal.from && k < cal.to) cell.push('in-range');
            if (ranged && k === cal.from) cell.push('range-start');
            if (ranged && k === cal.to) cell.push('range-end');
            var btn = ['tm-xl-cal-day'];
            if (k === cal.from || k === cal.to) btn.push('is-selected');
            if (k === today) btn.push('is-today');
            html += '<div class="' + cell.join(' ') + '"><button type="button" class="' + btn.join(' ') + '" data-xl-day="' + k + '"'
                + (k > today ? ' disabled' : '') + ' aria-label="' + pretty(k) + '"'
                + (k === cal.from || k === cal.to ? ' aria-pressed="true"' : '') + '>' + d + '</button></div>';
        }
        $('[data-xl-cal-grid]').innerHTML = html;
        $('[data-xl-cal-title]').textContent = MONTHS[cal.m] + ' ' + cal.y;
        $('[data-xl-nav="1"]').disabled = key(cal.y, cal.m, 1) >= today.slice(0, 8) + '01';

        $('#tm-xl-from').value = cal.from;
        $('#tm-xl-to').value = cal.period ? cal.to : cal.from;
        $('[data-xl-picked]').innerHTML = !cal.period
            ? 'Report for <strong>' + pretty(cal.from) + '</strong>'
            : (cal.pickingEnd
                ? 'From <strong>' + pretty(cal.from) + '</strong> — now click the end date'
                : 'From <strong>' + pretty(cal.from) + '</strong> to <strong>' + pretty(cal.to) + '</strong>');

        // Same rule as DailyWorkReportService::layout().
        var days = cal.period && !cal.pickingEnd
            ? Math.round((Date.parse(cal.to + 'T00:00:00Z') - Date.parse(cal.from + 'T00:00:00Z')) / 86400000) + 1
            : 1;
        $('[data-xl-layout]').textContent = cal.period && cal.pickingEnd ? '' : 'Excel: ' + (
            days <= 1 ? 'designer-wise numbers for the day.'
                : days <= 7 ? 'one row per date — click + to see designers.'
                    : days <= 31 ? 'one row per week — expand to dates, then designers.'
                        : 'one row per month — expand to weeks, dates, then designers.');
    }

    function setPreset(name) {
        var today = istDate(0);
        markPreset(name);
        if (name === 'today') select(today, today, false);
        if (name === 'yesterday') select(istDate(-1), istDate(-1), false);
        if (name === 'week') {
            var weekday = new Date(today + 'T00:00:00Z').getUTCDay(); // 0 = Sunday
            select(istDate(-((weekday + 6) % 7)), today, true);
        }
        if (name === 'month') select(today.slice(0, 8) + '01', today, true);
        if (name === 'year') select(today.slice(0, 5) + '01-01', today, true);
        if (name === 'custom') {
            // Keep the chosen day as the start and wait for the end date.
            cal.period = true;
            cal.to = cal.from;
            cal.pickingEnd = true;
            render();
        }
        showMsg('', '');
    }

    function pickDay(k) {
        var today = istDate(0);
        if (!cal.period) {
            select(k, k, false);
            markPreset(k === today ? 'today' : (k === istDate(-1) ? 'yesterday' : ''));
        } else if (cal.pickingEnd) {
            var from = k < cal.from ? k : cal.from;
            cal.to = k < cal.from ? cal.from : k;
            cal.from = from;
            cal.pickingEnd = false;
            render();
            markPreset('custom');
        } else {
            cal.from = k; cal.to = k; cal.pickingEnd = true;
            render();
            markPreset('custom');
        }
        showMsg('', '');
    }

    function pageFilters() {
        var form = document.getElementById('task-monitoring-filters');
        var pick = function (name) {
            var el = form && form.elements[name];
            return el && el.value ? { id: el.value, label: el.options ? el.options[el.selectedIndex].text : el.value } : null;
        };
        return { bd: pick('bd_id'), designer: pick('designer_id') };
    }

    function showMsg(kind, text) {
        var el = $('[data-xl-msg]');
        el.className = 'tm-xl-msg' + (kind ? ' is-' + kind : '');
        el.textContent = text;
        el.hidden = !text;
    }

    // Called after every tab load; draws the calendar if the Daily Report tab is showing.
    window.tmDailyInit = function () {
        if (!root()) return;
        if (!cal.from || cal.to > istDate(0)) {
            select(istDate(0), istDate(0), false);
            cal.preset = 'today';
        } else {
            render();
        }
        markPreset(cal.preset);
        var f = pageFilters();
        var parts = [];
        if (f.bd) parts.push('BD: ' + f.bd.label);
        if (f.designer) parts.push('Designer: ' + f.designer.label);
        $('[data-xl-filters]').textContent = parts.length
            ? 'Filters above applied — ' + parts.join(' · ')
            : 'No BD / Designer filter set above — every designer is included.';
    };

    function download() {
        if (cal.period && cal.pickingEnd) return showMsg('error', 'Click the end date of the period first.');
        var from = $('#tm-xl-from').value;
        var to = $('#tm-xl-to').value;
        if (!from || !to) return showMsg('error', 'Choose a date.');

        var f = pageFilters();
        var url = new URL(exportUrl, window.location.origin);
        url.searchParams.set('from', from);
        url.searchParams.set('to', to);
        if (f.bd) url.searchParams.set('bd_id', f.bd.id);
        if (f.designer) url.searchParams.set('designer_id', f.designer.id);

        var btn = $('[data-xl-download]');
        btn.disabled = true;
        btn.textContent = 'Preparing…';
        showMsg('', '');

        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r.ok) {
                    var name = (/filename="?([^";]+)"?/.exec(r.headers.get('Content-Disposition') || '') || [])[1] || 'Daily_Report.xlsx';
                    return r.blob().then(function (blob) {
                        var a = document.createElement('a');
                        a.href = URL.createObjectURL(blob);
                        a.download = name;
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
                        if (root()) showMsg('ok', 'Downloaded ' + name);
                    });
                }
                return r.json().catch(function () { return {}; }).then(function (data) {
                    var errors = data.errors ? Object.values(data.errors).flat() : [];
                    throw new Error(errors[0] || data.message || 'Download failed. Please try again.');
                });
            })
            .catch(function (e) { if (root()) showMsg('error', e.message); })
            .finally(function () { if (!root() || !document.contains(btn)) return; btn.disabled = false; btn.textContent = 'Download Excel'; });
    }

    document.addEventListener('click', function (e) {
        var r = root();
        if (!r || !r.contains(e.target)) return;
        var preset = e.target.closest('[data-xl-preset]');
        if (preset) { setPreset(preset.getAttribute('data-xl-preset')); return; }
        var nav = e.target.closest('[data-xl-nav]');
        if (nav && !nav.disabled) {
            var next = new Date(Date.UTC(cal.y, cal.m + Number(nav.getAttribute('data-xl-nav')), 1));
            cal.y = next.getUTCFullYear(); cal.m = next.getUTCMonth();
            render();
            return;
        }
        var day = e.target.closest('[data-xl-day]');
        if (day && !day.disabled) { pickDay(day.getAttribute('data-xl-day')); return; }
        if (e.target.closest('[data-xl-download]')) download();
    });
})();
</script>
@endsection
