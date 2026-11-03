@extends('layouts.app')

@section('title','Designer Dashboard')
@section('workspace-title','Designer Dashboard')
@section('workspace-subtitle','A simple overview of your tasks, requests and deadlines')

@section('content')
<style>
    .bd-dashboard{display:flex;flex-direction:column;gap:14px}
    .bd-dash-head{display:flex;align-items:center;justify-content:space-between;gap:14px}
    .bd-dash-head h1{margin:0;font-size:21px;font-weight:900;color:#101828}
    .bd-dash-head p{margin:4px 0 0;font-size:10px;color:#667085}
    .bd-dash-actions{display:flex;gap:8px}
    .bd-dash-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:9px;font-weight:900}
    .bd-dash-btn.primary{background:#e30613;color:#fff}
    .bd-dash-btn.secondary{background:#fff;color:#344054;border:1px solid #d0d5dd}

    .bd-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px}
    .bd-kpis.bd-kpis-8{grid-template-columns:repeat(8,minmax(0,1fr))}
    .bd-kpis.bd-kpis-9{grid-template-columns:repeat(9,minmax(0,1fr))}
    .bd-kpi{background:#fff;border:1px solid #e4e7ec;border-radius:12px;padding:12px 13px}
    .bd-kpi-link{display:block;text-decoration:none;color:inherit;cursor:pointer;transition:.15s}
    .bd-kpi-link:hover{border-color:#e30613;box-shadow:0 4px 14px rgba(227,6,19,.1);transform:translateY(-1px)}
    .bd-kpi-label{font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.045em;color:#667085}
    .bd-kpi-value{font-size:22px;font-weight:950;color:#101828;margin-top:5px}
    .bd-kpi-note{font-size:8px;color:#98a2b3;margin-top:3px}

    .bd-card{background:#fff;border:1px solid #e4e7ec;border-radius:13px;overflow:hidden}
    .bd-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;border-bottom:1px solid #eaecf0;background:#fcfcfd}
    .bd-card-title{font-size:11px;font-weight:900;color:#101828}
    .bd-card-link{font-size:8px;font-weight:900;text-decoration:none;color:#e30613}
    .bd-card-body{padding:12px}

    .bd-table-wrap{overflow:auto;max-height:480px}
    .bd-table{width:100%;border-collapse:collapse;min-width:900px}
    .bd-table th{padding:8px 9px;text-align:left;border-bottom:1px solid #eaecf0;font-size:8px;color:#667085;text-transform:uppercase;letter-spacing:.035em;font-weight:900;position:sticky;top:0;background:#fff;z-index:1}
    .bd-table td{padding:9px;border-bottom:1px solid #f1f2f4;font-size:9px;color:#344054;vertical-align:middle}
    .bd-table tbody tr:last-child td{border-bottom:0}
    .bd-task-link{font-weight:900;color:#101828;text-decoration:none}
    .bd-task-link:hover{color:#e30613}

    .bd-pill{display:inline-flex;align-items:center;min-height:20px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;white-space:nowrap}
    .pill-assigned{background:#f2f4f7;color:#475467}
    .pill-progress{background:#eef4ff;color:#3538cd}
    .pill-completed{background:#ecfdf3;color:#027a48}
    .pill-waiting{background:#f4f0ff;color:#6938ef}
    .pill-rework{background:#fff6ed;color:#b54708}
    .pill-overdue{background:#fff1f3;color:#c01048}
    .pill-approved{background:#ecfdf3;color:#027a48}
    .pill-rejected{background:#fff1f3;color:#c01048}
    .pill-default{background:#f9fafb;color:#475467;border:1px solid #eaecf0}

    .bd-lower{display:grid;grid-template-columns:.8fr 1fr 1.1fr;gap:10px}
    .bd-req-history{padding:11px 0;border-bottom:1px solid #f2f4f7}
    .bd-req-history:last-child{border-bottom:0}
    .bd-req-history-top{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
    .bd-req-history-title{font-size:10px;font-weight:900;color:#101828}
    .bd-req-history-type{font-size:8px;color:#667085;margin-top:2px}
    .bd-req-history-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:3px 14px;margin-top:7px}
    .bd-req-history-line{font-size:8px;color:#667085}
    .bd-req-history-line b{color:#344054;font-weight:800}
    .bd-request-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px solid #f2f4f7}
    .bd-request-row:last-child{border-bottom:0}
    .bd-request-name{font-size:9px;font-weight:850;color:#344054}
    .bd-request-count{font-size:16px;font-weight:950;color:#101828}
    .bd-request-meta{font-size:8px;color:#98a2b3;margin-top:2px}

    .bd-status-row{display:grid;grid-template-columns:135px 36px 1fr;align-items:center;gap:8px;padding:5px 0}
    .bd-status-label{font-size:8px;color:#475467;font-weight:750}
    .bd-status-count{font-size:8px;font-weight:900;text-align:right;color:#101828}
    .bd-bar{height:6px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .bd-bar > span{display:block;height:100%;border-radius:999px;background:#e30613}

    .bd-recent-request{padding:9px 0;border-bottom:1px solid #f2f4f7}
    .bd-recent-request:last-child{border-bottom:0}
    .bd-recent-request-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
    .bd-recent-request-title{font-size:9px;font-weight:900;color:#101828}
    .bd-recent-request-sub{font-size:8px;color:#667085;margin-top:3px}
    .bd-request-state{font-size:7px;font-weight:900;padding:3px 6px;border-radius:999px;background:#fffaeb;color:#b54708}

    .bd-empty{text-align:center;color:#98a2b3;font-size:9px;padding:20px 8px}

    .bd-scroll-y{max-height:520px;overflow-y:auto}

    .bd-progress-card{display:flex;align-items:center;gap:14px;padding:14px 16px}
    .bd-progress-label{font-size:9px;font-weight:900;color:#344054;white-space:nowrap}
    .bd-progress-track{flex:1;height:10px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .bd-progress-value{font-size:13px;font-weight:950;color:#101828;min-width:34px;text-align:right}

    .bd-donut-wrap{display:flex;align-items:center;gap:14px;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #f2f4f7;flex-wrap:wrap}
    .bd-donut-legend{display:flex;flex-direction:column;gap:4px;flex:1;min-width:120px}
    .bd-donut-legend-row{display:flex;align-items:center;gap:6px;font-size:8px;color:#475467}
    .bd-donut-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
    .bd-donut-legend-count{margin-left:auto;font-weight:900;color:#101828}

    .bd-line-chart{width:100%;height:auto;display:block}
    .bd-line-chart .grid-line{stroke:#f1f2f4;stroke-width:1}
    .bd-line-chart .trend-line{fill:none;stroke:#e30613;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .bd-line-chart .trend-point{fill:#e30613;stroke:#fff;stroke-width:1.5}
    .bd-line-chart .trend-label{font-size:7px;fill:#98a2b3;font-weight:700}
    .bd-line-chart .trend-value{font-size:7px;fill:#344054;font-weight:900}

    .bd-hist-row{margin-bottom:10px}
    .bd-hist-row:last-child{margin-bottom:0}
    .bd-hist-top{display:flex;justify-content:space-between;font-size:8px;font-weight:850;color:#344054;margin-bottom:4px}
    .bd-hist-track{display:flex;height:10px;border-radius:999px;overflow:hidden;background:#f2f4f7}
    .bd-hist-track span{height:100%}
    .bd-hist-legend{display:flex;gap:10px;margin-top:10px;font-size:7px;color:#667085}
    .bd-hist-legend span{display:inline-flex;align-items:center;gap:4px}
    .bd-hist-legend i{width:7px;height:7px;border-radius:2px;display:inline-block}

    .bd-reviews-wrap{overflow:hidden}
    .bd-reviews-track{display:flex;gap:12px;width:max-content;animation:bd-reviews-scroll linear infinite}
    .bd-reviews-track.bd-reviews-static{animation:none}
    .bd-reviews-wrap:hover .bd-reviews-track{animation-play-state:paused}
    @keyframes bd-reviews-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .bd-review-card{flex:0 0 260px;background:#fcfcfd;border:1px solid #eaecf0;border-radius:10px;padding:12px}
    .bd-review-stars{display:flex;gap:2px;margin-bottom:6px}
    .bd-review-star{--star-fill:0%;display:inline-block;width:13px;height:13px;flex:0 0 13px;font-size:13px;line-height:13px;font-family:Arial,"Segoe UI Symbol",sans-serif;background:linear-gradient(90deg,#f5b301 0%,#f5b301 var(--star-fill),#d8dee8 var(--star-fill),#d8dee8 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
    .bd-review-comment{font-size:9px;color:#344054;line-height:1.5;margin-bottom:8px;min-height:36px}
    .bd-review-meta{font-size:7px;color:#98a2b3;margin-top:2px}

    @media(max-width:1200px){.bd-kpis,.bd-kpis.bd-kpis-8,.bd-kpis.bd-kpis-9{grid-template-columns:repeat(3,1fr)}.bd-lower{grid-template-columns:1fr 1fr}}
    @media(max-width:760px){.bd-dash-head{align-items:flex-start;flex-direction:column}.bd-kpis,.bd-kpis.bd-kpis-8,.bd-kpis.bd-kpis-9{grid-template-columns:repeat(2,1fr)}.bd-lower{grid-template-columns:1fr}}
    :root{--dsh-star:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Cpath d=%27M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.2L12 17.9 5.6 21.5 7 14.3 1.7 9.3l7.2-.9z%27/%3E%3C/svg%3E")}
    .dsh-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:170px;padding:18px 20px;border:1px solid #eef0f3;border-radius:16px;background:#fff;overflow:hidden}
    .dsh-hero-text{min-width:0;position:relative;z-index:1}
    .dsh-eyebrow{display:flex;align-items:center;gap:8px;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#667085}
    .dsh-eyebrow span{width:26px;height:3px;border-radius:3px;background:#e30613}
    .dsh-hero h1{margin:8px 0 0;font-size:26px;line-height:1.15;font-weight:900;color:#101828}
    .dsh-hero h1 em{font-style:normal;color:#e30613}
    .dsh-hero p{margin:6px 0 0;font-size:11px;color:#667085}
    .dsh-login{display:flex;align-items:center;gap:7px;margin-top:10px;font-size:10px;color:#667085}
    .dsh-login svg{width:14px;height:14px;flex:none}
    .dsh-login strong{color:#344054}
    .dsh-hero-art{position:absolute;right:215px;top:0;height:100%;width:auto;max-width:48%;object-fit:contain;object-position:right center;mix-blend-mode:multiply;pointer-events:none;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 18%);mask-image:linear-gradient(90deg,transparent 0,#000 18%)}
    .dsh-actions{display:flex;gap:9px;position:relative;z-index:1;flex:none}
    .dsh-btn{display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:8px 14px;border-radius:10px;text-decoration:none;font-size:11px;font-weight:800;transition:.15s}
    .dsh-btn svg{width:15px;height:15px;flex:none}
    .dsh-btn.primary{background:#e30613;color:#fff;box-shadow:0 6px 14px rgba(227,6,19,.22)}
    .dsh-btn.primary:hover{background:#c8050f}
    .dsh-btn.secondary{background:#fff;color:#344054;border:1px solid #e4e7ec;box-shadow:0 1px 3px rgba(16,24,40,.06)}
    .dsh-btn.secondary:hover{border-color:#e30613;color:#e30613}

    .dsh-primary{display:grid;grid-template-columns:1.55fr repeat(6,minmax(0,1fr));gap:10px}
    .dsh-secondary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .dsh-extra{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
    .dsh-card{--t1:#f8fafc;--t2:#f8fafc;--ic:#475467;--icbg:#eef1f5;display:flex;align-items:center;gap:11px;min-width:0;padding:13px 14px;border-radius:14px;border:1px solid rgba(16,24,40,.07);background:linear-gradient(135deg,var(--t1),var(--t2));color:inherit;text-decoration:none;box-shadow:0 1px 3px rgba(16,24,40,.05);transition:.15s}
    .dsh-card.is-link{cursor:pointer}
    .dsh-card.is-link:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(16,24,40,.1);border-color:rgba(227,6,19,.35)}
    .dsh-icon{display:grid;place-items:center;width:40px;height:40px;flex:none;border-radius:11px;background:var(--icbg);color:var(--ic)}
    .dsh-icon svg{width:21px;height:21px}
    .dsh-label{font-size:10.5px;font-weight:700;color:#475467;line-height:1.25}
    .dsh-value{font-size:24px;font-weight:900;line-height:1.1;color:#101828;margin-top:3px}
    .dsh-value small{font-size:13px;font-weight:700;color:#667085;margin-left:1px}
    .dsh-note{font-size:9px;color:rgba(255,255,255,.8);margin-top:3px}
    .dsh-total{background:linear-gradient(135deg,#e30613,#b8040f);border-color:#b8040f;color:#fff;box-shadow:0 12px 24px rgba(227,6,19,.25)}
    .dsh-total .dsh-icon{background:rgba(255,255,255,.2);color:#fff}
    .dsh-total .dsh-label{color:rgba(255,255,255,.92)}
    .dsh-total .dsh-value{color:#fff;font-size:30px}
    .dsh-flat{background:#fff;border-color:#eceff4}
    .dsh-blue{--t1:#eaf4ff;--t2:#f3f9ff;--ic:#2563eb;--icbg:#d9eaff}
    .dsh-green{--t1:#e9f9ee;--t2:#f3fcf6;--ic:#16a34a;--icbg:#d3f3de}
    .dsh-purple{--t1:#f1ecff;--t2:#f8f5ff;--ic:#7c3aed;--icbg:#e4daff}
    .dsh-orange{--t1:#fff1e2;--t2:#fff8ef;--ic:#ea580c;--icbg:#ffe0c2}
    .dsh-pink{--t1:#ffeaf1;--t2:#fff5f8;--ic:#e11d48;--icbg:#ffd6e3}
    .dsh-teal{--t1:#e6f8f1;--t2:#f1fcf8;--ic:#059669;--icbg:#ccf2e4}
    .dsh-amber{--t1:#fff6e0;--t2:#fffaf0;--ic:#d97706;--icbg:#ffe8b8}
    .dsh-red{--t1:#ffeceb;--t2:#fff5f4;--ic:#dc2626;--icbg:#ffd9d6}
    .dsh-flat.dsh-blue,.dsh-flat.dsh-green,.dsh-flat.dsh-purple,.dsh-flat.dsh-orange,.dsh-flat.dsh-pink,.dsh-flat.dsh-teal,.dsh-flat.dsh-amber,.dsh-flat.dsh-red{background:linear-gradient(135deg,#fff,var(--t2))}
    .dsh-rating{display:flex;align-items:center;gap:8px;margin-top:3px}
    .dsh-rating .dsh-value{margin:0}
    .dsh-stars{display:inline-flex;gap:2px}
    .dsh-stars i{width:17px;height:17px;display:block;background:linear-gradient(90deg,#f59e0b var(--f),#e1e5ec var(--f));-webkit-mask:var(--dsh-star) center/contain no-repeat;mask:var(--dsh-star) center/contain no-repeat}
    .dsh-completion{display:flex;align-items:center;gap:14px;padding:14px 18px;border:1px solid #eceff4;border-radius:14px;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.05)}
    .dsh-completion-label{font-size:12px;font-weight:900;color:#101828;white-space:nowrap}
    .dsh-completion .bd-progress-track{height:11px}
    .dsh-completion-value{min-width:52px;text-align:center;padding:6px 10px;border-radius:10px;background:#fff1e0;font-size:14px;font-weight:900;color:#101828}
    @media(max-width:1280px){.dsh-primary{grid-template-columns:repeat(4,minmax(0,1fr))}.dsh-total{grid-column:span 2}.dsh-extra{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:960px){.dsh-hero{flex-direction:column;align-items:flex-start}.dsh-hero-art{display:none}.dsh-secondary{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:640px){.dsh-hero h1{font-size:21px}.dsh-actions{width:100%}.dsh-btn{flex:1;justify-content:center}.dsh-primary,.dsh-secondary,.dsh-extra{grid-template-columns:repeat(2,minmax(0,1fr))}.dsh-total{grid-column:span 2}.dsh-card{padding:11px}.dsh-completion{flex-wrap:wrap}.dsh-completion .bd-progress-track{order:3;flex-basis:100%}}
</style>

<div class="bd-dashboard">
    @php
        $ic = [
            'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
            'user' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
            'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
            'loader' => '<path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/>',
            'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
            'check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'printer' => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'shuffle' => '<path d="M2 18h1.4c1.3 0 2.5-.6 3.3-1.7l6.1-8.6c.7-1.1 2-1.7 3.3-1.7H22"/><path d="m18 2 4 4-4 4"/><path d="M2 6h1.9c1.5 0 2.9.9 3.6 2.2"/><path d="M22 18h-5.9c-1.3 0-2.6-.7-3.3-1.8l-.5-.8"/><path d="m18 14 4 4-4 4"/>',
            'x' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
            'split' => '<path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M12 22v-8.3a4 4 0 0 0-1.172-2.872L3 3"/><path d="m15 9 6-6"/>',
            'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            'tasks' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
            'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        ];
        $svg = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$ic[$name].'</svg>';
        $focus = fn (string $key) => route('designer.tasks.index', ['focus' => $key]);

        $primaryCards = [
            ['Assigned', $stats['assigned'], $focus('assigned_tasks'), 'user', 'blue', 'Awaiting your action'],
            ['Ready to Start', $stats['ready_to_start'], $focus('yet_to_start'), 'play', 'green', 'Ready to begin work'],
            ['In Progress', $stats['in_progress'], $focus('in_progress'), 'loader', 'purple', 'Currently being worked on'],
            ['Rework', $stats['rework'], $focus('rework'), 'refresh', 'orange', 'Tasks currently in rework'],
            ['Rework Creatives', $stats['rework_creatives'], null, 'image', 'pink', 'Creatives pending resubmission'],
            ['Completed', $stats['completed'], $focus('completed'), 'check', 'teal', 'Finished tasks'],
        ];
        $secondaryCards = [
            ['Waiting for BD Review', $stats['waiting_bd_review'], $focus('waiting_confirmation'), 'clock', 'purple', 'Completed by you, awaiting BD'],
            ['Waiting for BD Confirmation', $stats['waiting_bd_confirmation'], null, 'shield', 'blue', 'New tickets you created, pending BD approval'],
            ['Prepare Printing File Tasks', $stats['prepare_printing_file'], $focus('prepare_printing_file'), 'printer', 'purple', 'Assigned back to you for printing files'],
        ];
        $extraCards = [
            ['Swapped Tasks', $requestTypeCounts['swap'], $focus('swap_tasks'), 'shuffle', 'blue', 'Swap requests raised by you'],
            ['Declined Tasks', $requestTypeCounts['decline'], $focus('self_declined'), 'x', 'red', 'Decline requests raised by you'],
            ['Split Tasks', $requestTypeCounts['split'], null, 'split', 'blue', 'Split requests raised by you'],
            ['Approved Requests', $requestTypeCounts['approved'], null, 'check', 'green', 'Across swap/split/decline'],
            ['Overall Rework', $overallRework['cycles'], null, 'layers', 'pink', 'All-time rework cycles'],
            ['Overall Rework Creatives', $overallRework['creatives'], null, 'image', 'green', 'All-time creatives sent for rework'],
        ];
        $ratingAvg = $overallRating['average'];
    @endphp

    <div class="dsh-hero">
        <div class="dsh-hero-text">
            <div class="dsh-eyebrow"><span></span>Designer Dashboard</div>
            <h1>Welcome back, <em>{{ auth()->user()->name }}</em></h1>
            <p>All important task and request information in one place.</p>
            <div class="dsh-login">{!! $svg('calendar') !!}<span><strong>Last Login:</strong> {{ optional(auth()->user()->last_login_at)->format('d M Y \• h:i A') ?? 'This is your first login' }}</span></div>
        </div>
        <img class="dsh-hero-art" src="{{ asset('images/dashboard-hero.webp') }}" alt="" aria-hidden="true" loading="eager">
        <div class="dsh-actions">
            <a class="dsh-btn secondary" href="{{ route('designer.tasks.index') }}">{!! $svg('tasks') !!}View All Tasks</a>
            <a class="dsh-btn primary" href="{{ route('designer.tasks.create') }}">{!! $svg('plus') !!}Create Task</a>
        </div>
    </div>

    <div class="dsh-primary">
        <div class="dsh-card dsh-total">
            <span class="dsh-icon">{!! $svg('layers') !!}</span>
            <div><div class="dsh-label">Total Tasks</div><div class="dsh-value">{{ $stats['total'] }}</div><div class="dsh-note">Tasks assigned to you</div></div>
        </div>
        @foreach($primaryCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div></div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="dsh-secondary">
        @foreach($secondaryCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-flat dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div></div>
            </{{ $tag }}>
        @endforeach
        <div class="dsh-card dsh-flat dsh-amber" title="{{ $ratingAvg !== null ? 'Rated '.$overallRating['rated'].' / '.$overallRating['total'].' completed' : 'No ratings yet' }}">
            <span class="dsh-icon">{!! $svg('star') !!}</span>
            <div>
                <div class="dsh-label">Overall Rating</div>
                @if($ratingAvg !== null)
                    <div class="dsh-rating">
                        <span class="dsh-stars">@for($s = 1; $s <= 5; $s++)<i style="--f:{{ max(0, min(1, $ratingAvg - ($s - 1))) * 100 }}%"></i>@endfor</span>
                        <span class="dsh-value">{{ $ratingAvg }}<small>/5</small></span>
                    </div>
                @else
                    <div class="dsh-value">—</div>
                @endif
            </div>
        </div>
    </div>

    <section class="dsh-completion">
        <div class="dsh-completion-label">Completion Rate</div>
        <div class="bd-progress-track"><x-progress-fill :percentage="$completionRate" /></div>
        <div class="dsh-completion-value">{{ $completionRate }}%</div>
    </section>

    <div class="dsh-extra">
        @foreach($extraCards as [$label, $value, $href, $icon, $tone, $note])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @endif class="dsh-card dsh-flat dsh-{{ $tone }}{{ $href ? ' is-link' : '' }}" title="{{ $note }}">
                <span class="dsh-icon">{!! $svg($icon) !!}</span>
                <div><div class="dsh-label">{{ $label }}</div><div class="dsh-value">{{ $value }}</div></div>
            </{{ $tag }}>
        @endforeach
    </div>

    <section class="bd-card">
        <div class="bd-card-head">
            <div class="bd-card-title">Tasks Overview</div>
            <a class="bd-card-link" href="{{ route('designer.tasks.index') }}">View All Tasks</a>
        </div>

        <div class="bd-table-wrap">
            <table class="bd-table">
                <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Project Number</th>
                    <th>Task</th>
                    <th>Client / Agency</th>
                    <th>Vertical</th>
                    <th>Task Nature</th>
                    <th>Assigned By</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentTasks as $task)
                    @php
                        $isOverdue = $task->status !== 'completed' && $task->due_at && $task->due_at->isPast();
                        $statusClass = match($task->status) {
                            'assigned_tasks' => 'pill-assigned',
                            'in_progress' => 'pill-progress',
                            'completed' => 'pill-completed',
                            'waiting_confirmation' => 'pill-waiting',
                            'rework' => 'pill-rework',
                            default => 'pill-default',
                        };
                    @endphp
                    <tr>
                        <td><a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
                        <td>{{ $task->zoho_project_number ?: '-' }}</td>
                        <td>{{ $task->display_task_name ?? $task->task_name }}</td>
                        <td>{{ $task->party_name }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$task->vertical)) }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$task->task_nature)) }}</td>
                        <td>{{ $task->assigner?->name ?? '—' }}</td>
                        <td>
                            <span class="bd-pill {{ $isOverdue ? 'pill-overdue' : $statusClass }}">
                                {{ $isOverdue ? 'Overdue' : ucwords(str_replace('_',' ',$task->status === 'swap_tasks' ? 'swapped_tasks' : $task->status)) }}
                            </span>
                        </td>
                        <td>{{ ucfirst($task->priority) }}</td>
                        <td style="{{ $isOverdue ? 'color:#c01048;font-weight:850' : '' }}">{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="bd-empty">No tasks assigned yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bd-card">
        <div class="bd-card-head" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="bd-card-title">Your Task Details</div>
            <input type="text" id="designer-task-search" style="min-width:240px;border:1px solid #d0d5dd;border-radius:8px;padding:8px 12px;font-size:13px" placeholder="Search tasks, project, client, designer, BD..." autocomplete="off">
        </div>
        <div class="bd-table-wrap" id="designer-task-rows-wrap">
            <table class="bd-table">
                <thead>
                <tr>
                    <th>Task ID</th><th>Project Number</th><th>Task Name</th><th>Assigned At</th><th>Status</th><th>Progress</th>
                    <th>Creatives</th><th>Deadline</th><th>Completed At</th><th>Overdue</th><th>Rework</th><th>Rating</th>
                </tr>
                </thead>
                <tbody id="designer-task-rows">@include('designer.dashboard-task-rows')</tbody>
            </table>
        </div>
    </section>

    <script>
    (function () {
        var input = document.getElementById('designer-task-search');
        var tbody = document.getElementById('designer-task-rows');
        var wrap = document.getElementById('designer-task-rows-wrap');
        var base = "{{ route('designer.dashboard.taskRows') }}";
        var debounceTimer = null;

        function reload() {
            wrap.style.opacity = '0.5';
            fetch(base + '?search=' + encodeURIComponent(input.value), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    tbody.innerHTML = html;
                    wrap.style.opacity = '';
                })
                .catch(function () { wrap.style.opacity = ''; });
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(reload, 350);
        });
    })();
    </script>

    <section class="bd-card">
        <div class="bd-card-head"><div class="bd-card-title">Performance Trend</div></div>
        <div class="bd-card-body">
            @include('shared.performance-trend', [
                'trendCards' => $trendCards,
                'trendData' => $line,
                'trendContext' => $trendContext,
            ])
        </div>
    </section>

    <section class="bd-card">
        <div class="bd-card-head"><div class="bd-card-title">Designer Reviews</div></div>
        <div class="bd-card-body">
            @php $reviewCount = $reviewCards->count(); @endphp
            @if($reviewCount === 0)
                <div class="bd-empty">No BD comments yet on your completed tasks.</div>
            @else
                <div class="bd-reviews-wrap">
                    <div class="bd-reviews-track {{ $reviewCount <= 1 ? 'bd-reviews-static' : '' }}"
                         @if($reviewCount > 1) style="animation-duration:{{ $reviewCount * 6 }}s" @endif>
                        @foreach(($reviewCount > 1 ? $reviewCards->concat($reviewCards) : $reviewCards) as $review)
                            @php $ratingValue = max(0, min(5, $review['rating'])); @endphp
                            <div class="bd-review-card">
                                <div class="bd-review-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        @php $fill = $ratingValue >= $i ? 100 : ($ratingValue >= $i - 0.5 ? 50 : 0); @endphp
                                        <span class="bd-review-star" style="--star-fill:{{ $fill }}%">★</span>
                                    @endfor
                                </div>
                                <div class="bd-review-comment">&ldquo;{{ $review['comment'] }}&rdquo;</div>
                                <div class="bd-review-meta">
                                    @if($review['task'])
                                        <a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $review['task']->status, 'task' => $review['task_id']]) }}">{{ $review['task_id'] }}</a>
                                    @else
                                        {{ $review['task_id'] }}
                                    @endif
                                    · {{ $review['task_name'] }}
                                </div>
                                <div class="bd-review-meta">{{ $review['reviewer'] }}</div>
                                <div class="bd-review-meta">Reviewed: {{ $review['reviewed_at']?->format('d M Y, g:i A') ?? '—' }}</div>
                                <div class="bd-review-meta">Completed: {{ $review['completed_at']?->format('d M Y, g:i A') ?? '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="bd-lower">
        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Requests Overview</div></div>
            <div class="bd-card-body">
                <div class="bd-request-row"><div><div class="bd-request-name">Swap Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['swap'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Split Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['split'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Decline Requests</div><div class="bd-request-meta">Pending approval</div></div><div class="bd-request-count">{{ $requestSummary['decline'] }}</div></div>
                <div class="bd-request-row"><div><div class="bd-request-name">Total Pending</div></div><div class="bd-request-count">{{ array_sum($requestSummary) }}</div></div>
            </div>
        </section>

        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Request Outcomes</div></div>
            <div class="bd-card-body">
                @php $outcomeColors = ['approved' => '#027a48', 'rejected' => '#c01048', 'pending' => '#6938ef']; @endphp
                @foreach($requestOutcomes as $row)
                    @php $rowTotal = $row['approved'] + $row['rejected'] + $row['pending']; @endphp
                    <div class="bd-hist-row">
                        <div class="bd-hist-top"><span>{{ $row['label'] }}</span><span>{{ $rowTotal }} total</span></div>
                        <div class="bd-hist-track">
                            @if($rowTotal > 0)
                                <span style="width:{{ $row['approved'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['approved'] }}"></span>
                                <span style="width:{{ $row['rejected'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['rejected'] }}"></span>
                                <span style="width:{{ $row['pending'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['pending'] }}"></span>
                            @endif
                        </div>
                    </div>
                @endforeach
                <div class="bd-hist-legend">
                    <span><i style="background:{{ $outcomeColors['approved'] }}"></i>Approved</span>
                    <span><i style="background:{{ $outcomeColors['rejected'] }}"></i>Rejected</span>
                    <span><i style="background:{{ $outcomeColors['pending'] }}"></i>Pending</span>
                </div>
            </div>
        </section>

        <section class="bd-card">
            <div class="bd-card-head"><div class="bd-card-title">Task Status Summary</div></div>
            <div class="bd-card-body">
                @php
                    $donutColors = [
                        'assigned_tasks' => '#475467', 'review_analysis' => '#2970ff',
                        'need_clarification' => '#f79009', 'yet_to_start' => '#06aed4',
                        'in_progress' => '#3538cd', 'waiting_confirmation' => '#6938ef',
                        'rework' => '#b54708', 'completed' => '#027a48', 'swap_tasks' => '#c01048',
                    ];
                    $donutSegments = $statusSummary->filter(fn ($row) => $row['count'] > 0)->values();
                    $donutCircumference = 2 * M_PI * 26;
                    $donutCursor = 0;
                @endphp
                @if($stats['total'] > 0)
                    <div class="bd-donut-wrap">
                        <svg width="64" height="64" viewBox="0 0 64 64" style="flex-shrink:0">
                            <circle cx="32" cy="32" r="26" fill="none" stroke="#f2f4f7" stroke-width="10"/>
                            @foreach($donutSegments as $seg)
                                @php
                                    $segLen = ($seg['count'] / $stats['total']) * $donutCircumference;
                                @endphp
                                <circle cx="32" cy="32" r="26" fill="none" stroke="{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"
                                        stroke-width="10" stroke-dasharray="{{ $segLen }} {{ $donutCircumference - $segLen }}"
                                        stroke-dashoffset="{{ -$donutCursor }}" transform="rotate(-90 32 32)"/>
                                @php $donutCursor += $segLen; @endphp
                            @endforeach
                        </svg>
                        <div class="bd-donut-legend">
                            @foreach($donutSegments as $seg)
                                <div class="bd-donut-legend-row">
                                    <span class="bd-donut-dot" style="background:{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"></span>
                                    {{ $seg['label'] }}
                                    <span class="bd-donut-legend-count">{{ $seg['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @foreach($statusSummary as $row)
                    @php
                        $percentage = $stats['total'] > 0 ? round(($row['count'] / $stats['total']) * 100) : 0;
                    @endphp
                    <div class="bd-status-row">
                        <div class="bd-status-label">{{ $row['label'] }}</div>
                        <div class="bd-status-count">{{ $row['count'] }}</div>
                        <div class="bd-bar"><span style="width:{{ $percentage }}%"></span></div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="bd-card">
        <div class="bd-card-head">
            <div class="bd-card-title">My Requests — Swap, Decline &amp; Split</div>
        </div>
        <div class="bd-card-body bd-scroll-y">
            @forelse($myRequests as $req)
                @php
                    $pending = in_array($req->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                    $pillClass = match(true) {
                        $req->overall_status === 'approved' => 'pill-approved',
                        $req->overall_status === 'rejected' => 'pill-rejected',
                        default => 'pill-waiting',
                    };
                    $resultLabel = $pending ? 'Pending' : ucfirst($req->overall_status);
                    $currentHandler = ($req->overall_status === 'approved' && in_array($req->request_type, ['swap','split'], true))
                        ? ($req->approvedDesigner?->name ?? '—')
                        : ($req->task?->designer?->name ?? '—');
                @endphp
                <div class="bd-req-history">
                    <div class="bd-req-history-top">
                        <div>
                            <div class="bd-req-history-title">
                                @if($req->task)
                                    <a class="bd-task-link" href="{{ route('designer.tasks.index', ['focus' => $req->task->status, 'task' => $req->task->task_id]) }}">{{ $req->task->task_id }}</a>
                                @else
                                    Task
                                @endif
                                — {{ $req->task?->display_task_name ?? $req->task?->task_name ?? 'Task unavailable' }}
                            </div>
                            <div class="bd-req-history-type">{{ ucfirst($req->request_type) }} Request</div>
                        </div>
                        <span class="bd-pill {{ $pillClass }}">{{ $resultLabel }}</span>
                    </div>
                    <div class="bd-req-history-grid">
                        <div class="bd-req-history-line">Requested by: <b>{{ $req->requester?->name ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Requested at: <b>{{ $req->created_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Responded by: <b>{{ $req->decided_by?->name ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Responded at: <b>{{ $req->responded_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div class="bd-req-history-line">Current handler: <b>{{ $currentHandler }}</b></div>
                    </div>
                </div>
            @empty
                <div class="bd-empty">No swap, decline or split requests yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
