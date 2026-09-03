{{-- Shared Performance Trend — reused by Designer, Designer Head and BD dashboards.
     Expects:
       $trendCards  : array of ['label' => string, 'value' => int, 'color' => string] — selected-period metrics (5)
       $trendData   : collection of monthly rows ['label', 'assigned', 'in_progress', 'completed', 'overdue_completed', 'declined']
       $trendContext: string shown near the title, e.g. "sk_designer • Sep 2026"
       $expandable  : bool — shows an expand icon per chart opening a large modal (BD & Designer Head only)
     Ratings are intentionally NOT part of this section. --}}

@php $expandable = $expandable ?? false; @endphp

@php
    $trendData = $trendData ?? collect();
    $trendData = collect($trendData)->values();
    $trendCards = $trendCards ?? [];
    $cards = collect($trendCards);
    $months = $trendData->pluck('label');
    $cardColors = $cards->pluck('color', 'label');

    $tw = 620; $th = 160; $thLine = 220; $px = 40; $py = 22;
    $n = max(2, $trendData->count());
    $stepX = ($tw - $px * 2) / ($n - 1);
    $lineSeries = [
        'assigned'  => ['label' => 'Assigned',     'color' => '#2970ff', 'dash' => false],
        'in_progress'=> ['label' => 'In Progress', 'color' => '#7c3aed', 'dash' => false],
        'completed' => ['label' => 'Completed',    'color' => '#027a48', 'dash' => false],
    ];
    $lineMax = max(1, (int) collect($lineSeries)->map(fn ($s) => $trendData->max($s['label'] === 'In Progress' ? 'in_progress' : ($s['label'] === 'Completed' ? 'completed' : 'assigned')))->max());

    $linePoints = collect($lineSeries)->map(function ($s) use ($trendData, $px, $py, $thLine, $stepX, $lineMax) {
        $key = $s['label'] === 'In Progress' ? 'in_progress' : ($s['label'] === 'Completed' ? 'completed' : 'assigned');
        return $trendData->values()->map(function ($m, $i) use ($key, $px, $py, $thLine, $stepX, $lineMax) {
            return [
                'x' => round($px + $i * $stepX, 1),
                'y' => round($thLine - $py - (($m[$key] ?? 0) / $lineMax) * ($thLine - $py * 2), 1),
                'v' => (int) ($m[$key] ?? 0),
                'label' => $m['label'] ?? '',
            ];
        });
    });
    $linePolys = $linePoints->map(fn ($pts) => $pts->map(fn ($p) => $p['x'].','.$p['y'])->implode(' '));
    $yTicks = collect([0, 0.25, 0.5, 0.75, 1])->map(fn ($f) => [
        'y' => round($thLine - $py - $f * ($thLine - $py * 2), 1),
        'v' => (int) round($f * $lineMax),
    ]);
    $monthTooltips = $trendData->values()->map(fn ($m) => trim(($m['label'] ?? '')
        ."\nAssigned: ".(int) ($m['assigned'] ?? 0)
        ."\nIn Progress: ".(int) ($m['in_progress'] ?? 0)
        ."\nCompleted: ".(int) ($m['completed'] ?? 0)));

    $barSeries = [
        'overdue_completed' => ['label' => 'Overdue & Completed', 'color' => '#f79009'],
        'declined'          => ['label' => 'Declined',            'color' => '#c01048'],
    ];
    $barMax = max(1, (int) collect($barSeries)->map(fn ($b, $key) => $trendData->max($key))->max());
    $barW = ($tw - $px * 2) / ($n * 2.6);
    $barStep = ($tw - $px * 2) / $n;
@endphp

<div class="pt-wrap" @if($expandable) x-data="{ ptExpandModal: false, ptExpandChart: 'line' }" @endif>
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
            <div class="pt-chart-head">
                <div class="pt-chart-title">Assigned · In Progress · Completed</div>
                @if($expandable)
                <button type="button" class="pt-expand" @click="ptExpandModal=true;ptExpandChart='line'" aria-label="Expand chart"><span class="pt-expand-ico">⤢</span></button>
                @endif
            </div>
            <svg class="pt-svg" viewBox="0 0 {{ $tw }} {{ $thLine + 22 }}" preserveAspectRatio="xMidYMid meet" role="img">
                @foreach($yTicks as $tick)
                    <line class="pt-grid" x1="{{ $px }}" x2="{{ $tw - $px }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"/>
                    <text class="pt-axis-label" x="{{ $px - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['v'] }}</text>
                @endforeach
                @foreach($lineSeries as $idx => $ls)
                    <polyline class="pt-line" style="stroke:{{ $ls['color'] }}" points="{{ $linePolys[$idx] }}"/>
                @endforeach
                @foreach($lineSeries as $idx => $ls)
                    @foreach($linePoints[$idx] as $p)
                        <circle class="pt-point" style="fill:{{ $ls['color'] }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4"/>
                        <text class="pt-value" x="{{ $p['x'] }}" y="{{ $p['y'] - 8 }}" text-anchor="middle">{{ $p['v'] }}</text>
                    @endforeach
                @endforeach
                @foreach($months as $i => $m)
                    <text class="pt-label" x="{{ round($px + $i * $stepX, 1) }}" y="{{ $thLine + 14 }}" text-anchor="middle">{{ $m }}</text>
                @endforeach
                @foreach($trendData as $i => $m)
                    <rect class="pt-hover-strip" x="{{ round($px + $i * $stepX - $stepX / 2, 1) }}" y="0" width="{{ round($stepX, 1) }}" height="{{ $thLine }}" fill="transparent"><title>{{ $monthTooltips[$i] }}</title></rect>
                @endforeach
            </svg>
            <div class="pt-legend">
                @foreach($lineSeries as $ls)
                    <span class="pt-legend-item"><i class="pt-legend-dot" style="background:{{ $ls['color'] }}"></i>{{ $ls['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="pt-chart pt-chart-bar">
            <div class="pt-chart-head">
                <div class="pt-chart-title">Overdue &amp; Completed · Declined</div>
                @if($expandable)
                <button type="button" class="pt-expand" @click="ptExpandModal=true;ptExpandChart='bar'" aria-label="Expand chart"><span class="pt-expand-ico">⤢</span></button>
                @endif
            </div>
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

@if($expandable)
@php
    $ltw = 940; $lth = 300; $lpx = 56; $lpy = 34;
    $ln = max(2, $trendData->count());
    $lstepX = ($ltw - $lpx * 2) / ($ln - 1);
    $lLineMax = $lineMax;
    $lLinePoints = collect($lineSeries)->map(function ($s) use ($trendData, $lpx, $lpy, $lth, $lstepX, $lLineMax) {
        $key = $s['label'] === 'In Progress' ? 'in_progress' : ($s['label'] === 'Completed' ? 'completed' : 'assigned');
        return $trendData->values()->map(function ($m, $i) use ($key, $lpx, $lpy, $lth, $lstepX, $lLineMax) {
            return [
                'x' => round($lpx + $i * $lstepX, 1),
                'y' => round($lth - $lpy - (($m[$key] ?? 0) / $lLineMax) * ($lth - $lpy * 2), 1),
                'v' => (int) ($m[$key] ?? 0),
                'label' => $m['label'] ?? '',
            ];
        });
    });
    $lLinePolys = $lLinePoints->map(fn ($pts) => $pts->map(fn ($p) => $p['x'].','.$p['y'])->implode(' '));
    $lBarStep = ($ltw - $lpx * 2) / $ln;
    $lBarW = ($ltw - $lpx * 2) / ($ln * 2.6);
    $lYTicks = collect([0, 0.25, 0.5, 0.75, 1])->map(fn ($f) => [
        'y' => round($lth - $lpy - $f * ($lth - $lpy * 2), 1),
        'v' => (int) round($f * $lLineMax),
    ]);
@endphp
<div
    class="pt-modal-overlay"
    x-show="ptExpandModal"
    x-cloak
    x-transition.opacity
    @click="ptExpandModal=false"
    @keydown.escape.window="ptExpandModal=false"
    role="dialog"
    aria-modal="true"
>
    <div class="pt-modal-box" @click.stop>
        <div class="pt-modal-head">
            <div>
                <div class="pt-modal-title" x-text="ptExpandChart==='line' ? 'Assigned · In Progress · Completed' : 'Overdue &amp; Completed · Declined'"></div>
                <div class="pt-modal-sub"><span class="pt-viewing">Viewing:</span> {{ $trendContext }}</div>
            </div>
            <button type="button" class="pt-modal-close" @click="ptExpandModal=false" aria-label="Close">✕</button>
        </div>
        <div class="pt-modal-body">
            <div class="pt-modal-chart" x-show="ptExpandChart==='line'" x-cloak>
                <svg class="pt-svg" viewBox="0 0 {{ $ltw }} {{ $lth + 30 }}" preserveAspectRatio="xMidYMid meet" role="img">
                    @foreach($lYTicks as $tick)
                        <line class="pt-grid" x1="{{ $lpx }}" x2="{{ $ltw - $lpx }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"/>
                        <text class="pt-axis-label" x="{{ $lpx - 8 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end">{{ $tick['v'] }}</text>
                    @endforeach
                    @foreach($lineSeries as $idx => $ls)
                        <polyline class="pt-line" style="stroke:{{ $ls['color'] }}" points="{{ $lLinePolys[$idx] }}"/>
                    @endforeach
                    @foreach($lineSeries as $idx => $ls)
                        @foreach($lLinePoints[$idx] as $p)
                            <circle class="pt-point" style="fill:{{ $ls['color'] }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4.5"/>
                            <text class="pt-value" x="{{ $p['x'] }}" y="{{ $p['y'] - 9 }}" text-anchor="middle">{{ $p['v'] }}</text>
                        @endforeach
                    @endforeach
                    @foreach($months as $i => $m)
                        <text class="pt-label" x="{{ round($lpx + $i * $lstepX, 1) }}" y="{{ $lth + 16 }}" text-anchor="middle">{{ $m }}</text>
                    @endforeach
                    @foreach($trendData as $i => $m)
                        <rect class="pt-hover-strip" x="{{ round($lpx + $i * $lstepX - $lstepX / 2, 1) }}" y="0" width="{{ round($lstepX, 1) }}" height="{{ $lth }}" fill="transparent"><title>{{ $monthTooltips[$i] }}</title></rect>
                    @endforeach
                </svg>
                <div class="pt-legend">
                    @foreach($lineSeries as $ls)
                        <span class="pt-legend-item"><i class="pt-legend-dot" style="background:{{ $ls['color'] }}"></i>{{ $ls['label'] }}</span>
                    @endforeach
                </div>
            </div>

            <div class="pt-modal-chart" x-show="ptExpandChart==='bar'" x-cloak>
                <svg class="pt-svg" viewBox="0 0 {{ $ltw }} {{ $lth + 30 }}" preserveAspectRatio="xMidYMid meet" role="img">
                    @foreach([0.25, 0.5, 0.75] as $frac)
                        <line class="pt-grid" x1="{{ $lpx }}" x2="{{ $ltw - $lpx }}" y1="{{ $lth - $lpy - $frac * ($lth - $lpy * 2) }}" y2="{{ $lth - $lpy - $frac * ($lth - $lpy * 2) }}"/>
                    @endforeach
                    @foreach($trendData as $mi => $m)
                        @php $lcx = $lpx + $mi * $lBarStep; @endphp
                        @foreach($barSeries as $key => $bs)
                            @php
                                $lval = (int) ($m[$key] ?? 0);
                                $lbarH = ($lval / $barMax) * ($lth - $lpy * 2);
                                $lyy = $lth - $lpy - $lbarH;
                                $lbxi = $lcx - ($lBarStep / 2) + $lBarW * $loop->index + $lBarW * 0.25;
                            @endphp
                            <rect class="pt-bar" style="fill:{{ $bs['color'] }}" x="{{ $lbxi }}" y="{{ $lyy }}" width="{{ max(5, $lBarW * 0.8) }}" height="{{ max(0, $lbarH) }}" rx="3">
                                <title>{{ $bs['label'] }} · {{ $m['label'] }}: {{ $lval }}</title>
                            </rect>
                            <text class="pt-value" x="{{ $lbxi + $lBarW * 0.4 }}" y="{{ $lyy - 6 }}" text-anchor="middle">{{ $lval }}</text>
                        @endforeach
                    @endforeach
                    @foreach($months as $i => $m)
                        <text class="pt-label" x="{{ round($lpx + $i * $lBarStep, 1) }}" y="{{ $lth + 16 }}" text-anchor="middle">{{ $m }}</text>
                    @endforeach
                </svg>
                <div class="pt-legend">
                    @foreach($barSeries as $bs)
                        <span class="pt-legend-item"><i class="pt-legend-dot" style="background:{{ $bs['color'] }}"></i>{{ $bs['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>

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
        .pt-charts{display:grid;grid-template-columns:1fr;gap:14px}
        .pt-axis-label{font-size:7.5px;fill:#98a2b3;font-weight:700}
        .pt-chart{border:1px solid #eef0f3;border-radius:12px;padding:12px;background:#fff;min-width:0}
        .pt-chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
        .pt-chart-title{font-size:9px;font-weight:900;color:#344054;text-transform:uppercase;letter-spacing:.03em;margin-bottom:8px}
        .pt-expand{flex:0 0 auto;border:1px solid #eef0f3;background:#fcfcfd;color:#98a2b3;border-radius:8px;width:26px;height:26px;display:grid;place-items:center;cursor:pointer;font-size:13px;line-height:1;padding:0;transition:.15s}
        .pt-expand:hover{border-color:#d0d5dd;color:#344054;background:#fff}
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
        .pt-modal-overlay{position:fixed;inset:0;z-index:100000;background:rgba(17,24,39,.58);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px}
        .pt-modal-box{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 28px 80px rgba(15,23,42,.28);width:min(96vw,1080px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden}
        .pt-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
        .pt-modal-title{font-size:15px;font-weight:900;color:#101828}
        .pt-modal-sub{font-size:9px;color:#667085;margin-top:3px}
        .pt-modal-close{flex:0 0 auto;border:1px solid #d0d5dd;background:#fff;color:#475467;border-radius:10px;width:32px;height:32px;display:grid;place-items:center;cursor:pointer;font-size:13px;line-height:1;padding:0;transition:.15s}
        .pt-modal-close:hover{background:#f2f4f7;color:#101828}
        .pt-modal-body{padding:18px;overflow:auto}
        .pt-modal-chart .pt-svg{width:100%}
        .pt-modal-chart .pt-label{font-size:10px}
        .pt-modal-chart .pt-value{font-size:9px}
        .pt-modal-chart .pt-axis-label{font-size:9px}
        .pt-modal-chart .pt-legend{font-size:9px}
        @media (max-width:900px){
            .pt-summary{grid-template-columns:repeat(3,minmax(0,1fr))}
        }
        @media (max-width:520px){
            .pt-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
    </style>
@endonce
