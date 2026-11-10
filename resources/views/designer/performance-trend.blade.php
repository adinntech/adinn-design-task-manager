{{-- Designer Dashboard Performance Trend (designer-only visual; the shared partial used by BD / Head is untouched).
     Expects the same data as shared.performance-trend: $trendCards, $trendData, $trendContext. --}}
@php
    $trendData = collect($trendData ?? [])->values();
    $cards = collect($trendCards ?? [])->values();
    $months = $trendData->pluck('label');

    $icons = [
        'trend' => '<path d="M12 16v5"/><path d="M16 14v7"/><path d="M20 10v11"/><path d="m22 3-8.646 8.646a.5.5 0 0 1-.708 0L9.354 8.354a.5.5 0 0 0-.707 0L2 15"/><path d="M4 18v3"/><path d="M8 14v7"/>',
        'user' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'Assigned Tasks' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'In Progress' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
        'Completed' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'Overdue & Completed' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'Declined' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    ];
    $ico = fn (string $k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$k] ?? $icons['trend']).'</svg>';
    $tones = ['Assigned Tasks' => 'blue', 'In Progress' => 'purple', 'Completed' => 'green', 'Overdue & Completed' => 'orange', 'Declined' => 'red'];

    $tw = 1400; $thLine = 230; $thBar = 190; $px = 44; $py = 28;
    $n = max(2, $trendData->count());
    $stepX = ($tw - $px * 2) / ($n - 1);
    $lineSeries = [
        'assigned' => ['label' => 'Assigned Tasks', 'color' => '#2563eb', 'fill' => 'dpt-fill-blue'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#9333ea', 'fill' => 'dpt-fill-purple'],
        'completed' => ['label' => 'Completed', 'color' => '#16a34a', 'fill' => 'dpt-fill-green'],
    ];
    $lineMax = max(1, (int) collect($lineSeries)->map(fn ($s, $key) => $trendData->max($key))->max());
    $yOf = fn ($v, $h, $max) => round($h - $py - ($v / $max) * ($h - $py * 2), 1);
    $linePoints = collect($lineSeries)->map(fn ($s, $key) => $trendData->map(fn ($m, $i) => [
        'x' => round($px + $i * $stepX, 1),
        'y' => $yOf((int) ($m[$key] ?? 0), $thLine, $lineMax),
        'v' => (int) ($m[$key] ?? 0),
    ]));
    $yTicks = collect([0, 0.25, 0.5, 0.75, 1])->map(fn ($f) => (int) round($f * $lineMax))->unique()->map(fn ($v) => ['y' => $yOf($v, $thLine, $lineMax), 'v' => $v]);
    // Stack value labels upward where points of different series sit close together.
    $labelY = [];
    foreach ($trendData->keys() as $i) {
        $col = collect($linePoints)->map(fn ($pts) => $pts[$i]['y'])->sortDesc();
        $prev = null;
        foreach ($col as $key => $y) {
            $ly = $y - 11;
            if ($prev !== null) { $ly = min($ly, $prev - 15); }
            $labelY[$key][$i] = $ly;
            $prev = $ly;
        }
    }
    $monthTooltips = $trendData->map(fn ($m) => trim(($m['label'] ?? '')
        ."\nAssigned: ".(int) ($m['assigned'] ?? 0)
        ."\nIn Progress: ".(int) ($m['in_progress'] ?? 0)
        ."\nCompleted: ".(int) ($m['completed'] ?? 0)));

    $barSeries = [
        'overdue_completed' => ['label' => 'Overdue & Completed', 'color' => '#f59e0b', 'grad' => 'dptBarOrange'],
        'declined' => ['label' => 'Declined', 'color' => '#e11d48', 'grad' => 'dptBarRed'],
    ];
    $barMax = max(1, (int) collect($barSeries)->map(fn ($b, $key) => $trendData->max($key))->max());
    $barStep = ($tw - $px * 2) / $n;
    $barW = min(48, $barStep / 4);
    $baseY = $thBar - $py;
@endphp

<section class="dpt">
    <div class="dpt-head">
        <span class="dpt-head-icon">{!! $ico('trend') !!}</span>
        <div class="dpt-head-text">
            <h2>Performance Trend</h2>
            <p>Track the status-wise performance of your tasks over time.</p>
        </div>
    </div>

    <div class="dpt-kpis">
        @foreach($cards as $c)
            <div class="dpt-kpi dpt-{{ $tones[$c['label']] ?? 'blue' }}">
                <span class="dpt-kpi-icon">{!! $ico($c['label']) !!}</span>
                <div><div class="dpt-kpi-label">{{ $c['label'] }}</div><div class="dpt-kpi-value">{{ $c['value'] }}</div></div>
            </div>
        @endforeach
    </div>

    <div class="dpt-tabs" role="tablist" aria-label="Performance chart">
        <button type="button" class="dpt-tab is-active" role="tab" aria-selected="true" data-dpt-tab="status">Assigned · In Progress · Completed</button>
        <button type="button" class="dpt-tab" role="tab" aria-selected="false" data-dpt-tab="outcome">Overdue &amp; Completed · Declined</button>
    </div>

    <div class="dpt-chart" data-dpt-panel="status" role="tabpanel">
        <div class="dpt-chart-head">
            <div class="dpt-chart-title">Assigned · In Progress · Completed</div>
            <div class="dpt-legend">
                @foreach($lineSeries as $ls)<span><i style="background:{{ $ls['color'] }}"></i>{{ $ls['label'] }}</span>@endforeach
            </div>
        </div>
        <div class="dpt-scroll"><svg class="dpt-svg" viewBox="0 0 {{ $tw }} {{ $thLine + 30 }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Assigned, In Progress and Completed by month">
            <defs>
                <linearGradient id="dpt-fill-blue" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2563eb" stop-opacity=".16"/><stop offset="1" stop-color="#2563eb" stop-opacity="0"/></linearGradient>
                <linearGradient id="dpt-fill-purple" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9333ea" stop-opacity=".14"/><stop offset="1" stop-color="#9333ea" stop-opacity="0"/></linearGradient>
                <linearGradient id="dpt-fill-green" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#16a34a" stop-opacity=".18"/><stop offset="1" stop-color="#16a34a" stop-opacity="0"/></linearGradient>
            </defs>
            @foreach($yTicks as $tick)
                <line class="dpt-grid" x1="{{ $px }}" x2="{{ $tw - $px }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"/>
                <text class="dpt-axis" x="{{ $px - 10 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['v'] }}</text>
            @endforeach
            @php $baseLine = $yOf(0, $thLine, $lineMax); @endphp
            @foreach($lineSeries as $key => $ls)
                @php $pts = $linePoints[$key]; @endphp
                @if($pts->count() > 1)
                    <polygon fill="url(#{{ $ls['fill'] }})" points="{{ $pts->first()['x'] }},{{ $baseLine }} {{ $pts->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }} {{ $pts->last()['x'] }},{{ $baseLine }}"/>
                @endif
            @endforeach
            @foreach($lineSeries as $key => $ls)
                <polyline class="dpt-line" style="stroke:{{ $ls['color'] }}" points="{{ $linePoints[$key]->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"/>
            @endforeach
            @foreach($lineSeries as $key => $ls)
                @foreach($linePoints[$key] as $i => $p)
                    <circle class="dpt-point" style="stroke:{{ $ls['color'] }};fill:{{ $loop->last ? '#fff' : $ls['color'] }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5"/>
                    @php
                        // Assigned above its point, Completed below, In Progress beside it — so close values never sit on top of each other.
                        [$lx, $ly, $anchor] = match ($key) {
                            'assigned' => [$p['x'], $p['y'] - 12, 'middle'],
                            'completed' => [$p['x'], $p['y'] + 20, 'middle'],
                            default => $loop->last ? [$p['x'] - 12, $p['y'] + 4, 'end'] : [$p['x'] + 12, $p['y'] + 4, 'start'],
                        };
                    @endphp
                    <text class="dpt-value" style="fill:{{ $ls['color'] }}" x="{{ $lx }}" y="{{ $ly }}" text-anchor="{{ $anchor }}">{{ $p['v'] }}</text>
                @endforeach
            @endforeach
            @foreach($months as $i => $m)
                <text class="dpt-month" x="{{ round($px + $i * $stepX, 1) }}" y="{{ $thLine + 12 }}" text-anchor="middle">{{ $m }}</text>
            @endforeach
            @foreach($trendData as $i => $m)
                <rect class="dpt-hover" x="{{ round($px + $i * $stepX - $stepX / 2, 1) }}" y="0" width="{{ round($stepX, 1) }}" height="{{ $thLine }}" fill="transparent"><title>{{ $monthTooltips[$i] }}</title></rect>
            @endforeach
        </svg></div>
    </div>

    <div class="dpt-chart" data-dpt-panel="outcome" role="tabpanel" hidden>
        <div class="dpt-chart-head">
            <div class="dpt-chart-title">Overdue &amp; Completed · Declined</div>
            <div class="dpt-legend">
                @foreach($barSeries as $bs)<span><i style="background:{{ $bs['color'] }}"></i>{{ $bs['label'] }}</span>@endforeach
            </div>
        </div>
        <div class="dpt-scroll"><svg class="dpt-svg" viewBox="0 0 {{ $tw }} {{ $thBar + 30 }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Overdue and Completed, and Declined by month">
            <defs>
                <linearGradient id="dptBarOrange" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#f97316"/></linearGradient>
                <linearGradient id="dptBarRed" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fb7185"/><stop offset="1" stop-color="#e11d48"/></linearGradient>
            </defs>
            @foreach(collect([0, 0.5, 1])->map(fn ($f) => (int) round($f * $barMax))->unique() as $tv)
                @php $gy = round($baseY - ($tv / $barMax) * ($thBar - $py * 2), 1); @endphp
                <line class="dpt-grid" x1="{{ $px }}" x2="{{ $tw - $px }}" y1="{{ $gy }}" y2="{{ $gy }}"/>
                <text class="dpt-axis" x="{{ $px - 10 }}" y="{{ $gy + 4 }}" text-anchor="end">{{ $tv }}</text>
            @endforeach
            @foreach($trendData as $mi => $m)
                @php $cx = $px + $barStep * ($mi + 0.5); @endphp
                @foreach($barSeries as $key => $bs)
                    @php
                        $val = (int) ($m[$key] ?? 0);
                        $barH = ($val / $barMax) * ($thBar - $py * 2);
                        $bx = $cx - $barW + ($loop->index * $barW) + ($loop->index ? 4 : -4);
                        $by = $baseY - $barH;
                    @endphp
                    @if($barH > 0)
                        <path class="dpt-bar" fill="url(#{{ $bs['grad'] }})" d="M{{ $bx }},{{ $baseY }} V{{ $by + 8 }} Q{{ $bx }},{{ $by }} {{ $bx + 8 }},{{ $by }} H{{ $bx + $barW - 8 }} Q{{ $bx + $barW }},{{ $by }} {{ $bx + $barW }},{{ $by + 8 }} V{{ $baseY }} Z"><title>{{ $bs['label'] }} · {{ $m['label'] }}: {{ $val }}</title></path>
                    @endif
                    @if($barH > 0 || $loop->first)
                        <text class="dpt-value" x="{{ $bx + $barW / 2 }}" y="{{ $by - 8 }}" text-anchor="middle">{{ $val }}</text>
                    @endif
                @endforeach
            @endforeach
            @foreach($months as $i => $m)
                <text class="dpt-month" x="{{ round($px + $barStep * ($i + 0.5), 1) }}" y="{{ $thBar + 12 }}" text-anchor="middle">{{ $m }}</text>
            @endforeach
        </svg></div>
    </div>
</section>

<script>
(function () {
    var root = document.currentScript.previousElementSibling;
    if (!root) return;
    var tabs = root.querySelectorAll('[data-dpt-tab]');
    var panels = root.querySelectorAll('[data-dpt-panel]');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var key = tab.getAttribute('data-dpt-tab');
            tabs.forEach(function (t) {
                var on = t === tab;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (p) { p.hidden = p.getAttribute('data-dpt-panel') !== key; });
        });
    });
})();
</script>

@once
<style>
    .dpt-tabs{display:inline-flex;gap:4px;margin-top:14px;padding:4px;border:1px solid #eceff4;border-radius:12px;background:#f7f8fa;max-width:100%;flex-wrap:wrap}
    .dpt-tab{border:0;background:transparent;padding:9px 16px;border-radius:9px;font-size:12.5px;font-weight:800;color:#475467;cursor:pointer;transition:background .15s,color .15s,box-shadow .15s}
    .dpt-tab:hover{color:#101828}
    .dpt-tab.is-active{background:#fff;color:#d4111f;box-shadow:0 1px 3px rgba(16,24,40,.12)}
    .dpt-chart[hidden]{display:none}
    .dpt{border:1px solid #f1dfe3;border-radius:20px;background:linear-gradient(180deg,#fff7f8,#fff 30%);box-shadow:0 10px 30px rgba(227,6,19,.07);padding:18px}
    .dpt-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .dpt-head-icon{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#e30613;color:#fff;flex:none;box-shadow:0 6px 14px rgba(227,6,19,.28)}
    .dpt-head-icon svg{width:20px;height:20px}
    .dpt-head-text{flex:1;min-width:220px}
    .dpt-head-text h2{margin:0;font-size:19px;line-height:1.15;font-weight:900;color:#101828}
    .dpt-head-text p{margin:3px 0 0;font-size:11.5px;color:#475467}
    .dpt-chip{display:inline-flex;align-items:center;gap:8px;padding:9px 15px;border:1px solid #dfe3ea;border-radius:10px;background:#fff;font-size:12px;font-weight:800;color:#101828;box-shadow:0 1px 2px rgba(16,24,40,.04)}
    .dpt-chip svg{width:15px;height:15px;color:#344054}
    .dpt-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-top:16px}
    .dpt-kpi{--bg1:#eef5ff;--bg2:#f7faff;--ic:#2563eb;display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:14px;border:1px solid rgba(16,24,40,.06);background:linear-gradient(135deg,var(--bg1),var(--bg2))}
    .dpt-kpi-icon{display:grid;place-items:center;width:38px;height:38px;flex:none;color:var(--ic)}
    .dpt-kpi-icon svg{width:26px;height:26px}
    .dpt-kpi-label{font-size:11px;font-weight:700;color:#344054;line-height:1.25}
    .dpt-kpi-value{font-size:21px;font-weight:900;line-height:1.1;color:#101828;margin-top:2px}
    .dpt-purple{--bg1:#f6eeff;--bg2:#fbf7ff;--ic:#9333ea}
    .dpt-green{--bg1:#e9f9ee;--bg2:#f5fcf8;--ic:#16a34a}
    .dpt-orange{--bg1:#fff1e0;--bg2:#fff8ef;--ic:#f59e0b}
    .dpt-red{--bg1:#ffeaee;--bg2:#fff5f6;--ic:#e11d48}
    .dpt-chart{margin-top:14px;border:1px solid #eceff4;border-radius:14px;background:#fff;padding:13px 16px 8px;min-width:0}
    .dpt-chart-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .dpt-chart-title{font-size:13.5px;font-weight:900;color:#101828}
    .dpt-legend{display:flex;gap:16px;flex-wrap:wrap;font-size:11.5px;font-weight:600;color:#344054}
    .dpt-legend span{display:inline-flex;align-items:center;gap:8px}
    .dpt-legend i{width:10px;height:10px;border-radius:50%;display:inline-block}
    .dpt-scroll{overflow-x:auto}.dpt-svg{width:100%;min-width:640px;height:auto;display:block;margin-top:6px}
    .dpt-grid{stroke:#dfe3ea;stroke-width:1;stroke-dasharray:4 5}
    .dpt-axis{font-size:12px;font-weight:700;fill:#667085}
    .dpt-month{font-size:12px;font-weight:700;fill:#667085}
    .dpt-value{font-size:13px;font-weight:900;fill:#101828;paint-order:stroke;stroke:#fff;stroke-width:4px;stroke-linejoin:round}
    .dpt-line{fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
    .dpt-point{stroke-width:2.5;cursor:pointer;transition:r .12s}
    .dpt-point:hover{r:6.5}
    .dpt-bar{cursor:pointer;transition:opacity .15s}
    .dpt-bar:hover{opacity:.85}
    @media(max-width:1100px){.dpt-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:640px){.dpt{padding:14px 12px}.dpt-head-text h2{font-size:19px}.dpt-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dpt-chart{padding:12px 10px 6px}.dpt-chart-title{font-size:14px}}
</style>
@endonce
