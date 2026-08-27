@extends('layouts.app')

@section('title','Designer Performance Dashboard')
@section('workspace-title','Designer Performance & Task Control')
@section('workspace-subtitle','Live insight into every Designer’s workload, quality, rework and approvals')

@section('content')
<style>
    .dh-dash{display:flex;flex-direction:column;gap:16px}
    .dh-top{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .dh-eyebrow{font-size:8px;font-weight:950;letter-spacing:.14em;text-transform:uppercase;color:#e30613}
    .dh-title{margin:4px 0 0;font-size:22px;font-weight:900;letter-spacing:-.025em;color:#101828}
    .dh-sub{margin-top:5px;font-size:10px;color:#667085}
    .dh-filters{display:flex;gap:9px;align-items:center;flex-wrap:wrap}
    .dh-select{appearance:none;min-height:38px;padding:8px 30px 8px 12px;border:1px solid #d0d5dd;border-radius:10px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23667085' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center;font-size:10px;font-weight:850;color:#344054;cursor:pointer}
    .dh-select:focus{outline:none;border-color:#f04452;box-shadow:0 0 0 3px rgba(227,6,19,.08)}
    .dh-zone{min-height:120px}
    .dh-zone-inner{display:flex;flex-direction:column;gap:14px}
    .dh-loading{opacity:.5;pointer-events:none}

    .dh-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(148px,1fr));gap:9px}
    .dh-kpi{border:1px solid #e4e7ec;border-radius:12px;background:#fff;padding:12px;min-height:96px;box-shadow:0 1px 2px rgba(16,24,40,.03)}
    .dh-kpi-accent{border-color:#f1d0d3;background:linear-gradient(180deg,#fff,#fff7f7)}
    .dh-kpi-icon{width:30px;height:30px;border-radius:9px;background:#f4f5f7;display:grid;place-items:center;font-size:14px;margin-bottom:8px;color:#475467}
    .dh-kpi-accent .dh-kpi-icon{background:#fff0f1;color:#e30613}
    .dh-kpi-label{font-size:8px;font-weight:900;color:#667085;text-transform:uppercase;letter-spacing:.04em;line-height:1.35}
    .dh-kpi-value{font-size:21px;font-weight:950;color:#101828;margin-top:4px}
    .dh-kpi-note{font-size:8px;color:#98a2b3;margin-top:2px;line-height:1.4}

    .dh-card{background:#fff;border:1px solid #e4e7ec;border-radius:13px;overflow:hidden;min-width:0}
    .dh-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .dh-card-title{font-size:12px;font-weight:900;color:#101828}
    .dh-card-sub{font-size:9px;color:#667085;margin-top:3px}
    .dh-card-badge{flex:0 0 auto;display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:#101828;color:#fff;font-size:9px;font-weight:900}
    .dh-card-body{padding:12px 13px}

    .dh-grid{display:grid;gap:14px;align-items:start}
    .dh-grid-2{grid-template-columns:1fr 1.35fr}

    .dh-table-wrap{overflow-x:auto}
    .dh-table{width:100%;border-collapse:collapse;min-width:840px}
    .dh-table th{padding:8px 9px;font-size:8px;color:#98a2b3;text-transform:uppercase;letter-spacing:.035em;text-align:left;border-bottom:1px solid #eaecf0;white-space:nowrap;font-weight:900}
    .dh-table td{padding:9px;font-size:9px;color:#344054;border-bottom:1px solid #f2f4f7;vertical-align:middle;line-height:1.45}
    .dh-table tr:last-child td{border-bottom:0}
    .dh-strong{font-weight:900;color:#101828}
    .dh-muted{color:#98a2b3}
    .dh-danger{color:#c01048;font-weight:850}
    .dh-cell-main{max-width:220px;overflow:hidden;text-overflow:ellipsis}
    .dh-cell-sub{font-size:8px;color:#98a2b3;margin-top:2px;line-height:1.4;font-weight:600}
    .dh-task-link{font-weight:900;color:#101828;text-decoration:none}
    .dh-task-link:hover{color:#e30613}
    .dh-click{cursor:pointer}
    .dh-click:hover td{background:#fafafa}
    .dh-row-selected td{background:#fff5f5}
    .dh-designer-link{font-weight:900;color:#101828;text-decoration:underline dotted #c1c7d0}
    .dh-now{display:inline-flex;margin-left:6px;padding:2px 6px;border-radius:999px;background:#e30613;color:#fff;font-size:7px;font-weight:900;vertical-align:middle}
    .dh-empty{padding:26px 10px;text-align:center;color:#98a2b3;font-size:9px}

    .dh-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;white-space:nowrap;line-height:1}
    .dh-pill-assigned{background:#f2f4f7;color:#475467}
    .dh-pill-review{background:#eff6ff;color:#1d4ed8}
    .dh-pill-clarify{background:#fff6ed;color:#b54708}
    .dh-pill-ready{background:#ecfdff;color:#0e7490}
    .dh-pill-progress{background:#eef4ff;color:#3538cd}
    .dh-pill-waiting{background:#f4f0ff;color:#6938ef}
    .dh-pill-rework{background:#fffaeb;color:#b54708}
    .dh-pill-completed{background:#ecfdf3;color:#027a48}
    .dh-pill-swap{background:#fff1f3;color:#c01048}
    .dh-pill-overdue{background:#fff1f3;color:#c01048}
    .dh-pill-default{background:#f9fafb;color:#475467;border:1px solid #eaecf0}

    .dh-progress{min-width:96px}
    .dh-progress-track{height:6px;background:#f2f4f7;border-radius:999px;overflow:hidden}
    .dh-progress-fill{height:100%;border-radius:999px;background:#e30613}
    .dh-progress-note{font-size:8px;color:#98a2b3;margin-top:3px}

    .dh-bar{display:flex;flex-direction:column;gap:11px}
    .dh-bar-row{display:grid;grid-template-columns:96px minmax(0,1fr) 34px;gap:9px;align-items:center}
    .dh-bar-label{font-size:9px;font-weight:850;color:#475467}
    .dh-bar-track{height:10px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .dh-bar-fill{height:100%;border-radius:999px;min-width:0;transition:width .3s}
    .dh-bar-value{font-size:11px;font-weight:950;color:#101828;text-align:right}

    .dh-line-chart{width:100%;height:auto;display:block}
    .dh-line-chart .grid-line{stroke:#f1f2f4;stroke-width:1}
    .dh-line-chart .trend-line{fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .dh-line-chart .trend-completed{stroke:#e30613}
    .dh-line-chart .trend-rating{stroke:#f5b301;stroke-dasharray:4 3}
    .dh-line-chart .trend-point{stroke:#fff;stroke-width:1.5}
    .dh-line-chart .trend-point-completed{fill:#e30613}
    .dh-line-chart .trend-point-rating{fill:#f5b301}
    .dh-line-chart .trend-label{font-size:8px;fill:#98a2b3;font-weight:700}
    .dh-line-chart .trend-value{font-size:8px;fill:#344054;font-weight:900}
    .dh-line-chart .trend-value-rating{fill:#b27a00}
    .dh-chart-legend{display:flex;gap:14px;margin-top:8px;font-size:8px;color:#667085;font-weight:750}
    .dh-chart-legend span{display:inline-flex;align-items:center;gap:5px}
    .dh-legend-dot{width:8px;height:8px;border-radius:2px;display:inline-block}

    .dh-req{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:12px;margin-bottom:9px}
    .dh-req:last-child{margin-bottom:0}
    .dh-req-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
    .dh-req-top strong{font-size:10px;font-weight:900;color:#101828}
    .dh-req-reason{margin-top:7px;padding:8px 10px;border-radius:8px;background:#f8f9fb;font-size:9px;color:#475467;line-height:1.5}
    .dh-decision{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:11px;border-top:1px solid #eaecf0;padding-top:11px}
    .dh-decision-box{border:1px solid #e4e7ec;border-radius:10px;padding:10px;background:#fcfcfd}
    .dh-decision-title{font-size:9px;font-weight:900;color:#101828;margin-bottom:7px}
    .dh-label{display:block;font-size:8px;font-weight:900;color:#667085;text-transform:uppercase;margin:8px 0 4px}
    .dh-select-field{width:100%;border:1px solid #d0d5dd;border-radius:8px;padding:8px 9px;background:#fff;font-size:9px;color:#344054;min-height:34px}
    textarea.dh-select-field{min-height:52px;resize:vertical}
    .dh-hint{font-size:8px;color:#98a2b3;margin-top:4px;line-height:1.4}
    .dh-decision-actions{display:flex;justify-content:flex-end;margin-top:9px}
    .dh-btn{border:0;border-radius:8px;padding:8px 12px;font-size:9px;font-weight:900;cursor:pointer}
    .dh-btn-accept{background:#101828;color:#fff}
    .dh-btn-decline{background:#e30613;color:#fff}

    .dh-rating{border:1px solid #e6e9ef;border-radius:12px;padding:12px;margin-bottom:9px}
    .dh-rating:last-child{margin-bottom:0}
    .dh-rating-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
    .dh-rating-head strong{font-size:10px;font-weight:900;color:#101828}
    .dh-rating-score{display:flex;align-items:center;gap:8px}
    .dh-sub-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}
    .dh-sub-item{border:1px solid #eef0f3;border-radius:9px;background:#fbfbfc;padding:9px 10px;min-width:0}
    .dh-sub-item span{display:block;font-size:8px;font-weight:850;color:#667085;text-transform:uppercase;letter-spacing:.02em}
    .dh-sub-item strong{display:block;font-size:11px;color:#101828;margin:3px 0 5px}
    .dh-sub-overall{border-color:#e7cdc0;background:#fffaf7}
    .dh-rating-meta{display:grid;grid-template-columns:1fr auto;gap:14px;margin-top:11px;padding-top:11px;border-top:1px solid #eaecf0}
    .dh-rating-meta div{font-size:9px;color:#475467;line-height:1.5}
    .dh-rating-meta strong{font-size:8px;font-weight:900;text-transform:uppercase;color:#667085}
    .dh-rating-meta-end{text-align:right;font-size:8px;color:#98a2b3}
    .dh-stars{display:inline-flex;gap:2px;line-height:1}
    .dh-star{--star-fill:0%;display:inline-block;width:13px;height:13px;flex:0 0 13px;font-size:13px;line-height:13px;font-family:Arial,"Segoe UI Symbol",sans-serif;background:linear-gradient(90deg,#f5b301 0%,#f5b301 var(--star-fill),#d8dee8 var(--star-fill),#d8dee8 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}

    .dh-scroll{overflow-y:auto;overscroll-behavior:contain}

    @media(max-width:1280px){.dh-grid-2{grid-template-columns:1fr}}
    @media(max-width:760px){.dh-top{align-items:flex-start;flex-direction:column}.dh-sub-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dh-decision{grid-template-columns:1fr}.dh-rating-meta{grid-template-columns:1fr}.dh-rating-meta-end{text-align:left}.dh-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<div class="dh-dash">
    <div class="dh-top">
        <div>
            <div class="dh-eyebrow">Designer Head</div>
            <h1 class="dh-title">Designer Performance &amp; Task Control</h1>
            <div class="dh-sub">{{ $selectedMonthLabel }} · {{ $selectedDesignerName ?? 'All Designers' }}</div>
        </div>
        <div class="dh-filters">
            <select class="dh-select" id="dh-designer" aria-label="Filter by Designer">
                <option value="all">All Designers</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected((int) $selectedDesigner === (int) $designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>
            <select class="dh-select" id="dh-month" aria-label="Filter by Month">
                @foreach($months as $monthOption)
                    <option value="{{ $monthOption['value'] }}" @selected($selectedMonth === $monthOption['value'])>{{ $monthOption['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div id="dh-root" class="dh-zone">
        @include('designer-head.dashboard-partial', [
            'stats' => $stats,
            'designers' => $designers,
            'selectedDesigner' => $selectedDesigner,
            'selectedDesignerName' => $selectedDesignerName,
            'workload' => $workload,
            'bar' => $bar,
            'line' => $line,
            'taskRows' => $taskRows,
            'reworkRows' => $reworkRows,
            'ratings' => $ratings,
            'completions' => $completions,
            'overdue' => $overdue,
            'pendingRequests' => $pendingRequests,
            'recentDecisions' => $recentDecisions,
        ])
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('dh-root');
    var base = "{{ route('designer-head.dashboard.partial') }}";

    function reload() {
        var designer = document.getElementById('dh-designer').value;
        var month = document.getElementById('dh-month').value;
        root.classList.add('dh-loading');
        fetch(base + '?designer=' + encodeURIComponent(designer) + '&month=' + encodeURIComponent(month), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                root.replaceChildren.apply(root, Array.prototype.slice.call(tmp.childNodes));
                root.classList.remove('dh-loading');
            })
            .catch(function () { root.classList.remove('dh-loading'); });
    }

    document.getElementById('dh-designer').addEventListener('change', reload);
    document.getElementById('dh-month').addEventListener('change', reload);

    root.addEventListener('click', function (e) {
        var el = e.target.closest('[data-dh-design]');
        if (!el) return;
        document.getElementById('dh-designer').value = el.dataset.dhDesign;
        reload();
    });
})();
</script>
@endsection