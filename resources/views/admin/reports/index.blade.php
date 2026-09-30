@extends('layouts.app')
@section('title','Overall Reports')
@section('workspace-title','Overall Reports')
@section('workspace-subtitle','Filter by BD, Designer and period, then export the full task report to Excel')
@section('content')

<style>
    #reportExportBtn.is-loading{pointer-events:none;opacity:.65}

    .rpt-split{display:grid;grid-template-columns:minmax(250px,290px) minmax(0,1fr);gap:14px;margin-top:14px;align-items:start}
    .rpt-split[hidden]{display:none}
    .rpt-card{min-width:0}
    .rpt-status-card{position:sticky;top:96px}

    /* Status overview */
    .rpt-status-list{list-style:none;margin:0;padding:0;display:grid;gap:4px}
    .rpt-status-list[hidden]{display:none}
    .rpt-status-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;column-gap:9px;row-gap:5px;padding:7px 8px;border-radius:9px;font-size:11px;font-weight:750;color:#344054}
    .rpt-status-item:hover{background:#f8f9fb}
    .rpt-status-item.is-zero{color:#98a2b3;padding:5px 8px}
    .rpt-status-dot{width:9px;height:9px;border-radius:3px;background:var(--c,#d0d5dd)}
    .rpt-status-label{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .rpt-status-count{font-size:13px;font-weight:950;color:#101828;font-variant-numeric:tabular-nums}
    .rpt-status-item.is-zero .rpt-status-count{font-size:11px;color:#98a2b3}
    .rpt-status-bar{grid-column:2/-1;height:4px;border-radius:999px;background:#f0f2f5;overflow:hidden}
    .rpt-status-bar span{display:block;height:100%;border-radius:999px;background:var(--c)}
    .rpt-toggle{margin:6px 0 2px;border:0;background:none;padding:4px 8px;font:inherit;font-size:10px;font-weight:850;color:#e30613;cursor:pointer;border-radius:7px}
    .rpt-toggle:hover{background:#fff0f1}
    .rpt-toggle[hidden]{display:none}
    .rpt-status-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:10px;padding:11px 8px 0;border-top:1px solid var(--line,#e6e8ec);font-size:11px;font-weight:850;color:#475467}
    .rpt-status-total strong{font-size:18px;font-weight:950;color:#101828;letter-spacing:-.02em}

    /* Report preview header + stat chips (double as the legend) */
    .rpt-preview-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 16px;border-bottom:1px solid var(--line,#e6e8ec)}
    .rpt-chips{display:flex;flex-wrap:wrap;gap:6px}
    .rpt-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;border:1px solid var(--line,#e6e8ec);background:#fff;font-size:10px;font-weight:750;color:#475467;cursor:default}
    .rpt-chip b{font-size:12px;font-weight:950;color:#101828;font-variant-numeric:tabular-nums}
    .rpt-chip i{width:10px;height:10px;border-radius:3px;border:1px solid rgba(0,0,0,.1)}
    .rpt-chip.is-late i{background:#ffc7ce}.rpt-chip.is-done i{background:#c6efce}.rpt-chip.is-carry i{background:#ffeb9c}

    /* Preview table */
    .rpt-table-wrap{max-height:560px;overflow:auto;border-radius:0 0 16px 16px}
    .rpt-table-wrap[hidden]{display:none}
    .rpt-table{border-collapse:separate;border-spacing:0;min-width:2300px;width:100%;font-size:11px;line-height:1.35;color:#15171c}
    .rpt-table th{position:sticky;top:0;z-index:2;background:#e30613;color:#fff;font-size:10px;font-weight:850;text-align:left;padding:9px 10px;border-right:1px solid #c80511;white-space:nowrap}
    /* adinn-premium.css sets td{font-size:14px!important}; the preview needs its compact size. */
    .rpt-table td{font-size:11px!important;line-height:1.35;padding:6px 10px;border-right:1px solid #eef0f3;border-bottom:1px solid #eef0f3;vertical-align:top;white-space:nowrap;background:#fff}
    .rpt-table tbody tr:nth-child(even) td{background:#fafbfc}
    .rpt-table tbody tr:not(.is-late):not(.is-carry):hover td{background:#f3f6fc}
    .rpt-table th:nth-child(1),.rpt-table td:nth-child(1){position:sticky;left:0;width:46px;min-width:46px;max-width:46px;text-align:center}
    .rpt-table th:nth-child(2),.rpt-table td:nth-child(2){position:sticky;left:46px;box-shadow:inset -1px 0 0 #e4e7ec,4px 0 6px -4px rgba(16,24,40,.18);font-weight:850}
    .rpt-table td:nth-child(1),.rpt-table td:nth-child(2){z-index:1}
    .rpt-table th:nth-child(1),.rpt-table th:nth-child(2){z-index:3}
    .rpt-table td:nth-child(4){white-space:normal;min-width:160px;max-width:240px}
    .rpt-table td:nth-child(n+14){min-width:170px;white-space:normal}
    .rpt-table td:nth-child(18){min-width:280px}
    .rpt-clamp{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;line-clamp:3;max-height:calc(1.35em * 3);overflow:hidden;white-space:pre-line}
    .rpt-clamp.is-2{-webkit-line-clamp:2;line-clamp:2;max-height:calc(1.35em * 2);white-space:normal}
    .rpt-pill{display:inline-block;padding:2px 8px;border-radius:999px;background:rgba(16,24,40,.07);font-size:10px;font-weight:800}
    .rpt-table tbody tr.is-carry td{background:#ffeb9c;color:#9c6500}
    .rpt-table tbody tr.is-late td{background:#ffc7ce;color:#9c0006}
    .rpt-table td.is-done{background:#c6efce!important;color:#006100!important;font-weight:850}
    .rpt-table td.is-done .rpt-pill{background:rgba(0,97,0,.1)}

    /* Empty + loading states */
    .rpt-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:44px 16px;text-align:center;font-size:12px;font-weight:750;color:#667085}
    .rpt-empty[hidden]{display:none}
    .rpt-empty svg{color:#c0c6d0}
    .rpt-skel{display:none}
    .rpt-split.is-loading .rpt-skel{display:grid;gap:8px}
    .rpt-split.is-loading .rpt-status-body,.rpt-split.is-loading .rpt-preview-body{display:none}
    .rpt-skel-bar{height:14px;border-radius:7px;background:linear-gradient(90deg,#eef0f3 25%,#f7f8fa 50%,#eef0f3 75%);background-size:200% 100%;animation:rpt-shimmer 1.2s ease-in-out infinite}
    .rpt-skel-bar.is-tall{height:34px}
    .rpt-skel.is-table{padding:14px 16px}
    @keyframes rpt-shimmer{from{background-position:200% 0}to{background-position:-200% 0}}

    @media(max-width:900px){.rpt-split{grid-template-columns:minmax(0,1fr)}.rpt-status-card{position:static}}
    @media(prefers-reduced-motion:reduce){.rpt-skel-bar{animation:none}}
</style>

@php
    $period = request('period', 'current_month');
    $bdId = (string) request('bd_id', '');
    $designerId = (string) request('designer_id', '');
    $dateFrom = request('date_from', '');
    $dateTo = request('date_to', '');
@endphp

<div class="page-head">
    <div><h1>Overall Reports</h1><p>Export task details across every BD and Designer, scoped to the selected period.</p></div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filter-bar" style="margin-bottom:0">
            <select class="premium-select" id="reportBd">
                <option value="">All BD's ({{ $bds->count() }})</option>
                @foreach($bds as $bd)
                    <option value="{{ $bd->id }}" @selected($bdId === (string) $bd->id)>{{ $bd->name }}</option>
                @endforeach
            </select>

            <select class="premium-select" id="reportDesigner">
                <option value="">All Designers ({{ $designers->count() }})</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected($designerId === (string) $designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>

            <select class="premium-select" id="reportPeriod">
                <option value="current_month" @selected($period === 'current_month')>Current Month</option>
                <option value="last_month" @selected($period === 'last_month')>Last Month</option>
                <option value="custom" @selected($period === 'custom')>Custom Period</option>
            </select>

            <input class="premium-input" type="date" id="reportDateFrom" value="{{ $dateFrom }}" style="{{ $period === 'custom' ? '' : 'display:none' }}">
            <input class="premium-input" type="date" id="reportDateTo" value="{{ $dateTo }}" style="{{ $period === 'custom' ? '' : 'display:none' }}">

            <button type="button" class="btn btn-secondary" id="reportClearBtn">Clear Filters</button>

            <a class="btn btn-primary" id="reportExportBtn" href="{{ route('admin.reports.export', [
                'bd_id' => $bdId, 'designer_id' => $designerId, 'period' => $period,
                'date_from' => $dateFrom, 'date_to' => $dateTo,
            ]) }}">Export Report</a>
        </div>

        {{-- Kept (hidden) because the existing reload() still writes the match count here. --}}
        <p class="muted" id="reportMatchCount" hidden></p>
        <p class="error" id="reportExportError" style="display:none;margin-top:12px"></p>
    </div>
</div>

{{-- Split view for the selected BD × Designer (All × All = overall): status counts (left) + export preview (right). Filled by the script below. --}}
<div class="rpt-split" id="reportSplit" hidden>
    <section class="panel rpt-card rpt-status-card">
        <div class="panel-header"><div><div class="panel-title">Status overview</div><div class="metric-note">Tasks by current status</div></div></div>
        <div class="panel-body">
            <div class="rpt-skel" aria-hidden="true">@for($i = 0; $i < 5; $i++)<div class="rpt-skel-bar is-tall"></div>@endfor</div>
            <div class="rpt-status-body">
                <ul class="rpt-status-list" id="reportStatusList"></ul>
                <button type="button" class="rpt-toggle" id="reportStatusToggle" aria-expanded="false" aria-controls="reportStatusZero" hidden></button>
                <ul class="rpt-status-list" id="reportStatusZero" hidden></ul>
                <div class="rpt-status-total"><span>Total tasks</span><strong id="reportStatusTotal">0</strong></div>
            </div>
        </div>
    </section>
    <section class="panel rpt-card">
        <div class="rpt-preview-head">
            <div><div class="panel-title">Report preview</div><div class="metric-note" id="reportSplitScope"></div></div>
            <div class="rpt-chips">
                <span class="rpt-chip" title="Rows in the exported Tasks sheet"><b id="reportChipRows">0</b>Rows</span>
                <span class="rpt-chip is-late" title="Still open and past the due date — red row in the sheet"><i></i><b id="reportChipOverdue">0</b>Overdue</span>
                <span class="rpt-chip is-done" title="Completed after the due date — red row with a green Status cell"><i></i><b id="reportChipLate">0</b>Completed late</span>
                <span class="rpt-chip is-carry" title="The exported sheet includes tasks from the selected period, plus any still-open task carried forward from an earlier month (Current Month only) — those rows are highlighted in amber."><i></i><b id="reportChipCarry">0</b>Carried forward</span>
            </div>
        </div>
        <div class="rpt-skel is-table" aria-hidden="true">@for($i = 0; $i < 6; $i++)<div class="rpt-skel-bar {{ $i === 0 ? '' : 'is-tall' }}"></div>@endfor</div>
        <div class="rpt-preview-body">
            <div class="rpt-table-wrap" id="reportTableWrap">
                <table class="rpt-table"><thead id="reportPreviewHead"></thead><tbody id="reportPreviewBody"></tbody></table>
            </div>
            <div class="rpt-empty" id="reportPreviewEmpty" hidden>
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6"/></svg>
                No matching tasks for the selected filters
            </div>
        </div>
        <p class="error" id="reportSplitError" style="display:none;margin:10px 16px 14px"></p>
    </section>
</div>

<script>
(function () {
    var bdSelect = document.getElementById('reportBd');
    var designerSelect = document.getElementById('reportDesigner');
    var periodSelect = document.getElementById('reportPeriod');
    var dateFrom = document.getElementById('reportDateFrom');
    var dateTo = document.getElementById('reportDateTo');
    var clearBtn = document.getElementById('reportClearBtn');
    var exportBtn = document.getElementById('reportExportBtn');
    var matchCount = document.getElementById('reportMatchCount');
    var exportError = document.getElementById('reportExportError');

    var summaryUrl = @json(route('admin.reports.summary'));
    var exportUrlBase = @json(route('admin.reports.export'));
    var exportDefaultText = exportBtn.textContent;

    var debounceTimer = null;
    var activeRequest = 0;
    var isLoading = false;

    function currentFilters() {
        var isCustom = periodSelect.value === 'custom';

        return {
            bd_id: bdSelect.value,
            designer_id: designerSelect.value,
            period: periodSelect.value,
            date_from: isCustom ? dateFrom.value : '',
            date_to: isCustom ? dateTo.value : '',
        };
    }

    function buildQuery(filters) {
        return Object.keys(filters).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(filters[key] || '');
        }).join('&');
    }

    function setLoading(loading) {
        isLoading = loading;
        exportBtn.classList.toggle('is-loading', loading);
        if (loading) {
            exportBtn.setAttribute('aria-disabled', 'true');
            exportBtn.innerHTML = '<span class="btn-spinner"></span>Loading...';
        } else {
            exportBtn.removeAttribute('aria-disabled');
            exportBtn.textContent = exportDefaultText;
        }
    }

    function togglePeriodInputs() {
        var isCustom = periodSelect.value === 'custom';
        dateFrom.style.display = isCustom ? '' : 'none';
        dateTo.style.display = isCustom ? '' : 'none';
    }

    function reload() {
        var filters = currentFilters();
        var query = buildQuery(filters);
        var requestId = ++activeRequest;

        setLoading(true);
        refreshSplit(filters, query);

        fetch(summaryUrl + '?' + query, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
            .then(function (data) {
                if (requestId !== activeRequest) return;
                exportBtn.href = exportUrlBase + '?' + query;
                matchCount.textContent = data.count + ' matching task' + (data.count === 1 ? '' : 's') + ' for the selected filters.';
                setLoading(false);
            })
            .catch(function () {
                if (requestId !== activeRequest) return;
                exportBtn.href = exportUrlBase + '?' + query;
                matchCount.textContent = '';
                setLoading(false);
            });
    }

    /* ---- Split view (BD selected): status overview + export preview ---- */
    var split = document.getElementById('reportSplit');
    var splitScope = document.getElementById('reportSplitScope');
    var statusList = document.getElementById('reportStatusList');
    var statusZero = document.getElementById('reportStatusZero');
    var statusToggle = document.getElementById('reportStatusToggle');
    var statusTotal = document.getElementById('reportStatusTotal');
    var previewHead = document.getElementById('reportPreviewHead');
    var previewBody = document.getElementById('reportPreviewBody');
    var tableWrap = document.getElementById('reportTableWrap');
    var previewEmpty = document.getElementById('reportPreviewEmpty');
    var chips = {
        rows: document.getElementById('reportChipRows'),
        overdue: document.getElementById('reportChipOverdue'),
        late: document.getElementById('reportChipLate'),
        carry: document.getElementById('reportChipCarry')
    };
    var splitError = document.getElementById('reportSplitError');
    var showZeroStatuses = false;
    var totalStatusCount = 0;
    // 0-based columns (from the sheet header) that hold multi-line text: clamped to 3 lines.
    var CLAMP_FROM_COLUMN = 13;
    var previewUrl = @json(route('admin.reports.preview'));
    var previewController = null;
    var previewSeq = 0;
    var statusColors = {
        assigned_tasks: '#2a78d6', review_analysis: '#eda100', need_clarification: '#e87ba4', yet_to_start: '#1baf7a',
        in_progress: '#eb6834', waiting_confirmation: '#4a3aa7', rework: '#e34948', prepare_printing_file: '#00a3bf',
        completed: '#008300', swap_tasks: '#b07cf0', decline_tasks: '#667085'
    };

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    }

    function selectedText(select) {
        return select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';
    }

    function statusRow(item, total) {
        var li = el('li', 'rpt-status-item' + (item.count ? '' : ' is-zero'));
        var color = item.count ? (statusColors[item.key] || '#98a2b3') : '';
        if (color) li.style.setProperty('--c', color);
        li.appendChild(el('span', 'rpt-status-dot'));
        li.appendChild(el('span', 'rpt-status-label', item.label));
        li.appendChild(el('span', 'rpt-status-count', item.count));
        if (item.count) {
            var bar = el('span', 'rpt-status-bar');
            var fill = el('span');
            fill.style.width = (total ? Math.max(2, item.count / total * 100) : 0) + '%';
            bar.appendChild(fill);
            li.appendChild(bar);
            li.title = item.label + ': ' + item.count + ' of ' + total + ' (' + Math.round(item.count / total * 100) + '%)';
        }
        return li;
    }

    function syncStatusToggle() {
        var zeroCount = statusZero.children.length;
        statusToggle.hidden = zeroCount === 0;
        statusZero.hidden = !showZeroStatuses || zeroCount === 0;
        statusToggle.setAttribute('aria-expanded', showZeroStatuses ? 'true' : 'false');
        statusToggle.textContent = showZeroStatuses ? 'Hide empty statuses' : 'Show all statuses (' + totalStatusCount + ')';
    }

    statusToggle.addEventListener('click', function () {
        showZeroStatuses = !showZeroStatuses;
        syncStatusToggle();
    });

    function renderSplit(data) {
        splitScope.textContent = [
            bdSelect.value ? selectedText(bdSelect) : 'All BDs',
            designerSelect.value ? selectedText(designerSelect) : 'All Designers',
            selectedText(periodSelect)
        ].join(' · ');

        // Left: non-zero statuses first (board order), empty ones behind the toggle.
        statusList.textContent = '';
        statusZero.textContent = '';
        totalStatusCount = data.statusCounts.length;
        data.statusCounts.forEach(function (item) {
            (item.count ? statusList : statusZero).appendChild(statusRow(item, data.total));
        });
        syncStatusToggle();
        statusTotal.textContent = data.total;

        // Right: stat chips (also the colour legend).
        var counts = { overdue: 0, late: 0, carry: 0 };
        data.rowFlags.forEach(function (flags) {
            if (flags.overdue) counts.overdue++;
            if (flags.completedLate) counts.late++;
            if (flags.carryForward) counts.carry++;
        });
        chips.rows.textContent = data.total;
        chips.overdue.textContent = counts.overdue;
        chips.late.textContent = counts.late;
        chips.carry.textContent = counts.carry;

        previewHead.textContent = '';
        previewBody.textContent = '';
        tableWrap.hidden = data.rows.length === 0;
        previewEmpty.hidden = data.rows.length !== 0;
        if (!data.rows.length) return;

        var headRow = el('tr');
        data.header.forEach(function (label) { headRow.appendChild(el('th', '', label)); });
        previewHead.appendChild(headRow);

        var statusIndex = data.header.indexOf('Status');
        var nameIndex = data.header.indexOf('Task Name');
        var fragment = document.createDocumentFragment();
        data.rows.forEach(function (row, index) {
            var flags = data.rowFlags[index] || {};
            // Same precedence as the sheet: amber carry-forward, overridden by red overdue/late.
            var tr = el('tr', flags.overdue || flags.completedLate ? 'is-late' : (flags.carryForward ? 'is-carry' : ''));
            row.forEach(function (value, col) {
                var text = value === null ? '' : String(value);
                var td = el('td', flags.completedLate && col === statusIndex ? 'is-done' : '');
                if (col === statusIndex) {
                    td.appendChild(el('span', 'rpt-pill', text));
                } else if (col >= CLAMP_FROM_COLUMN || col === nameIndex) {
                    td.appendChild(el('div', 'rpt-clamp' + (col === nameIndex ? ' is-2' : ''), text));
                    td.title = text;
                } else {
                    td.textContent = text;
                }
                tr.appendChild(td);
            });
            fragment.appendChild(tr);
        });
        previewBody.appendChild(fragment);
    }

    function refreshSplit(filters, query) {
        if (previewController) previewController.abort();
        var seq = ++previewSeq;

        // Always shown: BD and Designer filter independently (All × All = overall).
        previewController = new AbortController();
        split.hidden = false;
        split.classList.add('is-loading');
        splitError.style.display = 'none';

        fetch(previewUrl + '?' + query, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            signal: previewController.signal,
        })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error('preview_failed')); })
            .then(function (data) {
                if (seq !== previewSeq) return;
                renderSplit(data);
                split.classList.remove('is-loading');
            })
            .catch(function (error) {
                if (error.name === 'AbortError' || seq !== previewSeq) return;
                split.classList.remove('is-loading');
                splitError.textContent = 'The report preview could not be loaded. Please try again.';
                splitError.style.display = '';
            });
    }

    function scheduleReload() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(reload, 350);
    }

    bdSelect.addEventListener('change', scheduleReload);
    designerSelect.addEventListener('change', scheduleReload);
    periodSelect.addEventListener('change', function () { togglePeriodInputs(); scheduleReload(); });
    dateFrom.addEventListener('change', scheduleReload);
    dateTo.addEventListener('change', scheduleReload);

    function resetFiltersAndReload() {
        bdSelect.value = '';
        designerSelect.value = '';
        periodSelect.value = 'current_month';
        dateFrom.value = '';
        dateTo.value = '';
        togglePeriodInputs();
        reload();
    }

    clearBtn.addEventListener('click', resetFiltersAndReload);

    exportBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (isLoading) return;

        var href = exportBtn.getAttribute('href');
        exportError.style.display = 'none';
        setLoading(true);

        fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) throw new Error('export_failed');
                var disposition = res.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/);
                var filename = match ? match[1] : 'report.xlsx';
                return res.blob().then(function (blob) { return { blob: blob, filename: filename }; });
            })
            .then(function (result) {
                var blobUrl = window.URL.createObjectURL(result.blob);
                var link = document.createElement('a');
                link.href = blobUrl;
                link.download = result.filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);

                setLoading(false);
                // Only reset filters after a confirmed successful download —
                // a failed export (caught below) must leave them untouched.
                resetFiltersAndReload();
            })
            .catch(function () {
                setLoading(false);
                exportError.textContent = 'The report could not be exported. Please try again.';
                exportError.style.display = '';
            });
    });

    // Load the split view straight away for the current (or URL-preselected) filters.
    refreshSplit(currentFilters(), buildQuery(currentFilters()));
})();
</script>
@endsection
