@extends('layouts.app')
@section('title','Overall Reports')
@section('workspace-title','Overall Reports')
@section('workspace-subtitle','Filter by BD, Designer and period, then export the full task report to Excel')
@section('content')

<style>
    #reportExportBtn.is-loading{pointer-events:none;opacity:.65}
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
                <option value="">All BDs</option>
                @foreach($bds as $bd)
                    <option value="{{ $bd->id }}" @selected($bdId === (string) $bd->id)>{{ $bd->name }}</option>
                @endforeach
            </select>

            <select class="premium-select" id="reportDesigner">
                <option value="">All Designers</option>
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

        <p class="muted" id="reportMatchCount" style="margin-top:14px"></p>
        <p class="muted" style="margin-top:6px">
            The exported sheet includes tasks from the selected period, plus any still-open task carried forward from an earlier month (Current Month only) — those rows are highlighted in amber.
        </p>
    </div>
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

    function scheduleReload() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(reload, 350);
    }

    bdSelect.addEventListener('change', scheduleReload);
    designerSelect.addEventListener('change', scheduleReload);
    periodSelect.addEventListener('change', function () { togglePeriodInputs(); scheduleReload(); });
    dateFrom.addEventListener('change', scheduleReload);
    dateTo.addEventListener('change', scheduleReload);

    clearBtn.addEventListener('click', function () {
        bdSelect.value = '';
        designerSelect.value = '';
        periodSelect.value = 'current_month';
        dateFrom.value = '';
        dateTo.value = '';
        togglePeriodInputs();
        reload();
    });

    exportBtn.addEventListener('click', function (e) {
        if (isLoading) e.preventDefault();
    });
})();
</script>
@endsection
