@extends('layouts.app')

@section('title','BD Dashboard')
@section('workspace-title','BD Dashboard')
@section('workspace-subtitle','Live insight into your tickets, Designers, quality, rework and reviews')

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
    .dh-kpi-link{display:block;text-decoration:none;color:inherit;cursor:pointer;transition:.15s}
    .dh-kpi-link:hover{border-color:#e30613;box-shadow:0 4px 14px rgba(227,6,19,.1);transform:translateY(-1px)}
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

    .dh-table-wrap{overflow:auto;max-height:480px}
    .dh-table{width:100%;border-collapse:collapse;min-width:840px}
    .dh-table th{padding:8px 9px;font-size:8px;color:#98a2b3;text-transform:uppercase;letter-spacing:.035em;text-align:left;border-bottom:1px solid #eaecf0;white-space:nowrap;font-weight:900;position:sticky;top:0;background:#fff;z-index:1}
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
    .dh-row-selected td:first-child{border-left:3px solid #e30613}
    .dh-row-selected .dh-designer-link{color:#e30613}
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
    .dh-line-chart .trend-assigned{stroke:#2970ff}
    .dh-line-chart .trend-completed{stroke:#027a48}
    .dh-line-chart .trend-progress{stroke:#3538cd}
    .dh-line-chart .trend-rating{stroke:#f5b301;stroke-dasharray:4 3}
    .dh-line-chart .trend-point{stroke:#fff;stroke-width:1.5}
    .dh-line-chart .trend-point-assigned{fill:#2970ff}
    .dh-line-chart .trend-point-completed{fill:#027a48}
    .dh-line-chart .trend-point-progress{fill:#3538cd}
    .dh-line-chart .trend-point-rating{fill:#f5b301}
    .dh-line-chart .trend-label{font-size:8px;fill:#98a2b3;font-weight:700}
    .dh-line-chart .trend-value{font-size:8px;fill:#344054;font-weight:900}
    .dh-line-chart .trend-value-rating{fill:#b27a00}
    .dh-chart-legend{display:flex;gap:14px;margin-top:14px;font-size:8px;color:#667085;font-weight:750;flex-wrap:wrap}
    .dh-chart-legend span{display:inline-flex;align-items:center;gap:5px}
    .dh-legend-dot{width:8px;height:8px;border-radius:2px;display:inline-block}
    .dh-legend-dashed{border-top:2px dashed #f5b301;background:transparent;height:0;width:14px}
    .dh-line-chart .chart-axis-label{font-size:8px;fill:#98a2b3;font-weight:800}
    .dh-line-chart .chart-axis-rating{fill:#b27a00}
    .dh-line-chart .trend-point{cursor:pointer}
    .dh-trend-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
    .dh-trend-stat{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:10px 12px;display:flex;flex-direction:column;gap:2px}
    .dh-trend-stat-label{font-size:8px;color:#667085;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
    .dh-trend-stat-value{font-size:20px;font-weight:950;color:#101828}
    .dh-trend-stat-value em{font-style:normal;font-size:10px;color:#98a2b3;font-weight:750}
    .dh-trend-rating-head{display:flex;align-items:center;justify-content:space-between;margin:16px 0 4px}
    .dh-trend-rating-title{font-size:10px;font-weight:900;color:#344054}
    .dh-trend-rating-scale{font-size:8px;color:#98a2b3;font-weight:750}
    .dh-rating-chart{background:linear-gradient(180deg,#fffbeb 0,#fff 40%);border-radius:12px;padding-top:6px}



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

    .dh-stars{display:inline-flex;gap:2px;line-height:1}
    .dh-star{--star-fill:0%;display:inline-block;width:13px;height:13px;flex:0 0 13px;font-size:13px;line-height:13px;font-family:Arial,"Segoe UI Symbol",sans-serif;background:linear-gradient(90deg,#f5b301 0%,#f5b301 var(--star-fill),#d8dee8 var(--star-fill),#d8dee8 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}

    .dh-scroll{overflow-y:auto;overscroll-behavior:contain}

    .dhr-scroll{max-height:560px;overflow-y:auto;overscroll-behavior:contain}
    .dhr-list{display:flex;flex-direction:column;gap:9px;padding:12px 13px}
    .dhr-row{border:1px solid #e6e9ef;border-radius:12px;padding:12px;display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start}
    .dhr-col{min-width:0}
    .dhr-col-task{flex:1.1 1 190px}
    .dhr-task-name{font-size:10px;font-weight:900;color:#101828;line-height:1.4}
    .dhr-designer-name{font-size:9px;color:#475467;margin-top:4px;font-weight:750}
    .dhr-completed-date{font-size:8px;color:#98a2b3;margin-top:5px}
    .dhr-col-ratings{flex:1.7 1 300px;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap}
    .dhr-overall{flex:0 0 auto}
    .dhr-overall-value{font-size:16px;font-weight:950;color:#101828;line-height:1.2}
    .dhr-overall-value span{font-size:9px;font-weight:800;color:#98a2b3}
    .dhr-chips{display:grid;grid-template-columns:repeat(3,minmax(50px,1fr));gap:6px;flex:1 1 180px}
    .dhr-chip{border:1px solid #eef0f3;background:#fbfbfc;border-radius:8px;padding:5px 7px;text-align:center;cursor:default}
    .dhr-chip-label{display:block;font-size:7px;font-weight:900;color:#98a2b3;text-transform:uppercase;letter-spacing:.03em}
    .dhr-chip-value{display:block;font-size:10px;font-weight:900;color:#101828;margin-top:2px}
    .dhr-col-comment{flex:1.2 1 210px}
    .dhr-comment-label{font-size:8px;font-weight:900;color:#667085;text-transform:uppercase;margin-bottom:3px}
    .dhr-comment-text{font-size:9px;color:#475467;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .dhr-row.dhr-expanded .dhr-comment-text{-webkit-line-clamp:unset;overflow:visible}
    .dhr-comment-toggle{margin-top:5px;border:1px solid #d0d5dd;background:#fff;border-radius:7px;padding:3px 8px;font-size:8px;font-weight:850;color:#344054;cursor:pointer}
    .dhr-col-by{flex:0 0 130px;text-align:right}
    .dhr-by-name{font-size:9px;font-weight:900;color:#101828}
    .dhr-by-date{font-size:8px;color:#98a2b3;margin-top:3px}
    .dhr-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 13px;border-top:1px solid #eaecf0;flex-wrap:wrap}
    .dhr-foot-info{font-size:8px;color:#667085;font-weight:800}
    .dhr-pager{display:flex;align-items:center;gap:4px}
    .dhr-pager button{border:1px solid #d0d5dd;background:#fff;border-radius:7px;min-width:24px;height:24px;font-size:9px;font-weight:850;color:#344054;cursor:pointer}
    .dhr-pager button[disabled]{opacity:.4;cursor:not-allowed}
    .dhr-pager button.dhr-page-active{background:#101828;color:#fff;border-color:#101828}
    .dhr-page-ellipsis{font-size:9px;color:#98a2b3;padding:0 2px}

    .bd-dash-actions{display:flex;align-items:center;gap:8px}
    .bd-dash-btn{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:9px;text-decoration:none;font-size:9px;font-weight:900}
    .bd-dash-btn.primary{background:#e30613;color:#fff}
    .bd-dash-btn.secondary{background:#fff;color:#344054;border:1px solid #d0d5dd}

    /* ===== Unified visual system (mirrors the Designer dashboard) ===== */
    .dsh-hero{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:160px;padding:18px 22px;border:1px solid #eef0f3;border-radius:16px;background:#fff url('{{ asset('images/dashboard-hero-bg.webp') }}') center/cover no-repeat;overflow:hidden}
    .dsh-hero-text{min-width:0;position:relative;z-index:1}
    .dsh-eyebrow{display:flex;align-items:center;gap:8px;font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#667085}
    .dsh-eyebrow span{width:26px;height:3px;border-radius:3px;background:#e30613}
    .dsh-hero h1{margin:8px 0 0;font-size:26px;line-height:1.15;font-weight:900;color:#101828}
    .dsh-hero h1 em{font-style:normal;color:#e30613}
    .dsh-hero p{margin:6px 0 0;font-size:12px;color:#667085}
    .dsh-login{display:flex;align-items:center;gap:7px;margin-top:10px;font-size:11px;color:#667085}
    .dsh-login svg{width:14px;height:14px;flex:none}
    .dsh-login strong{color:#344054}
    .dsh-hero-art{position:absolute;right:320px;top:0;height:100%;width:auto;max-width:48%;object-fit:contain;object-position:right center;mix-blend-mode:multiply;pointer-events:none;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 18%,#000 82%,transparent 100%);mask-image:linear-gradient(90deg,transparent 0,#000 18%,#000 82%,transparent 100%)}
    .dsh-actions{display:flex;gap:9px;position:relative;z-index:1;flex:none}
    .dsh-btn{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:8px 16px;border-radius:10px;text-decoration:none;font-size:12px;font-weight:800;transition:.15s}
    .dsh-btn svg{width:15px;height:15px;flex:none}
    .dsh-btn.primary{background:#e30613;color:#fff;box-shadow:0 6px 14px rgba(227,6,19,.22)}
    .dsh-btn.primary:hover{background:#c8050f}
    .dsh-btn.secondary{background:#fff;color:#344054;border:1px solid #e4e7ec;box-shadow:0 1px 3px rgba(16,24,40,.06)}
    .dsh-btn.secondary:hover{border-color:#e30613;color:#e30613}

    .dh-toolbar{display:grid;grid-template-columns:minmax(260px,2.4fr) minmax(170px,1fr) minmax(170px,1fr);gap:12px;align-items:end;padding:12px 14px;border:1px solid #eceff4;border-radius:14px;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.05)}
    .dh-tb-search{position:relative;display:flex;align-items:center;height:42px;border:1px solid #dfe3ea;border-radius:10px;background:#fff}
    .dh-tb-search svg{position:absolute;left:13px;width:16px;height:16px;color:#667085;pointer-events:none}
    .dh-tb-search input{width:100%;height:100%;border:0;background:transparent;padding:0 12px 0 38px;font-size:12.5px;color:#101828;outline:none;border-radius:10px}
    .dh-tb-search:focus-within,.dh-tb-field .dh-select:focus{border-color:#e30613;box-shadow:0 0 0 3px rgba(227,6,19,.1)}
    .dh-tb-field{display:flex;flex-direction:column;gap:5px;min-width:0}
    .dh-tb-field span{font-size:10px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#667085}
    .dh-tb-field .dh-select{width:100%;height:42px;font-size:12.5px;font-weight:700}

    .dsh-grid{display:grid;gap:12px}
    .dsh-primary{grid-template-columns:1.45fr repeat(5,minmax(0,1fr))}
    .dsh-secondary{grid-template-columns:repeat(5,minmax(0,1fr))}
    .dsh-extra{grid-template-columns:repeat(4,minmax(0,1fr))}
    .dsh-card{--t1:#f8fafc;--t2:#f8fafc;--ic:#475467;--icbg:#eef1f5;display:flex;align-items:center;gap:12px;min-width:0;min-height:96px;padding:14px 15px;border-radius:14px;border:1px solid rgba(16,24,40,.07);background:linear-gradient(135deg,var(--t1),var(--t2));color:inherit;text-decoration:none;box-shadow:0 1px 3px rgba(16,24,40,.05);transition:.15s}
    .dsh-card>div{min-width:0}
    .dsh-card.is-link{cursor:pointer}
    .dsh-card.is-link:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(16,24,40,.1);border-color:rgba(227,6,19,.35)}
    .dsh-icon{display:grid;place-items:center;width:42px;height:42px;flex:none;border-radius:12px;background:var(--icbg);color:var(--ic)}
    .dsh-icon svg{width:22px;height:22px}
    .dsh-label{font-size:11.5px;font-weight:700;color:#475467;line-height:1.25}
    .dsh-value{font-size:26px;font-weight:900;line-height:1.1;color:#101828;margin-top:3px}
    .dsh-note{font-size:10.5px;color:rgba(255,255,255,.85);margin-top:4px;line-height:1.3}
    .dsh-sub-note{font-size:10px;color:#98a2b3;margin-top:3px;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
    .dsh-total{background:linear-gradient(135deg,#e30613,#b8040f);border-color:#b8040f;color:#fff;box-shadow:0 12px 24px rgba(227,6,19,.25)}
    .dsh-total .dsh-icon{background:rgba(255,255,255,.2);color:#fff}
    .dsh-total .dsh-label{color:rgba(255,255,255,.92)}
    .dsh-total .dsh-value{color:#fff;font-size:32px}
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
    .dsh-card.is-alert{border-color:#f5b5b0;box-shadow:0 0 0 3px rgba(220,38,38,.07)}
    .dsh-card.is-alert .dsh-value{color:#c01048}
    .dsh-completion{display:flex;align-items:center;gap:16px;padding:14px 18px;border:1px solid #eceff4;border-radius:14px;background:#fff;box-shadow:0 1px 3px rgba(16,24,40,.05)}
    .dsh-completion-label{font-size:13px;font-weight:900;color:#101828;white-space:nowrap}
    .dsh-completion-label span{display:block;font-size:10.5px;font-weight:600;color:#98a2b3;margin-top:2px}
    .dsh-progress-track{flex:1;height:11px;border-radius:999px;background:#f2f4f7;overflow:hidden}
    .dsh-completion-value{min-width:56px;text-align:center;padding:6px 10px;border-radius:10px;background:#fff1e0;font-size:15px;font-weight:900;color:#101828}

    /* Section cards: icon badge header + readable type */
    .dh-card{border-radius:16px;border-color:#eceff4;box-shadow:0 1px 3px rgba(16,24,40,.05)}
    .dh-card-head{justify-content:flex-start;gap:12px;padding:14px 16px;background:linear-gradient(180deg,#fff7f8,#fff);border-bottom-color:#f3e3e6}
    .dh-card-head>div:not(.dh-card-badge){flex:1;min-width:0}
    .dh-card-head .dh-card-badge,.dh-card-head>.dh-select{margin-left:auto}
    .dh-card-icon{display:grid;place-items:center;width:38px;height:38px;flex:none;border-radius:11px;background:var(--icbg);color:var(--ic)}
    .dh-card-icon svg{width:19px;height:19px}
    .dh-card-title{font-size:15px;font-weight:900}
    .dh-card-sub{font-size:11px;margin-top:3px}
    .dh-card-badge{min-width:26px;height:26px;font-size:11px;background:#e30613}
    .dh-table th{font-size:10px;padding:11px 12px;background:#fafbfc;color:#667085;letter-spacing:.05em}
    .dh-table td{font-size:11.5px;padding:11px 12px}
    .dh-table{min-width:1100px}
    .dh-table td{white-space:nowrap}
    .dh-table td.dh-cell-main{white-space:normal;min-width:190px;max-width:260px}
    .dh-table tbody tr:hover td{background:#fff8f8}
    .dh-cell-sub{font-size:10.5px}
    .dh-pill{font-size:10px;padding:4px 9px;min-height:22px}
    .dh-progress-note{font-size:10px}
    .dh-empty{font-size:12px;padding:30px 12px}
    .dh-bar-label{font-size:11.5px}
    .dh-bar-value{font-size:13px}
    .dh-req-top strong{font-size:12px}
    .dh-req-reason{font-size:11px}
    .dhr-task-name{font-size:12px}.dhr-designer-name,.dhr-comment-text,.dhr-by-name{font-size:11px}
    .dhr-chip-label{font-size:8.5px}.dhr-chip-value{font-size:11.5px}
    .dhr-completed-date,.dhr-by-date,.dhr-foot-info,.dhr-comment-label{font-size:10px}

    /* Monthly Analytics fills the row beside the tall Performance Trend card */
    .dh-grid-fill{align-items:stretch}
    .dh-card-fill{display:flex;flex-direction:column}
    /* chart hover states */
    .dh-tip{position:fixed;z-index:100001;display:none;pointer-events:none;min-width:150px;max-width:260px;padding:10px 12px;border-radius:12px;background:#101828;color:#fff;box-shadow:0 14px 34px rgba(16,24,40,.32);font-size:11.5px;line-height:1.35}
    .dh-tip-title{font-size:12.5px;font-weight:900;margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.14)}
    .dh-tip-row{display:flex;align-items:center;gap:8px;margin-top:4px}
    .dh-tip-row i{width:9px;height:9px;border-radius:3px;flex:none}
    .dh-tip-row span{flex:1;color:rgba(255,255,255,.78)}
    .dh-tip-row b{font-weight:900}
    .dh-tip-hint{margin-top:8px;font-size:10px;color:rgba(255,255,255,.55)}
    .dh-bar-row[data-hover]{padding:6px 8px;margin:-6px -8px;border-radius:10px;cursor:pointer;transition:background .15s,opacity .15s}
    .dh-bar-row.is-active{background:#fff7f0}
    .dh-bar-row.is-dim{opacity:.4}
    .dh-bar-row.is-active .dh-bar-fill{filter:brightness(1.1);box-shadow:0 2px 8px rgba(16,24,40,.2)}
    .dh-bar-fill{transform-origin:left center;animation:dhGrow .7s cubic-bezier(.2,.8,.2,1) both}
    @keyframes dhGrow{from{transform:scaleX(0)}}
    .dh-donut{cursor:pointer;transition:transform .2s}
    .dh-donut:hover{transform:scale(1.02)}
    .dh-donut-hole strong{transition:color .15s}
    .dh-donut-hole span{text-align:center;max-width:90px;line-height:1.2}
    .dh-leg{text-decoration:none;padding:4px 6px;margin:-4px -6px;border-radius:8px;transition:background .15s,opacity .15s}
    .dh-leg.is-active{background:#f2f4f7}
    .dh-leg.is-dim{opacity:.4}
    .pt-guide{stroke:#d0d5dd;stroke-width:1;stroke-dasharray:3 3;pointer-events:none}
    .pt-point{transition:r .12s}
    .pt-bar{transition:opacity .15s}
    .pt-bar.pt-dim{opacity:.3}
    .pt-hover-strip{cursor:crosshair}
    .dh-card-fill .dh-card-body{padding:20px 18px;gap:20px}
    .dh-card-fill .dh-bar-row{grid-template-columns:120px minmax(0,1fr) 38px}
    .dh-card-fill .dh-bar-track{height:12px}
    .dh-mix{flex:1;display:flex;flex-direction:column;justify-content:center;padding:18px 18px 20px;border-top:1px dashed #eaecf0;background:linear-gradient(180deg,#fff,#fcfcfd)}
    .dh-mix-title{display:flex;justify-content:space-between;align-items:baseline;font-size:13px;font-weight:900;color:#101828;margin-bottom:12px}
    .dh-mix-title span{font-size:11px;font-weight:700;color:#98a2b3}
    .dh-mix-body{display:flex;align-items:center;gap:22px;flex-wrap:wrap}
    .dh-donut{position:relative;width:180px;height:180px;border-radius:50%;flex:none}
    .dh-donut-hole{position:absolute;inset:32px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px #f2f4f7}
    .dh-donut-hole strong{font-size:26px;font-weight:900;color:#101828;line-height:1}
    .dh-donut-hole span{font-size:10.5px;color:#98a2b3;margin-top:3px;font-weight:700}
    .dh-mix-legend{flex:1;min-width:200px;display:grid;grid-template-columns:1fr 1fr;gap:9px 18px}
    .dh-mix-legend div,.dh-mix-legend a{display:flex;align-items:center;gap:8px;font-size:11.5px;color:#475467;min-width:0}
    .dh-mix-legend i{width:10px;height:10px;border-radius:3px;flex:none}
    .dh-mix-legend span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .dh-mix-legend b{font-weight:900;color:#101828}
    @media(max-width:1400px){.dsh-primary{grid-template-columns:repeat(3,minmax(0,1fr))}.dsh-primary>.dsh-total{grid-column:1/-1}.dsh-secondary{grid-template-columns:repeat(3,minmax(0,1fr))}.dsh-extra{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:1180px){.dsh-hero-art{display:none}}
    @media(max-width:960px){.dsh-hero{flex-direction:column;align-items:flex-start}.dsh-hero-art{display:none}.dh-toolbar{grid-template-columns:1fr 1fr}.dh-tb-search{grid-column:1/-1}}
    @media(max-width:640px){.dsh-hero h1{font-size:21px}.dsh-actions{width:100%}.dsh-btn{flex:1;justify-content:center}.dsh-primary,.dsh-secondary,.dsh-extra,.dh-toolbar{grid-template-columns:repeat(2,minmax(0,1fr))}.dsh-card{padding:11px;gap:9px}.dsh-completion{flex-wrap:wrap}.dsh-progress-track{order:3;flex-basis:100%}}
    @media(max-width:1280px){.dh-grid-2{grid-template-columns:1fr}}
    @media(max-width:760px){.dh-top{align-items:flex-start;flex-direction:column}.dh-decision{grid-template-columns:1fr}.dh-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dhr-row{flex-direction:column}.dhr-col-by{text-align:left}}
</style>

<div class="dh-dash">
    <div class="dsh-hero">
        <div class="dsh-hero-text">
            <div class="dsh-eyebrow"><span></span>BD Dashboard</div>
            <h1>Welcome back, <em>{{ auth()->user()->name }}</em></h1>
            <p>Live insight into your tickets, Designers, quality, rework and reviews.</p>
            <div class="dsh-login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                <span><strong>Last Login:</strong> {{ optional(auth()->user()->last_login_at)->format('d M Y \• h:i A') ?? 'This is your first login' }}</span>
            </div>
        </div>
        <img class="dsh-hero-art" src="{{ asset('images/dashboard-hero.webp') }}" alt="" aria-hidden="true" loading="eager">
        <div class="dsh-actions">
            <a class="dsh-btn secondary" href="{{ route('bd.tasks.index') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/></svg>View All Tasks</a>
            <a class="dsh-btn primary" href="{{ route('bd.tasks.create') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>Create Task</a>
        </div>
    </div>

    <div class="dh-toolbar">
        <label class="dh-tb-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="dh-search" placeholder="Search tasks, project, client, designer, BD..." value="{{ $search ?? '' }}" autocomplete="off">
        </label>
        <label class="dh-tb-field"><span>Designer</span>
            <select class="dh-select" id="dh-designer" aria-label="Filter by Designer">
                <option value="all">All Designers</option>
                @foreach($designers as $designer)
                    <option value="{{ $designer->id }}" @selected((int) $selectedDesigner === (int) $designer->id)>{{ $designer->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="dh-tb-field"><span>Month</span>
            <select class="dh-select" id="dh-month" aria-label="Filter by Month">
                @foreach($months as $monthOption)
                    <option value="{{ $monthOption['value'] }}" @selected($selectedMonth === $monthOption['value'])>{{ $monthOption['label'] }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div id="dh-root" class="dh-zone">
        @include('bd.dashboard-partial', [
            'stats' => $stats,
            'designers' => $designers,
            'selectedDesigner' => $selectedDesigner,
            'selectedDesignerName' => $selectedDesignerName,
            'workload' => $workload,
            'bar' => $bar,
            'line' => $line,
            'taskRows' => $taskRows,
            'reworkRows' => $reworkRows,
            'bdReviewRows' => $bdReviewRows,
            'completedRatings' => $completedRatings,
            'completions' => $completions,
            'overdue' => $overdue,
            'pendingRequests' => $pendingRequests,
            'recentDecisions' => $recentDecisions,
            'search' => $search,
        ])
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('dh-root');
    var base = "{{ route('bd.dashboard.partial') }}";

    function reload() {
        var designer = document.getElementById('dh-designer').value;
        var month = document.getElementById('dh-month').value;
        var search = document.getElementById('dh-search').value;
        root.classList.add('dh-loading');
        fetch(base + '?designer=' + encodeURIComponent(designer) + '&month=' + encodeURIComponent(month) + '&search=' + encodeURIComponent(search), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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

    var searchDebounce = null;
    document.getElementById('dh-search').addEventListener('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(reload, 350);
    });

    root.addEventListener('click', function (e) {
        var el = e.target.closest('[data-dh-design]');
        if (!el) return;
        document.getElementById('dh-designer').value = el.dataset.dhDesign;
        reload();
    });

    /* ===== Chart hover interactivity (bars, donut, trend charts) ===== */
    var tip = document.createElement('div');
    tip.className = 'dh-tip';
    document.body.appendChild(tip);

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function showTip(html, e) {
        tip.innerHTML = html;
        tip.style.display = 'block';
        var w = tip.offsetWidth, h = tip.offsetHeight;
        var x = e.clientX + 16, y = e.clientY + 16;
        if (x + w > window.innerWidth - 8) x = e.clientX - w - 16;
        if (y + h > window.innerHeight - 8) y = e.clientY - h - 16;
        tip.style.left = Math.max(8, x) + 'px';
        tip.style.top = Math.max(8, y) + 'px';
    }
    function row(color, label, value) {
        return '<div class="dh-tip-row"><i style="background:' + color + '"></i><span>' + esc(label) + '</span><b>' + esc(value) + '</b></div>';
    }

    var seriesColors = { 'Assigned': '#2970ff', 'In Progress': '#7c3aed', 'Completed': '#027a48', 'Overdue & Completed': '#f79009', 'Declined': '#c01048' };

    function donutPaint(donut, activeIdx) {
        var segs = JSON.parse(donut.dataset.segs || '[]');
        if (!segs.length) return segs;
        var stops = segs.map(function (s) {
            var c = (activeIdx === null || activeIdx === s.i) ? s.color : 'color-mix(in srgb, ' + s.color + ' 28%, #fff)';
            return c + ' ' + s.from + '% ' + s.to + '%';
        });
        donut.style.background = 'conic-gradient(' + stops.join(',') + ')';
        return segs;
    }
    function donutHole(donut, seg) {
        var hole = donut.querySelector('.dh-donut-hole');
        if (!hole) return;
        hole.querySelector('strong').textContent = seg ? seg.value : (donut.dataset.total || '0');
        hole.querySelector('span').textContent = seg ? seg.label : 'Tasks';
    }

    function resetHover() {
        root.querySelectorAll('.is-dim,.is-active,.pt-dim').forEach(function (el) { el.classList.remove('is-dim', 'is-active', 'pt-dim'); });
        root.querySelectorAll('.pt-guide').forEach(function (el) { el.remove(); });
        root.querySelectorAll('.pt-point[data-r]').forEach(function (c) { c.setAttribute('r', c.dataset.r); });
        root.querySelectorAll('.dh-donut').forEach(function (d) { donutPaint(d, null); donutHole(d, null); });
        tip.style.display = 'none';
    }

    function hoverSeg(idx, e) {
        var donut = root.querySelector('.dh-donut');
        if (!donut) return;
        var segs = donutPaint(donut, idx);
        var seg = segs.filter(function (s) { return s.i === idx; })[0];
        donutHole(donut, seg || null);
        root.querySelectorAll('.dh-leg').forEach(function (l) {
            l.classList.toggle('is-active', Number(l.dataset.segIdx) === idx);
            l.classList.toggle('is-dim', Number(l.dataset.segIdx) !== idx);
        });
        if (seg) showTip('<div class="dh-tip-title">' + esc(seg.label) + '</div>' + row(seg.color, 'Tasks', seg.value) + row('#98a2b3', 'Share', seg.pct + '%') + '<div class="dh-tip-hint">Click to open these tasks</div>', e);
        else if (e) {
            var l = root.querySelector('.dh-leg[data-seg-idx="' + idx + '"]');
            if (l) showTip('<div class="dh-tip-title">' + esc(l.querySelector('span').textContent) + '</div>' + row('#d0d5dd', 'Tasks', l.querySelector('b').textContent) + '<div class="dh-tip-hint">Click to open these tasks</div>', e);
        }
    }

    root.addEventListener('mousemove', function (e) {
        var t = e.target;
        var leg = t.closest && t.closest('.dh-leg');
        var donut = t.closest && t.closest('.dh-donut');
        var barRow = t.closest && t.closest('.dh-bar-row[data-hover]');
        var strip = t.closest && t.closest('.pt-hover-strip');
        var bar = t.closest && t.closest('.pt-bar');

        resetHover();

        if (leg) { hoverSeg(Number(leg.dataset.segIdx), e); return; }

        if (donut) {
            var r = donut.getBoundingClientRect();
            var dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
            var dist = Math.sqrt(dx * dx + dy * dy), outer = r.width / 2, inner = outer * 0.64;
            if (dist >= inner && dist <= outer) {
                var ang = Math.atan2(dx, -dy) * 180 / Math.PI; if (ang < 0) ang += 360;
                var pct = ang / 360 * 100;
                var segs = JSON.parse(donut.dataset.segs || '[]');
                for (var i = 0; i < segs.length; i++) {
                    if (pct >= segs[i].from && pct < segs[i].to) { hoverSeg(segs[i].i, e); return; }
                }
            }
            return;
        }

        if (barRow) {
            root.querySelectorAll('.dh-bar-row[data-hover]').forEach(function (b) { b.classList.toggle('is-dim', b !== barRow); });
            barRow.classList.add('is-active');
            showTip('<div class="dh-tip-title">' + esc(barRow.dataset.label) + '</div>' + row(barRow.dataset.color, 'Tasks', barRow.dataset.value) + (barRow.dataset.label !== 'Assigned Tasks' ? row('#98a2b3', 'Of assigned', barRow.dataset.share + '%') : ''), e);
            return;
        }

        if (strip) {
            var svg = strip.ownerSVGElement;
            var titleEl = strip.querySelector('title');
            if (titleEl) { strip.dataset.tip = titleEl.textContent; titleEl.remove(); }
            var lines = (strip.dataset.tip || '').split('\n');
            var x0 = parseFloat(strip.getAttribute('x')), w0 = parseFloat(strip.getAttribute('width')), cx = x0 + w0 / 2;
            var g = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            g.setAttribute('class', 'pt-guide');
            g.setAttribute('x1', cx); g.setAttribute('x2', cx);
            g.setAttribute('y1', 0); g.setAttribute('y2', strip.getAttribute('height'));
            svg.insertBefore(g, svg.querySelector('.pt-hover-strip'));
            svg.querySelectorAll('.pt-point').forEach(function (c) {
                if (!c.dataset.r) c.dataset.r = c.getAttribute('r');
                if (Math.abs(parseFloat(c.getAttribute('cx')) - cx) < 1) c.setAttribute('r', parseFloat(c.dataset.r) + 2.5);
            });
            var html = '<div class="dh-tip-title">' + esc(lines[0] || '') + '</div>';
            lines.slice(1).forEach(function (ln) {
                var p = ln.split(':'); html += row(seriesColors[p[0].trim()] || '#98a2b3', p[0].trim(), (p[1] || '').trim());
            });
            showTip(html, e);
            return;
        }

        if (bar) {
            var svg2 = bar.ownerSVGElement;
            var tEl = bar.querySelector('title');
            if (tEl) { bar.dataset.tip = tEl.textContent; tEl.remove(); }
            svg2.querySelectorAll('.pt-bar').forEach(function (b) { if (b !== bar) b.classList.add('pt-dim'); });
            var parts = (bar.dataset.tip || '').split(' · ');
            var name = parts[0] || '', rest = (parts[1] || '').split(':');
            showTip('<div class="dh-tip-title">' + esc((rest[0] || '').trim()) + '</div>' + row(seriesColors[name] || '#98a2b3', name, (rest[1] || '').trim()), e);
        }
    });
    root.addEventListener('mouseleave', resetHover);
    window.addEventListener('scroll', resetHover, true);

    /* Click a donut segment to open that status in the task list */
    root.addEventListener('click', function (e) {
        var donut = e.target.closest && e.target.closest('.dh-donut');
        if (!donut) return;
        var r = donut.getBoundingClientRect();
        var dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
        var dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < r.width / 2 * 0.64 || dist > r.width / 2) return;
        var ang = Math.atan2(dx, -dy) * 180 / Math.PI; if (ang < 0) ang += 360;
        var pct = ang / 360 * 100;
        JSON.parse(donut.dataset.segs || '[]').forEach(function (s) {
            if (pct >= s.from && pct < s.to) {
                var l = root.querySelector('.dh-leg[data-seg-idx="' + s.i + '"]');
                if (l) window.location = l.getAttribute('href');
            }
        });
    });

    var ratingsBase = "{{ route('bd.dashboard.ratings') }}";

    function loadRatings(designer, page) {
        var body = document.getElementById('dhr-root');
        if (!body) return;
        var month = document.getElementById('dh-month').value;
        body.classList.add('dh-loading');
        fetch(ratingsBase + '?designer=' + encodeURIComponent(designer) + '&page=' + encodeURIComponent(page) + '&month=' + encodeURIComponent(month), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                body.replaceChildren.apply(body, Array.prototype.slice.call(tmp.childNodes));
                body.classList.remove('dh-loading');
            })
            .catch(function () { body.classList.remove('dh-loading'); });
    }

    root.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'dhr-designer') {
            loadRatings(e.target.value, 1);
        }
    });

    root.addEventListener('click', function (e) {
        var pageBtn = e.target.closest('[data-dhr-page]');
        if (pageBtn && !pageBtn.disabled) {
            var designerSelect = document.getElementById('dhr-designer');
            loadRatings(designerSelect ? designerSelect.value : 'all', pageBtn.dataset.dhrPage);
            return;
        }
        var commentBtn = e.target.closest('[data-dhr-comment-toggle]');
        if (commentBtn) {
            var row = commentBtn.closest('.dhr-row');
            if (!row) return;
            var expanded = row.classList.toggle('dhr-expanded');
            commentBtn.textContent = expanded ? 'Show less' : 'View full comment';
        }
    });
})();
</script>
@endsection
