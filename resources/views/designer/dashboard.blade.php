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
    .dsh-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:170px;padding:18px 20px;border:1px solid #eef0f3;border-radius:16px;background:#fff url('{{ asset('images/dashboard-hero-bg.webp') }}') center/cover no-repeat;overflow:hidden}
    .dsh-hero-text{min-width:0;position:relative;z-index:1}
    .dsh-eyebrow{display:flex;align-items:center;gap:8px;font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#667085}
    .dsh-eyebrow span{width:26px;height:3px;border-radius:3px;background:#e30613}
    .dsh-hero h1{margin:8px 0 0;font-size:26px;line-height:1.15;font-weight:900;color:#101828}
    .dsh-hero h1 em{font-style:normal;color:#e30613}
    .dsh-hero p{margin:6px 0 0;font-size:11px;color:#667085}
    .dsh-login{display:flex;align-items:center;gap:7px;margin-top:10px;font-size:10px;color:#667085}
    .dsh-login svg{width:14px;height:14px;flex:none}
    .dsh-login strong{color:#344054}
    .dsh-hero-art{position:absolute;right:320px;top:0;height:100%;width:auto;max-width:48%;object-fit:contain;object-position:right center;mix-blend-mode:multiply;pointer-events:none;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 18%,#000 82%,transparent 100%);mask-image:linear-gradient(90deg,transparent 0,#000 18%,#000 82%,transparent 100%)}
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
    .tov{border:1px solid #f1dfe3;border-radius:20px;background:linear-gradient(180deg,#fff7f8,#fff 38%);box-shadow:0 10px 30px rgba(227,6,19,.07);padding:18px 18px 14px}
    .tov-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .tov-head-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:50%;background:#e30613;color:#fff;flex:none;box-shadow:0 6px 14px rgba(227,6,19,.28)}
    .tov-head-icon svg{width:22px;height:22px}
    .tov-head-text{flex:1;min-width:200px}
    .tov-head-text h2{margin:0;font-size:22px;line-height:1.15;font-weight:900;color:#101828}
    .tov-head-text p{margin:3px 0 0;font-size:12px;color:#475467}
    .tov-total{display:inline-flex;align-items:center;gap:12px;padding:11px 18px;border-radius:12px;background:#eef0f4;font-size:13px;font-weight:700;color:#344054}
    .tov-total strong{font-size:20px;font-weight:900;color:#e30613}
    .tov-viewall{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:10px;border:1.5px solid #e30613;background:#fff;color:#e30613;font-size:13px;font-weight:800;text-decoration:none;transition:.15s}
    .tov-viewall svg{width:16px;height:16px}
    .tov-viewall:hover{background:#e30613;color:#fff}
    .tov-filters{display:grid;grid-template-columns:minmax(220px,2.6fr) repeat(3,minmax(130px,1fr)) 46px 46px;gap:12px;margin-top:16px}
    .tov-search,.tov-select{position:relative;display:flex;align-items:center;height:46px;border:1px solid #dfe3ea;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(16,24,40,.04)}
    .tov-search svg{position:absolute;left:14px;width:17px;height:17px;color:#475467;pointer-events:none}
    .tov-search input{width:100%;height:100%;border:0;background:transparent;padding:0 12px 0 42px;font-size:13px;color:#101828;outline:none;border-radius:10px}
    .tov-select select{appearance:none;-webkit-appearance:none;width:100%;height:100%;border:0;background:transparent;padding:0 34px 0 16px;font-size:13px;font-weight:600;color:#344054;outline:none;cursor:pointer;border-radius:10px}
    .tov-select svg{position:absolute;right:12px;width:16px;height:16px;color:#344054;pointer-events:none}
    .tov-search:focus-within,.tov-select:focus-within{border-color:#e30613;box-shadow:0 0 0 3px rgba(227,6,19,.1)}
    .tov-iconbtn{display:grid;place-items:center;height:46px;border:1px solid #dfe3ea;border-radius:10px;background:#fff;color:#101828;cursor:pointer;transition:.15s;box-shadow:0 1px 2px rgba(16,24,40,.04)}
    .tov-iconbtn svg{width:19px;height:19px}
    .tov-iconbtn:hover{border-color:#e30613;color:#e30613}
    .tov-table-wrap{margin-top:16px;max-height:520px;overflow:auto;border:1px solid #eceff4;border-radius:14px;background:#fff}
    .tov-table{width:100%;min-width:1100px;border-collapse:separate;border-spacing:0}
    .tov-table th{position:sticky;top:0;z-index:2;padding:14px 12px;text-align:left;background:linear-gradient(180deg,#ffeef0,#fff4f5);border-bottom:1px solid #f3d9dd;font-size:11.5px;font-weight:800;letter-spacing:.02em;text-transform:uppercase;color:#344054;white-space:nowrap;line-height:1.25}
    .tov-table th:first-child{padding-left:18px}
    .tov-table td{padding:13px 12px;border-bottom:1px solid #eef0f3;font-size:12.5px;color:#344054;vertical-align:middle;line-height:1.35}
    .tov-table tbody tr:nth-child(even) td{background:#fafbfc}
    .tov-table tbody tr.is-alt td{background:#fafbfc}
    .tov-table tbody tr:hover td{background:#fff5f6}
    .tov-table tbody tr[hidden]{display:none}
    .tov-id{border-left:4px solid #e30613;padding-left:14px!important}
    .tov-id a{font-weight:800;color:#d4111f;text-decoration:none;white-space:nowrap}
    .tov-id a:hover{text-decoration:underline}
    .tov-vert,.tov-by,.tov-due span{display:inline-flex;align-items:center;gap:8px;white-space:nowrap}
    .tov-vert svg,.tov-by svg,.tov-due svg{width:17px;height:17px;flex:none}
    .tov-by svg,.tov-due svg{color:#667085}
    .tov-purple svg{color:#9333ea}.tov-pink svg{color:#db2777}.tov-blue svg{color:#2563eb}.tov-teal svg{color:#0d9488}.tov-orange svg{color:#ea580c}.tov-gray svg{color:#667085}
    .tov-due.is-overdue{color:#d4111f;font-weight:800}
    .tov-due.is-overdue svg{color:#d4111f}
    .tov-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:11.5px;font-weight:800;white-space:nowrap}
    .tov-pill svg{width:14px;height:14px;flex:none}
    .tov-st-assigned{background:#e8f1ff;color:#1d4ed8}.tov-st-assigned svg{width:11px;height:11px}
    .tov-st-completed{background:#e5f8ec;color:#067647}.tov-st-completed svg{fill:#12b76a;stroke:#fff}
    .tov-st-overdue{background:#ffe9ec;color:#d4111f}
    .tov-st-progress{background:#eef4ff;color:#3538cd}
    .tov-st-waiting{background:#f4f0ff;color:#6938ef}
    .tov-st-rework{background:#fff1e5;color:#b54708}
    .tov-st-default{background:#f2f4f7;color:#475467}
    .tov-pr-urgent{background:#fff0f1;color:#d4111f;border:1px solid #ffd5d9}
    .tov-pr-high{background:#fff3df;color:#b45309}.tov-pr-high svg{fill:#f59e0b;stroke:#f59e0b}
    .tov-pr-medium{background:#fff7d6;color:#a16207}.tov-pr-medium svg{fill:#facc15;stroke:#eab308}
    .tov-pr-low{background:#e9f7ee;color:#067647}
    .tov-pr-urgent svg{stroke:#d4111f}
    @media(max-width:1100px){.tov-filters{grid-template-columns:repeat(3,minmax(0,1fr)) 46px 46px}.tov-search{grid-column:1/-1}}
    @media(max-width:640px){.tov{padding:14px 12px}.tov-head-text h2{font-size:19px}.tov-total,.tov-viewall{flex:1;justify-content:center}.tov-filters{grid-template-columns:1fr 1fr}.tov-iconbtn{height:46px}}
    .ytd-icon{border-radius:12px}
    .ytd-search{width:min(360px,100%);flex:none}
    .ytd-table{min-width:1280px}
    .ytd-pct{font-size:12.5px;color:#344054}
    .ytd-bar{width:96px;height:7px;border-radius:999px;background:#e8ebf0;margin-top:6px;overflow:hidden}
    .ytd-bar i{display:block;height:100%;border-radius:999px;transition:width .25s ease}
    .ytd-count{font-weight:800;color:#101828;white-space:nowrap}
    .ytd-sub{font-size:12px;color:#98a2b3}
    .ytd-dash{color:#98a2b3}
    .ytd-done{color:#067647}.ytd-done svg{color:#16a34a!important}
    .ytd-od{display:inline-block;padding:6px 12px;border-radius:999px;font-size:11.5px;font-weight:800;text-align:center;line-height:1.25}
    .ytd-od-over{background:#ffe9ec;color:#d4111f}
    .ytd-od-late{background:#fff1da;color:#b45309}
    .ytd-od-ok{background:#e5f8ec;color:#067647}
    .ytd-stars{display:inline-flex;gap:2px}
    .ytd-stars i{width:17px;height:17px;display:block;background:linear-gradient(90deg,#f5b301 var(--f),#e1e5ec var(--f));-webkit-mask:var(--dsh-star) center/contain no-repeat;mask:var(--dsh-star) center/contain no-repeat}
    .ytd-search input{font-size:13px}
    .drv{border:1px solid #f1dfe3;border-radius:20px;background:linear-gradient(180deg,#fff7f8,#fff 34%);box-shadow:0 10px 30px rgba(227,6,19,.07);padding:18px 18px 14px;min-width:0}
    .drv-head{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .drv-head-icon{display:grid;place-items:center;width:44px;height:44px;border-radius:12px;background:#f43f7a;color:#fff;flex:none;box-shadow:0 6px 14px rgba(244,63,122,.3)}
    .drv-head-icon svg{width:22px;height:22px}
    .drv-head-text{flex:1;min-width:200px}
    .drv-head-text h2{margin:0;font-size:20px;line-height:1.15;font-weight:900;color:#101828}
    .drv-head-text p{margin:3px 0 0;font-size:12px;color:#475467}
    .drv-controls{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .drv-arrow{display:grid;place-items:center;width:40px;height:40px;border:1px solid #e4e7ec;border-radius:50%;background:#fff;color:#101828;cursor:pointer;box-shadow:0 2px 6px rgba(16,24,40,.08);transition:.15s}
    .drv-arrow svg{width:18px;height:18px}
    .drv-arrow:hover:not(:disabled){border-color:#e30613;color:#e30613}
    .drv-arrow:disabled{opacity:.45;cursor:not-allowed}
    .drv-viewport{margin-top:14px;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-ms-overflow-style:none;outline:none;border-radius:14px;overscroll-behavior-x:contain}
    .drv-viewport::-webkit-scrollbar{display:none}
    .drv-viewport:focus-visible{box-shadow:0 0 0 3px rgba(227,6,19,.18)}
    .drv-track{display:flex;gap:14px;width:max-content;padding:4px 2px 8px}
    .drv-card{--b1:#eef6ff;--b2:#fff;--ic:#2563eb;--icbg:#dcebff;--qc:#93c5fd;display:flex;flex-direction:column;flex:0 0 clamp(250px,26vw,340px);min-height:230px;padding:16px;border:1px solid rgba(16,24,40,.06);border-radius:16px;background:linear-gradient(160deg,var(--b1),var(--b2) 60%);box-shadow:0 4px 14px rgba(16,24,40,.06)}
    .drv-orange{--b1:#fff6e6;--ic:#d97706;--icbg:#ffe6bd;--qc:#fcd9a0}
    .drv-purple{--b1:#f6f0ff;--ic:#7c3aed;--icbg:#e7dbff;--qc:#c9b2f7}
    .drv-green{--b1:#ebfaf0;--ic:#16a34a;--icbg:#d3f3de;--qc:#86e0a8}
    .drv-pink{--b1:#fff0f5;--ic:#e11d48;--icbg:#ffd9e5;--qc:#f9a8c0}
    .drv-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
    .drv-stars{display:inline-flex;gap:3px}
    .drv-stars i{width:20px;height:20px;display:block;background:linear-gradient(90deg,#f5b301 var(--f),#dfe3ea var(--f));-webkit-mask:var(--dsh-star) center/contain no-repeat;mask:var(--dsh-star) center/contain no-repeat}
    .drv-quote{color:var(--qc);display:grid}
    .drv-quote svg{width:24px;height:24px;fill:currentColor;stroke:none}
    .drv-comment{margin:12px 0 14px;font-size:14px;line-height:1.5;color:#101828;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;min-height:63px;word-break:break-word}
    .drv-task{display:flex;align-items:center;gap:10px;margin-top:auto;padding-bottom:12px;border-bottom:1px solid rgba(16,24,40,.08)}
    .drv-task-icon{display:grid;place-items:center;width:38px;height:38px;flex:none;border-radius:10px;background:var(--icbg);color:var(--ic)}
    .drv-task-icon svg{width:19px;height:19px}
    .drv-task-text{min-width:0}
    .drv-task-line{font-size:12.5px;color:#344054;line-height:1.35;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
    .drv-task-line a,.drv-task-line strong{font-weight:800;color:#101828;text-decoration:none}
    .drv-task-line a:hover{color:#e30613;text-decoration:underline}
    .drv-by{font-size:12px;color:#667085;margin-top:2px}
    .drv-dates{display:flex;flex-direction:column;gap:7px;margin-top:11px;font-size:12px;color:#344054}
    .drv-dates div{display:flex;align-items:center;gap:8px}
    .drv-dates svg{width:15px;height:15px;flex:none;color:#667085}
    .drv-dates .drv-done svg{color:#16a34a}
    .drv-dots{display:flex;justify-content:center;gap:7px;margin-top:6px}
    .drv-dots i{width:8px;height:8px;border-radius:999px;background:#f5c9d8;cursor:pointer;transition:.2s}
    .drv-dots i.is-active{width:34px;background:#d6246e}
    .drv-empty{padding:26px 8px}
    @media(max-width:640px){.drv{padding:14px 12px}.drv-head-text h2{font-size:18px}.drv-controls{margin-left:auto}.drv-card{flex-basis:82vw}}
    @media(prefers-reduced-motion:reduce){.drv-dots i,.drv-arrow{transition:none}}
    .btm{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:12px;align-items:stretch}
    .btm-card{border:1px solid #f1dfe3;border-radius:16px;background:linear-gradient(180deg,#fff7f8,#fff 30%);box-shadow:0 8px 22px rgba(227,6,19,.06);padding:13px 14px;min-width:0;display:flex;flex-direction:column}
    .btm-head{display:flex;align-items:center;gap:11px;padding-bottom:10px;border-bottom:1px solid #f1e6e8}
    .btm-head-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e30613;color:#fff;flex:none;box-shadow:0 6px 14px rgba(227,6,19,.28)}
    .btm-head-icon svg{width:17px;height:17px}
    .btm-head-soft{background:#ffe9ec;color:#e30613;box-shadow:none}
    .btm-head-text h2{margin:0;font-size:15px;line-height:1.15;font-weight:900;color:#101828}
    .btm-head-text p{margin:2px 0 0;font-size:10.5px;color:#475467}
    .btm-req-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:16px;margin-top:11px;flex:1}
    .btm-req-list{display:flex;flex-direction:column;gap:7px;padding-right:16px;border-right:1px solid #f1e6e8}
    .btm-req{--t1:#eef4ff;--t2:#fbfcff;--ic:#2563eb;--icbg:#dfeaff;display:flex;align-items:center;gap:10px;padding:8px 11px;border-radius:12px;border:1px solid rgba(16,24,40,.05);background:linear-gradient(135deg,var(--t1),var(--t2))}
    .btm-purple{--t1:#f4edff;--t2:#fcfaff;--ic:#7c3aed;--icbg:#e8dcff}
    .btm-red{--t1:#ffecef;--t2:#fff8f9;--ic:#e11d48;--icbg:#ffd8df}
    .btm-orange{--t1:#fff3e2;--t2:#fffaf2;--ic:#f59e0b;--icbg:#ffe5b8}
    .btm-req-icon{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:var(--icbg);color:var(--ic);flex:none}
    .btm-req-icon svg{width:16px;height:16px}
    .btm-req-text{flex:1;min-width:0}
    .btm-req-name{font-size:12px;font-weight:800;color:#101828;line-height:1.2}
    .btm-req-sub{font-size:10px;color:#667085;margin-top:2px}
    .btm-req-count{display:grid;place-items:center;min-width:34px;height:34px;padding:0 8px;border-radius:10px;background:rgba(255,255,255,.75);font-size:15px;font-weight:900;color:#101828;box-shadow:0 1px 2px rgba(16,24,40,.06)}
    .btm-outcomes h3{margin:0 0 9px;font-size:14px;font-weight:900;color:#101828}
    .btm-out-row{margin-bottom:12px}
    .btm-out-top{display:flex;justify-content:space-between;font-size:11px;font-weight:800;color:#101828}
    .btm-out-top span:last-child{color:#475467}
    .btm-out-track{display:flex;height:8px;margin-top:6px;border-radius:999px;background:#eceff4;overflow:hidden}
    .btm-out-track span{display:block;height:100%}
    .btm-legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:4px;font-size:10.5px;color:#344054}
    .btm-legend span{display:inline-flex;align-items:center;gap:6px}
    .btm-legend i{width:11px;height:11px;border-radius:4px;display:inline-block}
    .btm-donut-wrap{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:10px;padding-bottom:10px;border-bottom:1px solid #f1e6e8}
    .btm-donut{position:relative;width:104px;height:104px;flex:none}
    .btm-donut svg{width:100%;height:100%;display:block}
    .btm-donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.1}
    .btm-donut-center strong{font-size:21px;font-weight:900;color:#101828}
    .btm-donut-center span{font-size:10.5px;color:#475467;margin-top:2px}
    .btm-donut-legend{flex:1;min-width:150px;display:flex;flex-direction:column;gap:6px}
    .btm-donut-legend div{display:flex;align-items:center;gap:8px;font-size:11px;color:#344054}
    .btm-donut-legend i{width:9px;height:9px;border-radius:50%;flex:none}
    .btm-donut-legend span{flex:1;min-width:0}
    .btm-donut-legend b{font-weight:900;color:#101828}
    .btm-status-list{display:flex;flex-direction:column;gap:6px;margin-top:10px}
    .btm-status-row{display:grid;grid-template-columns:minmax(110px,1.25fr) 38px minmax(0,2fr);align-items:center;gap:8px}
    .btm-status-label{font-size:11px;font-weight:800;color:#101828}
    .btm-status-count{display:grid;place-items:center;height:21px;border-radius:7px;background:#f2f4f7;font-size:11px;font-weight:900;color:#101828}
    .btm-status-count.has{background:#ffe8ec}
    .btm-bar{height:6px;border-radius:999px;background:#eceff4;overflow:hidden}
    .btm-bar span{display:block;height:100%;border-radius:999px;background:#e30613}
    @media(max-width:1200px){.btm{grid-template-columns:1fr}}
    @media(max-width:760px){.btm-req-body{grid-template-columns:1fr}.btm-req-list{padding-right:0;border-right:0;padding-bottom:14px;border-bottom:1px solid #f1e6e8}.btm-card{padding:14px 12px}.btm-status-row{grid-template-columns:minmax(100px,1.2fr) 42px minmax(0,1.5fr)}}
    .mrq{margin-top:0}
    .mrq-count{margin-left:auto;padding:7px 13px;border-radius:999px;background:#fff;border:1px solid #f1dfe3;font-size:11px;font-weight:700;color:#475467;white-space:nowrap}
    .mrq-body{display:flex;flex-direction:column;gap:9px;margin-top:11px;max-height:440px;overflow:auto;padding-right:2px}
    .mrq-item{--t1:#eef4ff;--t2:#fbfcff;padding:11px 13px;border-radius:13px;border:1px solid rgba(16,24,40,.06);background:linear-gradient(135deg,var(--t1),var(--t2))}
    .mrq-top{display:flex;align-items:center;gap:11px}
    .mrq-title{flex:1;min-width:0}
    .mrq-task{font-size:12.5px;font-weight:700;color:#101828;line-height:1.3}
    .mrq-task a{color:#d4111f;text-decoration:none;font-weight:800}
    .mrq-task a:hover{text-decoration:underline}
    .mrq-task span{font-weight:600;color:#344054}
    .mrq-type{font-size:10.5px;color:#667085;margin-top:2px}
    .mrq-pill{padding:5px 11px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap}
    .mrq-approved{background:#e5f8ec;color:#067647}
    .mrq-rejected{background:#ffe9ec;color:#d4111f}
    .mrq-pending{background:#f1ebff;color:#6d28d9}
    .mrq-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-top:10px;padding-top:9px;border-top:1px solid rgba(16,24,40,.07)}
    .mrq-grid div{min-width:0}
    .mrq-grid span{display:block;font-size:9.5px;font-weight:600;color:#667085;text-transform:uppercase;letter-spacing:.03em}
    .mrq-grid b{display:block;margin-top:2px;font-size:11.5px;font-weight:700;color:#101828;overflow:hidden;text-overflow:ellipsis}
    .mrq-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:26px 12px 22px}
    .mrq-empty-icon{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:#ffe9ec;color:#e30613}
    .mrq-empty-icon svg{width:22px;height:22px}
    .mrq-empty-title{margin-top:10px;font-size:13px;font-weight:700;color:#101828}
    .mrq-empty-sub{margin-top:3px;font-size:11px;color:#667085}
    @media(max-width:1000px){.mrq-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:640px){.mrq-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mrq-top{flex-wrap:wrap}.mrq-count{display:none}}
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

    @php
        $ic2 = $ic + [
            'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'list' => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
            'sliders' => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
            'arrow' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'chevron' => '<path d="m6 9 6 6 6-6"/>',
            'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
            'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
            'signpost' => '<path d="M12 13v8"/><path d="M12 3v3"/><path d="M18 6a2 2 0 0 1 1.387.56l2.307 2.22a1 1 0 0 1 0 1.44l-2.307 2.22A2 2 0 0 1 18 13H6a2 2 0 0 1-1.387-.56l-2.306-2.22a1 1 0 0 1 0-1.44l2.306-2.22A2 2 0 0 1 6 6z"/>',
            'party' => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11c-.11.7-.72 1.22-1.43 1.22H17"/><path d="m11 2 .33.82c.34.86-.2 1.82-1.11 1.98C9.52 4.9 9 5.52 9 6.23V7"/><path d="M11 13c1.93 1.93 2.83 4.17 2 5-.83.83-3.07-.07-5-2-1.93-1.93-2.83-4.17-2-5 .83-.83 3.07.07 5 2Z"/>',
            'armchair' => '<path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M5 18v2"/><path d="M19 18v2"/>',
            'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/>',
            'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'dot' => '<circle cx="12" cy="12" r="5" fill="currentColor"/>',
            'grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        ];
        $svg2 = fn (string $name) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($ic2[$name] ?? $ic2['grid']).'</svg>';
        $verticalIcon = fn (?string $v) => match (true) {
            str_contains((string) $v, 'roadshow') => ['truck', 'purple'],
            str_contains((string) $v, 'digital') => ['monitor', 'pink'],
            str_contains((string) $v, 'outdoor') => ['signpost', 'blue'],
            str_contains((string) $v, 'signage') => ['signpost', 'teal'],
            str_contains((string) $v, 'fixture') => ['armchair', 'orange'],
            str_contains((string) $v, 'pop') => ['printer', 'blue'],
            str_contains((string) $v, 'event') => ['party', 'pink'],
            default => ['grid', 'gray'],
        };
        $statusMeta = fn (string $s, bool $overdue) => $overdue ? ['Overdue', 'alert', 'overdue'] : match ($s) {
            'assigned_tasks' => ['Assigned Tasks', 'dot', 'assigned'],
            'completed' => ['Completed', 'check', 'completed'],
            'in_progress' => ['In Progress', 'loader', 'progress'],
            'waiting_confirmation' => ['Waiting Confirmation', 'clock', 'waiting'],
            'rework' => ['Rework', 'refresh', 'rework'],
            'prepare_printing_file' => ['Prepare Printing File', 'printer', 'waiting'],
            default => [ucwords(str_replace('_', ' ', $s === 'swap_tasks' ? 'swapped_tasks' : $s)), 'dot', 'default'],
        };
        $tovStatuses = $recentTasks->map(fn ($t) => $t->status)->unique()->values();
        $tovPriorities = $recentTasks->map(fn ($t) => (string) $t->priority)->filter()->unique()->values();
        $tovVerticals = $recentTasks->map(fn ($t) => (string) $t->vertical)->filter()->unique()->values();
    @endphp

    <section class="tov" id="tasks-overview">
        <div class="tov-head">
            <span class="tov-head-icon">{!! $svg2('tasks') !!}</span>
            <div class="tov-head-text">
                <h2>Tasks Overview</h2>
                <p>Track and manage all your assigned design tasks in one place.</p>
            </div>
            <div class="tov-total">Total Tasks <strong>{{ $stats['total'] }}</strong></div>
            <a class="tov-viewall" href="{{ route('designer.tasks.index') }}">View All Tasks {!! $svg2('arrow') !!}</a>
        </div>

        <div class="tov-filters">
            <label class="tov-search">{!! $svg2('search') !!}<input type="search" id="tov-search" placeholder="Search by Task ID, Project Number, Task, Client / Agency" autocomplete="off"></label>
            <label class="tov-select"><select id="tov-status" aria-label="Status"><option value="">Status: All</option>@foreach($tovStatuses as $s)<option value="{{ $s }}">{{ $statusMeta($s, false)[0] }}</option>@endforeach<option value="overdue">Overdue</option></select>{!! $svg2('chevron') !!}</label>
            <label class="tov-select"><select id="tov-priority" aria-label="Priority"><option value="">Priority: All</option>@foreach($tovPriorities as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select>{!! $svg2('chevron') !!}</label>
            <label class="tov-select"><select id="tov-vertical" aria-label="Vertical"><option value="">Vertical: All</option>@foreach($tovVerticals as $v)<option value="{{ $v }}">{{ ucwords(str_replace('_', ' ', $v)) }}</option>@endforeach</select>{!! $svg2('chevron') !!}</label>
            <button type="button" class="tov-iconbtn" id="tov-refresh" title="Refresh" aria-label="Refresh">{!! $svg2('refresh') !!}</button>
            <button type="button" class="tov-iconbtn" id="tov-reset" title="Clear filters" aria-label="Clear filters">{!! $svg2('sliders') !!}</button>
        </div>

        <div class="tov-table-wrap">
            <table class="tov-table">
                <thead>
                <tr>
                    <th>Task ID</th>
                    <th>Project<br>Number</th>
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
                <tbody id="tov-body">
                @forelse($recentTasks as $task)
                    @php
                        $isOverdue = $task->status !== 'completed' && $task->due_at && $task->due_at->isPast();
                        [$stLabel, $stIcon, $stTone] = $statusMeta((string) $task->status, (bool) $isOverdue);
                        [$vIcon, $vTone] = $verticalIcon((string) $task->vertical);
                        $prio = strtolower((string) $task->priority);
                    @endphp
                    <tr data-search="{{ strtolower($task->task_id.' '.($task->zoho_project_number ?? '').' '.($task->display_task_name ?? $task->task_name).' '.$task->party_name.' '.$task->vertical.' '.$task->task_nature) }}"
                        data-status="{{ $isOverdue ? 'overdue' : $task->status }}" data-status-raw="{{ $task->status }}" data-priority="{{ $task->priority }}" data-vertical="{{ $task->vertical }}">
                        <td class="tov-id"><a href="{{ route('designer.tasks.index', ['focus' => $task->status, 'task' => $task->task_id]) }}">{{ $task->task_id }}</a></td>
                        <td>{{ $task->zoho_project_number ?: '-' }}</td>
                        <td>{{ $task->display_task_name ?? $task->task_name }}</td>
                        <td>{{ $task->party_name }}</td>
                        <td><span class="tov-vert tov-{{ $vTone }}">{!! $svg2($vIcon) !!}{{ ucwords(str_replace('_', ' ', $task->vertical)) }}</span></td>
                        <td>{{ ucwords(str_replace('_', ' ', $task->task_nature)) }}</td>
                        <td><span class="tov-by">{!! $svg2('user') !!}{{ $task->assigner?->name ?? '—' }}</span></td>
                        <td><span class="tov-pill tov-st-{{ $stTone }}">{!! $svg2($stIcon) !!}{{ $stLabel }}</span></td>
                        <td><span class="tov-pill tov-pr-{{ $prio }}">{!! $svg2('flag') !!}{{ ucfirst($task->priority) }}</span></td>
                        <td class="tov-due{{ $isOverdue ? ' is-overdue' : '' }}"><span>{!! $svg2('calendar') !!}{{ $task->due_at?->format('d M Y · h:i A') ?? '—' }}</span></td>
                    </tr>
                @empty
                    <tr class="tov-empty-static"><td colspan="10"><div class="bd-empty">No tasks assigned yet.</div></td></tr>
                @endforelse
                <tr class="tov-empty" id="tov-empty" hidden><td colspan="10"><div class="bd-empty">No matching tasks found.</div></td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <script>
    (function () {
        var body = document.getElementById('tov-body');
        if (!body) return;
        var q = document.getElementById('tov-search');
        var st = document.getElementById('tov-status');
        var pr = document.getElementById('tov-priority');
        var vt = document.getElementById('tov-vertical');
        var empty = document.getElementById('tov-empty');

        function apply() {
            var term = q.value.trim().toLowerCase();
            var shown = 0;
            body.querySelectorAll('tr[data-search]').forEach(function (row) {
                var statusOk = !st.value || (st.value === 'overdue' ? row.dataset.status === 'overdue' : row.dataset.statusRaw === st.value);
                var ok = (!term || row.dataset.search.indexOf(term) !== -1)
                    && statusOk
                    && (!pr.value || row.dataset.priority === pr.value)
                    && (!vt.value || row.dataset.vertical === vt.value);
                row.hidden = !ok;
                if (ok) { row.classList.toggle('is-alt', shown % 2 === 1); shown++; }
            });
            if (empty) empty.hidden = shown !== 0 || !body.querySelector('tr[data-search]');
        }

        [q, st, pr, vt].forEach(function (el) { el.addEventListener(el === q ? 'input' : 'change', apply); });
        document.getElementById('tov-reset').addEventListener('click', function () { q.value = ''; st.value = ''; pr.value = ''; vt.value = ''; apply(); });
        document.getElementById('tov-refresh').addEventListener('click', function () { window.location.reload(); });
        apply();
    })();
    </script>

    <section class="tov ytd">
        <div class="tov-head">
            <span class="tov-head-icon ytd-icon">{!! $svg2('list') !!}</span>
            <div class="tov-head-text">
                <h2>Your Task Details</h2>
                <p>Detailed view of your assigned tasks, progress, creatives and deadlines.</p>
            </div>
            <label class="tov-search ytd-search">{!! $svg2('search') !!}<input type="text" id="designer-task-search" placeholder="Search tasks, project, client, creative..." autocomplete="off"></label>
        </div>
        <div class="tov-table-wrap" id="designer-task-rows-wrap">
            <table class="tov-table ytd-table">
                <thead>
                <tr>
                    <th>Task ID</th><th>Project<br>Number</th><th>Task Name</th><th>Assigned At</th><th>Status</th><th>Progress</th>
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

    @include('designer.performance-trend', [
        'trendCards' => $trendCards,
        'trendData' => $line,
        'trendContext' => $trendContext,
    ])

    @php
        $drvIc = $ic2 + [
            'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M13 8H7"/><path d="M17 12H7"/>',
            'left' => '<path d="m15 18-6-6 6-6"/>',
            'right' => '<path d="m9 18 6-6-6-6"/>',
            'pause' => '<rect x="14" y="4" width="4" height="16" rx="1"/><rect x="6" y="4" width="4" height="16" rx="1"/>',
            'quote' => '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>',
            'calcheck' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>',
        ];
        $svg4 = fn (string $n) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($drvIc[$n] ?? $drvIc['grid']).'</svg>';
        $reviewCount = $reviewCards->count();
        $drvTones = ['blue', 'orange', 'purple', 'green', 'pink'];
    @endphp

    <section class="drv" id="drv" aria-label="Designer Reviews">
        <div class="drv-head">
            <span class="drv-head-icon">{!! $svg4('chat') !!}</span>
            <div class="drv-head-text">
                <h2>Designer Reviews</h2>
                <p>Recent client reviews and ratings for completed tasks.</p>
            </div>
            @if($reviewCount > 0)
                <div class="drv-controls">
                    <button type="button" class="drv-arrow" id="drv-prev" aria-label="Previous reviews">{!! $svg4('left') !!}</button>
                    <button type="button" class="drv-arrow" id="drv-next" aria-label="Next reviews">{!! $svg4('right') !!}</button>
                </div>
            @endif
        </div>

        @if($reviewCount === 0)
            <div class="bd-empty drv-empty">No BD comments yet on your completed tasks.</div>
        @else
            <div class="drv-viewport" id="drv-viewport" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Recent reviews">
                <div class="drv-track" id="drv-track">
                    @foreach($reviewCards as $review)
                        @php
                            $ratingValue = max(0, min(5, $review['rating']));
                            $tone = $drvTones[$loop->index % count($drvTones)];
                        @endphp
                        <article class="drv-card drv-{{ $tone }}">
                            <div class="drv-card-top">
                                <div class="drv-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                    @for($i = 1; $i <= 5; $i++)<i style="--f:{{ $ratingValue >= $i ? 100 : ($ratingValue >= $i - 0.5 ? 50 : 0) }}%"></i>@endfor
                                </div>
                                <span class="drv-quote">{!! $svg4('quote') !!}</span>
                            </div>
                            <p class="drv-comment" title="{{ $review['comment'] }}">&ldquo;{{ $review['comment'] }}&rdquo;</p>
                            <div class="drv-task">
                                <span class="drv-task-icon">{!! $svg4('tasks') !!}</span>
                                <div class="drv-task-text">
                                    <div class="drv-task-line">
                                        @if($review['task'])
                                            <a href="{{ route('designer.tasks.index', ['focus' => $review['task']->status, 'task' => $review['task_id']]) }}">{{ $review['task_id'] }}</a>
                                        @else
                                            <strong>{{ $review['task_id'] }}</strong>
                                        @endif
                                        <span>· {{ $review['task_name'] }}</span>
                                    </div>
                                    <div class="drv-by">{{ $review['reviewer'] }}</div>
                                </div>
                            </div>
                            <div class="drv-dates">
                                <div>{!! $svg4('calendar') !!}Reviewed: {{ $review['reviewed_at']?->format('d M Y, h:i A') ?? '—' }}</div>
                                <div class="drv-done">{!! $svg4('calcheck') !!}Completed: {{ $review['completed_at']?->format('d M Y, h:i A') ?? '—' }}</div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="drv-dots" id="drv-dots" aria-hidden="true"></div>
        @endif
    </section>

    @if($reviewCount > 0)
    <script>
    (function () {
        var vp = document.getElementById('drv-viewport');
        var track = document.getElementById('drv-track');
        if (!vp || !track) return;

        var cards = Array.prototype.slice.call(track.children);
        var n = cards.length;
        var prevBtn = document.getElementById('drv-prev');
        var nextBtn = document.getElementById('drv-next');
        var dotsEl = document.getElementById('drv-dots');
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var SPEED = 55;            // px per second, right → left
        var RESUME_DELAY = 2200;   // ms of calm after interaction before auto-scroll resumes
        var looping = false, loopW = 0, step = 300;
        var enabled = !reduced;    // the Auto Scroll switch
        var holds = 0, onscreen = true;
        var pos = 0, last = 0, raf = 0, resumeTimer = 0, normTimer = 0, resizeTimer = 0;

        function clearClones() {
            Array.prototype.slice.call(track.querySelectorAll('[data-clone]')).forEach(function (c) { track.removeChild(c); });
        }

        function measure() {
            clearClones();
            var gap = parseFloat(getComputedStyle(track).columnGap) || 14;
            var cw = cards[0].getBoundingClientRect().width;
            step = cw + gap;
            var contentW = n * cw + (n - 1) * gap;
            looping = n > 1 && contentW > vp.clientWidth + 2;
            loopW = 0;

            if (looping) {
                cards.forEach(function (card) {
                    var clone = card.cloneNode(true);
                    clone.setAttribute('data-clone', '1');
                    clone.setAttribute('aria-hidden', 'true');
                    Array.prototype.forEach.call(clone.querySelectorAll('a'), function (a) { a.setAttribute('tabindex', '-1'); });
                    track.appendChild(clone);
                });
                loopW = n * step;
            } else {
                vp.scrollLeft = 0;
            }

            if (prevBtn) prevBtn.disabled = nextBtn.disabled = !looping && contentW <= vp.clientWidth + 2;
            pos = vp.scrollLeft;
            buildDots();
            updateDots();
        }

        function normalize() {
            if (!looping) return;
            if (vp.scrollLeft >= loopW) { vp.scrollLeft -= loopW; pos = vp.scrollLeft; }
        }

        function shouldRun() { return looping && enabled && holds === 0 && onscreen && !document.hidden; }

        function frame(t) {
            raf = 0;
            if (!shouldRun()) { last = 0; return; }
            if (last) {
                pos += SPEED * Math.min(0.05, (t - last) / 1000);
                if (pos >= loopW) pos -= loopW;
                vp.scrollLeft = Math.round(pos);
            }
            last = t;
            raf = requestAnimationFrame(frame);
        }

        function kick() { if (!raf && shouldRun()) { last = 0; pos = vp.scrollLeft; raf = requestAnimationFrame(frame); } }
        function stop() { if (raf) { cancelAnimationFrame(raf); raf = 0; } last = 0; }

        function hold() { holds++; clearTimeout(resumeTimer); stop(); }
        function release(delay) {
            holds = Math.max(0, holds - 1);
            clearTimeout(resumeTimer);
            resumeTimer = setTimeout(function () { pos = vp.scrollLeft; kick(); }, delay === undefined ? RESUME_DELAY : delay);
        }

        // A short pause for one-off interactions (wheel, keys, arrows, touch).
        function nudge() {
            clearTimeout(resumeTimer);
            stop();
            resumeTimer = setTimeout(function () { pos = vp.scrollLeft; kick(); }, RESUME_DELAY);
        }

        function move(dir) {
            nudge();
            if (looping) {
                if (dir < 0 && vp.scrollLeft < step) vp.scrollLeft += loopW;
                else if (dir > 0 && vp.scrollLeft > loopW) vp.scrollLeft -= loopW;
            }
            vp.scrollBy({ left: dir * step, behavior: reduced ? 'auto' : 'smooth' });
        }

        function buildDots() {
            if (!dotsEl) return;
            dotsEl.innerHTML = '';
            if (n < 2 || n > 10) return;
            for (var i = 0; i < n; i++) {
                (function (idx) {
                    var d = document.createElement('i');
                    d.addEventListener('click', function () { nudge(); vp.scrollTo({ left: (looping ? 0 : 0) + idx * step, behavior: reduced ? 'auto' : 'smooth' }); });
                    dotsEl.appendChild(d);
                })(i);
            }
        }

        var dotRaf = 0;
        function updateDots() {
            if (!dotsEl || !dotsEl.children.length) return;
            var idx = Math.round(vp.scrollLeft / step) % n;
            Array.prototype.forEach.call(dotsEl.children, function (d, i) { d.classList.toggle('is-active', i === idx); });
        }

        vp.addEventListener('scroll', function () {
            if (!dotRaf) dotRaf = requestAnimationFrame(function () { dotRaf = 0; updateDots(); });
            clearTimeout(normTimer);
            normTimer = setTimeout(function () { if (!raf) normalize(); }, 140);
        }, { passive: true });

        // Hover / focus pause, resume shortly after leaving.
        vp.addEventListener('mouseenter', hold);
        vp.addEventListener('mouseleave', function () { release(); });
        vp.addEventListener('focusin', hold);
        vp.addEventListener('focusout', function () { release(); });
        vp.addEventListener('touchstart', nudge, { passive: true });
        vp.addEventListener('wheel', nudge, { passive: true });
        vp.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); move(1); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); move(-1); }
        });
        if (prevBtn) prevBtn.addEventListener('click', function () { move(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { move(1); });

        var onVisibility = function () { if (document.hidden) stop(); else kick(); };
        document.addEventListener('visibilitychange', onVisibility);

        var io = null;
        if ('IntersectionObserver' in window) {
            io = new IntersectionObserver(function (entries) {
                onscreen = entries[0].isIntersecting;
                if (onscreen) kick(); else stop();
            }, { threshold: 0.1 });
            io.observe(vp);
        }

        var onResize = function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(function () { stop(); measure(); kick(); }, 160); };
        window.addEventListener('resize', onResize);

        // Tear everything down if the page is left (no stray timers / listeners).
        window.addEventListener('pagehide', function () {
            stop();
            [resumeTimer, normTimer, resizeTimer].forEach(clearTimeout);
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('resize', onResize);
            if (io) io.disconnect();
        });

        measure();
        kick();
    })();
    </script>
    @endif

    @php
        $btmIc = $ic2 + [
            'swap' => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
            'branch' => '<line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
            'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'hourglass' => '<path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/>',
        ];
        $svg5 = fn (string $n) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($btmIc[$n] ?? $btmIc['grid']).'</svg>';
        $outcomeColors = ['approved' => '#16a34a', 'rejected' => '#e11d48', 'pending' => '#7c3aed'];
        $donutColors = [
            'assigned_tasks' => '#475467', 'review_analysis' => '#2970ff',
            'need_clarification' => '#f79009', 'yet_to_start' => '#06aed4',
            'in_progress' => '#3538cd', 'waiting_confirmation' => '#6938ef',
            'rework' => '#b54708', 'completed' => '#16a34a', 'swap_tasks' => '#c01048',
        ];
        $donutSegments = $statusSummary->filter(fn ($row) => $row['count'] > 0)->values();
        $donutR = 46; $donutCircumference = 2 * M_PI * $donutR; $donutCursor = 0;
        $reqCards = [
            ['Swap Requests', $requestSummary['swap'], 'swap', 'blue'],
            ['Split Requests', $requestSummary['split'], 'branch', 'purple'],
            ['Decline Requests', $requestSummary['decline'], 'x', 'red'],
        ];
    @endphp

    <div class="btm">
        <section class="btm-card" aria-label="Requests Overview and Outcomes">
            <div class="btm-head">
                <span class="btm-head-icon">{!! $svg5('layers') !!}</span>
                <div class="btm-head-text">
                    <h2>Requests Overview &amp; Outcomes</h2>
                    <p>Current requests, approvals and final outcomes in one view.</p>
                </div>
            </div>
            <div class="btm-req-body">
                <div class="btm-req-list">
                    @foreach($reqCards as [$label, $count, $icon, $tone])
                        <div class="btm-req btm-{{ $tone }}">
                            <span class="btm-req-icon">{!! $svg5($icon) !!}</span>
                            <div class="btm-req-text"><div class="btm-req-name">{{ $label }}</div><div class="btm-req-sub">Pending approval</div></div>
                            <span class="btm-req-count">{{ $count }}</span>
                        </div>
                    @endforeach
                    <div class="btm-req btm-orange">
                        <span class="btm-req-icon">{!! $svg5('hourglass') !!}</span>
                        <div class="btm-req-text"><div class="btm-req-name">Total Pending</div></div>
                        <span class="btm-req-count">{{ array_sum($requestSummary) }}</span>
                    </div>
                </div>

                <div class="btm-outcomes">
                    <h3>Request Outcomes</h3>
                    @foreach($requestOutcomes as $row)
                        @php $rowTotal = $row['approved'] + $row['rejected'] + $row['pending']; @endphp
                        <div class="btm-out-row">
                            <div class="btm-out-top"><span>{{ $row['label'] }}</span><span>{{ $rowTotal }} total</span></div>
                            <div class="btm-out-track" title="Approved {{ $row['approved'] }} · Rejected {{ $row['rejected'] }} · Pending {{ $row['pending'] }}">
                                @if($rowTotal > 0)
                                    <span style="width:{{ $row['approved'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['approved'] }}"></span>
                                    <span style="width:{{ $row['rejected'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['rejected'] }}"></span>
                                    <span style="width:{{ $row['pending'] / $rowTotal * 100 }}%;background:{{ $outcomeColors['pending'] }}"></span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="btm-legend">
                        <span><i style="background:{{ $outcomeColors['approved'] }}"></i>Approved</span>
                        <span><i style="background:{{ $outcomeColors['rejected'] }}"></i>Rejected</span>
                        <span><i style="background:{{ $outcomeColors['pending'] }}"></i>Pending</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="btm-card" aria-label="Task Status Summary">
            <div class="btm-head">
                <span class="btm-head-icon btm-head-soft">{!! $svg5('tasks') !!}</span>
                <div class="btm-head-text">
                    <h2>Task Status Summary</h2>
                    <p>Task distribution across all stages.</p>
                </div>
            </div>

            @if($stats['total'] > 0)
                <div class="btm-donut-wrap">
                    <div class="btm-donut">
                        <svg viewBox="0 0 120 120" role="img" aria-label="{{ $stats['total'] }} tasks by status">
                            <circle cx="60" cy="60" r="{{ $donutR }}" fill="none" stroke="#f2f4f7" stroke-width="16"/>
                            @foreach($donutSegments as $seg)
                                @php $segLen = ($seg['count'] / $stats['total']) * $donutCircumference; @endphp
                                <circle cx="60" cy="60" r="{{ $donutR }}" fill="none" stroke="{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"
                                        stroke-width="16" stroke-dasharray="{{ $segLen }} {{ $donutCircumference - $segLen }}"
                                        stroke-dashoffset="{{ -$donutCursor }}" transform="rotate(-90 60 60)"><title>{{ $seg['label'] }}: {{ $seg['count'] }}</title></circle>
                                @php $donutCursor += $segLen; @endphp
                            @endforeach
                        </svg>
                        <div class="btm-donut-center"><strong>{{ $stats['total'] }}</strong><span>Tasks</span></div>
                    </div>
                    <div class="btm-donut-legend">
                        @foreach($donutSegments as $seg)
                            <div><i style="background:{{ $donutColors[$seg['key']] ?? '#98a2b3' }}"></i><span>{{ $seg['label'] }}</span><b>{{ $seg['count'] }}</b></div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="btm-status-list">
                @foreach($statusSummary as $row)
                    @php $percentage = $stats['total'] > 0 ? round(($row['count'] / $stats['total']) * 100) : 0; @endphp
                    <div class="btm-status-row">
                        <div class="btm-status-label">{{ $row['label'] }}</div>
                        <div class="btm-status-count{{ $row['count'] > 0 ? ' has' : '' }}">{{ $row['count'] }}</div>
                        <div class="btm-bar"><span style="width:{{ $percentage }}%"></span></div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="btm-card mrq" aria-label="My Requests">
        <div class="btm-head">
            <span class="btm-head-icon">{!! $svg5('swap') !!}</span>
            <div class="btm-head-text">
                <h2>My Requests — Swap, Decline &amp; Split</h2>
                <p>Requests you raised and how they were handled.</p>
            </div>
            <span class="mrq-count">{{ $myRequests->count() }} {{ \Illuminate\Support\Str::plural('request', $myRequests->count()) }}</span>
        </div>
        <div class="mrq-body">
            @forelse($myRequests as $req)
                @php
                    $pending = in_array($req->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                    $statusTone = match(true) {
                        $req->overall_status === 'approved' => 'approved',
                        $req->overall_status === 'rejected' => 'rejected',
                        default => 'pending',
                    };
                    $resultLabel = $pending ? 'Pending' : ucfirst($req->overall_status);
                    $currentHandler = ($req->overall_status === 'approved' && in_array($req->request_type, ['swap','split'], true))
                        ? ($req->approvedDesigner?->name ?? '—')
                        : ($req->task?->designer?->name ?? '—');
                    [$reqIcon, $reqTone] = match($req->request_type) {
                        'swap' => ['swap', 'blue'],
                        'split' => ['branch', 'purple'],
                        'decline' => ['x', 'red'],
                        default => ['grid', 'orange'],
                    };
                @endphp
                <div class="mrq-item btm-{{ $reqTone }}">
                    <div class="mrq-top">
                        <span class="btm-req-icon">{!! $svg5($reqIcon) !!}</span>
                        <div class="mrq-title">
                            <div class="mrq-task">
                                @if($req->task)
                                    <a href="{{ route('designer.tasks.index', ['focus' => $req->task->status, 'task' => $req->task->task_id]) }}">{{ $req->task->task_id }}</a>
                                @else
                                    Task
                                @endif
                                <span>— {{ $req->task?->display_task_name ?? $req->task?->task_name ?? 'Task unavailable' }}</span>
                            </div>
                            <div class="mrq-type">{{ ucfirst($req->request_type) }} Request</div>
                        </div>
                        <span class="mrq-pill mrq-{{ $statusTone }}">{{ $resultLabel }}</span>
                    </div>
                    <div class="mrq-grid">
                        <div><span>Requested by</span><b>{{ $req->requester?->name ?? '—' }}</b></div>
                        <div><span>Requested at</span><b>{{ $req->created_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div><span>Responded by</span><b>{{ $req->decided_by?->name ?? '—' }}</b></div>
                        <div><span>Responded at</span><b>{{ $req->responded_at?->format('d M Y \a\t h:i A') ?? '—' }}</b></div>
                        <div><span>Current handler</span><b>{{ $currentHandler }}</b></div>
                    </div>
                </div>
            @empty
                <div class="mrq-empty">
                    <span class="mrq-empty-icon">{!! $svg5('inbox') !!}</span>
                    <div class="mrq-empty-title">No swap, decline or split requests yet</div>
                    <div class="mrq-empty-sub">Requests you raise from a task will show up here with their status.</div>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
