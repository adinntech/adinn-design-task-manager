@php
    $pbPct = isset($percentage) ? max(0, min(100, (int) $percentage)) : 0;
    $pbFill = match (true) {
        $pbPct >= 81  => '#15803d',
        $pbPct >= 51  => '#4ade80',
        $pbPct >= 31  => '#d97706',
        default       => '#ea580c',
    };
    $pbHeight = $height ?? '100%';
@endphp
<div class="ad-progress-fill" style="height:{{ $pbHeight }};width:{{ $pbPct }}%;background:{{ $pbFill }};border-radius:999px;transition:width .25s ease"></div>