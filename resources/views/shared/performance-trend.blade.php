{{-- Shared Performance Trend — reused by Designer, Designer Head and BD dashboards.
     Expects:
       $trendCards  : array of ['label' => string, 'value' => int, 'color' => string] — selected-period metrics (5)
       $trendData   : collection of monthly rows ['label', 'assigned', 'in_progress', 'completed', 'overdue_completed', 'declined']
       $trendContext: string shown near the title, e.g. "sk_designer • Sep 2026"
     Ratings are intentionally NOT part of this section. --}}

@php
    $trendData = $trendData ?? collect();
    $trendData = collect($trendData)->values();
    $trendCards = $trendCards ?? [];
    $cards = collect($trendCards);
    $months = $trendData->pluck('label');
    $cardColors = $cards->pluck('color', 'label');

    $tw = 620; $th = 160; $px = 40; $py = 22;
    $n = max(2, $trendData->count());
    $stepX = ($tw - $px * 2) / ($n - 1);
    $lineSeries = [
        'assigned'  => ['label' => 'Assigned',     'color' => '#2970ff', 'dash' => false],
        'in_progress'=> ['label' => 'In Progress', 'color' => '#7c3aed', 'dash' => false],
        'completed' => ['label' => 'Completed',    'color' => '#027a48', 'dash' => false],
    ];
    $lineMax = max(1, (int) collect($lineSeries)->map(fn ($s) => $trendData->max($s['label'] === 'In Progress' ? 'in_progress' : ($s['label'] === 'Completed' ? 'completed' : 'assigned')))->max());

    $linePoints = collect($lineSeries)->map(function ($s) use ($trendData, $px, $py, $th, $stepX, $lineMax) {
        $key = $s['label'] === 'In Progress' ? 'in_progress' : ($s['label'] === 'Completed' ? 'completed' : 'assigned');
        return $trendData->values()->map(function ($m, $i) use ($key, $px, $py, $th, $stepX, $lineMax) {
            return [
                'x' => round($px + $i * $stepX, 1),
                'y' => round($th - $py - (($m[$key] ?? 0) / $lineMax) * ($th - $py * 2), 1),
                'v' => (int) ($m[$key] ?? 0),
                'label' => $m['label'] ?? '',
            ];
        });
    });
    $linePolys = $linePoints->map(fn ($pts) => $pts->map(fn ($p) => $p['x'].','.$p['y'])->implode(' '));

    $barSeries = [
        'overdue_completed' => ['label' => 'Overdue & Completed', 'color' => '#f79009'],
        'declined'          => ['label' => 'Declined',            'color' => '#c01048'],
    ];
    $barMax = max(1, (int) collect($barSeries)->map(fn ($b, $key) => $trendData->max($key))->max());
    $barW = ($tw - $px * 2) / ($n * 2.6);
    $barStep = ($tw - $px * 2) / $n;
@endphp

<section class="pt-card">
    <div class="pt-head">
        <div>
            <div class="pt-title">Performance Trend</div>
            <div class="pt-sub"><span class="pt-viewing">Viewing:</span> {{ $trendContext }}</div>
        </div>
    </div>

    <div class="pt-summary">
        @foreach($cards as $c)
            <div class="pt-stat">
                <span class="pt-stat-dot" style="background:{{ $c['color'] }}"></span>
                <span class="pt-stat-label">{{ $c['label'] }}</span>
                <span class="pt-stat-value">{{ $c['value'] }}</span>
            </div>
        @endforeach
    </div>

    <div class="pt-charts">
        <div class="pt-chart pt-chart-line">
            <div class="pt-chart-title">Assigned · In Progress · Completed</div>
            <svg class="pt-svg" viewBox="0 0 {{ $tw }} {{ $th + 22 }}" preserveAspectRatio="xMidYMid meet" role="img">
                @foreach([0.25, 0.5, 0.75] as $frac)
                    <line class="pt-grid" x1="{{ $px }}" x2="{{ $tw - $px }}" y1="{{ $th - $py - $frac * ($th - $py * 2) }}" y2="{{ $th - $py - $frac * ($th - $py * 2) }}"/>
                @endforeach
                @foreach($lineSeries as $idx => $ls)
                    <polyline class="pt-line" style="stroke:{{ $ls['color'] }}" points="{{ $linePolys[$idx] }}"/>
                @endforeach
                @foreach($lineSeries as $idx => $ls)
                    @php $lab = $ls['label']; @endphp
                    @foreach($linePoints[$idx] as $p)
                        <circle class="pt-point" style="fill:{{ $ls['color'] }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.4">
                            <title>{{ $lab }} · {{ $p['label'] }}: {{ $p['v'] }}</title>
                        </circle>
                        <text class="pt-value" x="{{ $p['x'] }}" y="{{ $p['y'] - 7 }}" text-anchor="middle">{{ $p['v'] }}</text>
                    @endforeach
                @endforeach
                @foreach($months as $i => $m)
                    <text class="pt-label" x="{{ round($px + $i * $stepX, 1) }}" y="{{ $th + 12 }}" text-anchor="middle">{{ $m }}</text>
                @endforeach
            </svg>
            <div class="pt-legend">
                @foreach($lineSeries as $ls)
                    <span class="pt-legend-item"><i class="pt-legend-dot" style="background:{{ $ls['color'] }}"></i>{{ $ls['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="pt-chart pt-chart-bar">
            <div class="pt-chart-title">Overdue &amp; Completed · Declined</div>
            <svg class="pt-svg" viewBox="0 0 {{ $tw }} {{ $th + 22 }}" preserveAspectRatio="xMidYMid meet" role="img">
                @foreach([0.25, 0.5, 0.75] as $frac)
                    <line class="pt-grid" x1="{{ $px }}" x2="{{ $tw - $px }}" y1="{{ $th - $py - $frac * ($th - $py * 2) }}" y2="{{ $th - $py - $frac * ($th - $py * 2) }}"/>
                @endforeach
                @foreach($trendData as $mi => $m)
                    @php
                        $cx = $px + $mi * $barStep;
                        $bx = 0;
                    @endphp
                    @foreach($barSeries as $key => $bs)
                        @php
                            $val = (int) ($m[$key] ?? 0);
                            $barH = ($val / $barMax) * ($th - $py * 2);
                            $yy = $th - $py - $barH;
                            $bxi = $cx - ($barStep / 2) + $barW * $loop->index + $barW * 0.25;
                        @endphp
                        <rect class="pt-bar" style="fill:{{ $bs['color'] }}" x="{{ $bxi }}" y="{{ $yy }}" width="{{ max(4, $barW * 0.8) }}" height="{{ max(0, $barH) }}" rx="2">
                            <title>{{ $bs['label'] }} · {{ $m['label'] }}: {{ $val }}</title>
                        </rect>
                        <text class="pt-value" x="{{ $bxi + $barW * 0.4 }}" y="{{ $yy - 4 }}" text-anchor="middle">{{ $val }}</text>
                    @endforeach
                @endforeach
                @foreach($months as $i => $m)
                    <text class="pt-label" x="{{ round($px + $i * $barStep, 1) }}" y="{{ $th + 12 }}" text-anchor="middle">{{ $m }}</text>
                @endforeach
            </svg>
            <div class="pt-legend">
                @foreach($barSeries as $bs)
                    <span class="pt-legend-item"><i class="pt-legend-dot" style="background:{{ $bs['color'] }}"></i>{{ $bs['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>
</section>

@once
    <style>
        .pt-card{background:#fff;border:1px solid #e7e9ef;border-radius:14px;padding:16px}
        .pt-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
        .pt-title{font-size:13px;font-weight:900;color:#101828}
        .pt-sub{font-size:9px;color:#667085;font-weight:750;margin-top:2px}
        .pt-viewing{font-weight:850;color:#344054}
        .pt-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin-bottom:14px}
        .pt-stat{border:1px solid #e6e9ef;border-radius:11px;background:#fcfcfd;padding:9px 10px;display:flex;flex-direction:column;gap:3px}
        .pt-stat-dot{width:8px;height:8px;border-radius:99px;display:inline-block}
        .pt-stat-label{font-size:7px;color:#667085;font-weight:800;text-transform:uppercase;letter-spacing:.03em;line-height:1.25}
        .pt-stat-value{font-size:17px;font-weight:950;color:#101828}
        .pt-charts{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .pt-chart{border:1px solid #eef0f3;border-radius:12px;padding:12px;background:#fff;min-width:0}
        .pt-chart-title{font-size:9px;font-weight:900;color:#344054;text-transform:uppercase;letter-spacing:.03em;margin-bottom:8px}
        .pt-svg{width:100%;height:auto;display:block}
        .pt-grid{stroke:#f1f2f4;stroke-width:1}
        .pt-line{fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .pt-point{stroke:#fff;stroke-width:1.4;cursor:pointer}
        .pt-bar{cursor:pointer}
        .pt-label{font-size:7.5px;fill:#98a2b3;font-weight:700}
        .pt-value{font-size:7px;fill:#344054;font-weight:900}
        .pt-legend{display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;font-size:7.5px;color:#667085;font-weight:750}
        .pt-legend-item{display:inline-flex;align-items:center;gap:5px}
        .pt-legend-dot{width:8px;height:8px;border-radius:2px;display:inline-block}
        @media (max-width:900px){
            .pt-charts{grid-template-columns:1fr}
            .pt-summary{grid-template-columns:repeat(3,minmax(0,1fr))}
        }
        @media (max-width:520px){
            .pt-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
    </style>
@endonce
