@extends('layouts.app')

@section('title',$task->task_id)
@section('workspace-title','BD Task Detail')
@section('workspace-subtitle','Review the complete task, collaboration, audit trail and production progress')

@section('content')
<style>
    [x-cloak]{display:none!important}.bd-detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;max-width:100%;overflow:auto;margin-bottom:14px}
    .bd-detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
    .bd-detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}.bd-tab-count{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#fff0f1;color:#e30613;font-size:8px;display:grid;place-items:center}
    .bd-tab-panel{margin-top:0}.bd-history-list,.bd-edit-history-list{display:flex;flex-direction:column;gap:10px}.bd-history-item{border:1px solid #e8eaef;border-left:4px solid #98a2b3;border-radius:10px;background:#fff;padding:11px 12px}.bd-history-item.role-bd{border-left-color:#e30613}.bd-history-item.role-designer{border-left-color:#2563eb}.bd-history-item.role-designer_head{border-left-color:#7c3aed}.bd-history-item.role-admin{border-left-color:#111827}.bd-history-title{font-size:11px;font-weight:850;color:#1d2939}.bd-history-meta{font-size:9px;color:#7a8493;margin-top:4px}
    .bd-request-card{border:1px solid #e6e9ef;border-radius:12px;background:#fff;padding:12px;margin-bottom:9px}.bd-request-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.bd-request-title{font-size:11px;font-weight:900}.bd-request-meta{font-size:9px;color:#667085;margin-top:4px}.bd-request-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:10px}.bd-request-field{background:#f8f9fb;border-radius:8px;padding:8px;font-size:9px;color:#667085}.bd-request-field strong{display:block;color:#344054;font-size:8px;text-transform:uppercase;margin-bottom:3px}
    .bd-edit-batch{border:1px solid #e7e9ee;border-radius:12px;overflow:hidden}.bd-edit-head{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:#f8f9fb;border-bottom:1px solid #eceef2;font-size:9px;color:#667085}.bd-edit-head strong{font-size:10px;color:#344054}.bd-edit-row{padding:11px 12px;border-bottom:1px solid #f0f1f3}.bd-edit-row:last-child{border-bottom:0}.bd-edit-field{font-size:9px;font-weight:900;text-transform:uppercase;color:#667085;margin-bottom:7px}.bd-edit-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}.bd-old,.bd-new{padding:9px;border-radius:9px;font-size:10px;overflow-wrap:anywhere}.bd-old{background:#fff1f1;border:1px solid #fecaca;color:#9b1c1c}.bd-new{background:#ecfdf3;border:1px solid #abefc6;color:#067647;font-weight:750}.bd-arrow{text-align:center;color:#98a2b3;font-weight:900}
    .bd-eod-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:12px}.bd-eod-card{padding:12px;border-radius:10px;background:#f8f9fb;border:1px solid #eaecf0}.bd-eod-card span{display:block;font-size:8px;font-weight:900;text-transform:uppercase;color:#667085}.bd-eod-card strong{display:block;font-size:19px;margin-top:4px;color:#101828}.bd-eod-row{display:grid;grid-template-columns:1.2fr repeat(4,.8fr);gap:8px;padding:10px;border:1px solid #eaecf0;border-radius:9px;margin-bottom:7px;font-size:9px}.bd-eod-row strong{display:block;font-size:8px;text-transform:uppercase;color:#667085;margin-bottom:2px}.bd-eod-card.rework-stat{background:#fff9eb;border-color:#f5d16a}.bd-eod-card.rework-stat span{color:#9a6700}.bd-eod-card.rework-stat strong{color:#7a5200}.bd-eod-row.is-rework{border-color:#f2ce68;background:linear-gradient(180deg,#fffdf7,#fff9e8);box-shadow:inset 4px 0 0 #f5b301}.bd-eod-row.is-progress{border-left:4px solid #d9dee7}.bd-eod-type-badge{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;margin-bottom:5px}.bd-eod-type-badge.rework{background:#fff0c2;color:#7a5200;border:1px solid #f2cf68}.bd-eod-type-badge.progress{background:#f2f4f7;color:#475467;border:1px solid #e4e7ec}@media(max-width:900px){.bd-eod-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.bd-eod-summary{grid-template-columns:1fr}}
    .bd-attachment-group{border:1px solid #e8eaef;border-radius:11px;padding:11px;margin-bottom:9px}.bd-attachment-title{font-size:10px;font-weight:850;margin-bottom:8px}.bd-file{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:8px 9px;background:#f8f9fb;border-radius:8px;margin-top:6px}.bd-file-name{font-size:9px;font-weight:750;overflow-wrap:anywhere}.bd-comment{border:1px solid #e8eaef;border-left:4px solid #98a2b3;border-radius:11px;padding:11px 12px;margin-bottom:9px}.bd-comment.role-bd{border-left-color:#e30613}.bd-comment.role-designer{border-left-color:#2563eb}.bd-comment-head{display:flex;justify-content:space-between;gap:10px;font-size:9px;color:#667085}.bd-comment-head strong{font-size:10px;color:#344054}.bd-comment-message{margin-top:8px;font-size:11px;line-height:1.55;font-weight:450;white-space:pre-wrap;color:#111827}
    .bd-review-box{margin-top:16px;border:1px solid #e4e7ec;border-radius:13px;background:#fcfcfd;padding:14px}.bd-review-box h3{margin:0 0 4px;font-size:11px;font-weight:900;color:#101828}.bd-review-box p{margin:0 0 12px;font-size:9px;color:#667085}.bd-review-grid{display:grid;grid-template-columns:180px minmax(0,1fr);gap:10px}.bd-review-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}.bd-danger-btn{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important}.bd-complete-btn{background:#16a34a!important;color:#fff!important;border-color:#16a34a!important}.bd-rating-panel{margin-top:12px;border-top:1px solid #eaecf0;padding-top:14px}.rating-row{display:grid;grid-template-columns:180px minmax(220px,1fr) 52px;gap:12px;align-items:center;padding:9px 0;border-bottom:1px solid #f2f4f7}.rating-label{font-size:9px;font-weight:850;color:#344054}.star-picker{display:flex;gap:4px;align-items:center}.star-unit{position:relative;width:25px;height:25px;font-size:24px;line-height:25px;color:#d0d5dd;display:inline-block;user-select:none}.star-unit .star-empty{position:absolute;inset:0}.star-unit .star-fill{position:absolute;inset:0;color:#f59e0b;overflow:hidden;white-space:nowrap;pointer-events:none}.star-half-hit{position:absolute;top:0;bottom:0;width:50%;border:0;background:transparent;cursor:pointer;padding:0;z-index:2}.star-half-hit.left{left:0}.star-half-hit.right{right:0}.rating-value{font-size:9px;font-weight:900;color:#344054}.overall-rating-card{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;padding:11px 12px;border-radius:10px;background:#f8fafc;border:1px solid #e4e7ec}.overall-rating-card span{font-size:9px;font-weight:850;color:#475467}.overall-rating-card strong{font-size:18px;color:#101828}.rating-summary-shell{
        border:1px solid #f1d07a;
        border-radius:14px;
        background:linear-gradient(180deg,#fffdf7 0%,#fff9e9 100%);
        padding:14px;
        box-shadow:0 4px 14px rgba(245,179,1,.06);
    }
    .rating-summary-top{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        margin-bottom:12px;
    }
    .rating-summary-kicker{
        font-size:8px;
        font-weight:950;
        letter-spacing:.045em;
        text-transform:uppercase;
        color:#8a6200;
    }
    .rating-summary-score{
        font-size:14px;
        font-weight:950;
        color:#624600;
        white-space:nowrap;
    }
    .rating-summary-stars{
        display:flex;
        align-items:center;
        gap:4px;
        margin-top:7px;
        line-height:1;
    }
    .rating-compact-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
        margin-top:10px;
    }
    .rating-compact-item{
        border:1px solid #eee3bd;
        border-radius:11px;
        background:rgba(255,255,255,.74);
        padding:10px 11px;
        min-width:0;
    }
    .rating-compact-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
    }
    .rating-compact-label{
        font-size:8px;
        font-weight:950;
        color:#5f6470;
        text-transform:uppercase;
        letter-spacing:.025em;
        line-height:1.35;
    }
    .rating-compact-score{
        font-size:10px;
        font-weight:950;
        color:#5d4300;
        white-space:nowrap;
    }
    .rating-compact-stars{
        display:flex;
        align-items:center;
        gap:3px;
        margin-top:7px;
        line-height:1;
    }
    .rating-static-star{
        --star-fill:0%;
        display:inline-block;
        width:17px;
        height:17px;
        flex:0 0 17px;
        font-size:17px;
        line-height:17px;
        font-family:Arial,"Segoe UI Symbol",sans-serif;
        background:linear-gradient(
            90deg,
            #f5b301 0%,
            #f5b301 var(--star-fill),
            #d8dee8 var(--star-fill),
            #d8dee8 100%
        );
        -webkit-background-clip:text;
        background-clip:text;
        -webkit-text-fill-color:transparent;
        color:transparent;
    }
    .rating-overall-item{
        border-color:#efcc69;
        background:#fffaf0;
    }
    .rating-meta-row{
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:10px;
        margin-top:10px;
    }
    .rating-comment-compact,
    .rating-submitted-compact{
        border:1px solid #e8e2cf;
        border-radius:10px;
        background:#fff;
        padding:10px 11px;
        font-size:9px;
        line-height:1.5;
        color:#475467;
    }
    .rating-comment-compact strong,
    .rating-submitted-compact strong{
        color:#101828;
        font-weight:900;
    }
    .rating-submitted-compact{
        min-width:220px;
    }
    @media(max-width:760px){
        .rating-compact-grid{grid-template-columns:1fr}
        .rating-meta-row{grid-template-columns:1fr}
        .rating-submitted-compact{min-width:0}
    }

    .board-table-shell{width:100%;max-width:100%;overflow-x:auto;border:1px solid #e4e7ec;border-radius:10px;background:#fff}
    .board-details-table{width:100%;min-width:560px;border-collapse:collapse;table-layout:auto}
    .board-details-table th{background:#f8fafc;color:#344054;font-size:9px;font-weight:850;text-align:left;padding:9px 10px;border-bottom:1px solid #e4e7ec;white-space:nowrap}
    .board-details-table td{color:#101828;font-size:9px;font-weight:500;padding:9px 10px;border-bottom:1px solid #eef0f3;white-space:nowrap}
    .board-details-table tbody tr:last-child td{border-bottom:0}
    .board-table-empty{text-align:center;color:#98a2b3!important;padding:18px!important}

    .bd-attachment-group{border:1px solid #e4e7ec;border-radius:12px;background:#fff;overflow:hidden;margin-bottom:10px;padding:0}
    .bd-attachment-title{font-size:9px;font-weight:900;color:#344054;background:#f8fafc;padding:9px 11px;border-bottom:1px solid #eaecf0;margin:0}
    .bd-file{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:9px 11px;background:#fff;border-radius:0;margin:0;border-bottom:1px solid #f2f4f7}
    .bd-file:last-child{border-bottom:0}
    .bd-file-main{min-width:0}
    .bd-file-name{font-size:9px;font-weight:650;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .bd-file-actions{display:flex;gap:6px;align-items:center;flex-wrap:nowrap}
    .bd-file-btn{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:6px 10px;border-radius:8px;text-decoration:none;font-size:8px;font-weight:850;white-space:nowrap}
    .bd-file-open{background:#e30613;color:#fff;border:1px solid #e30613}
    .bd-file-download{background:#fff;color:#344054;border:1px solid #d0d5dd}
    @media(max-width:700px){
        .bd-file{grid-template-columns:1fr}
        .bd-file-actions{justify-content:flex-start}
        .board-details-table{min-width:520px}
    }

    @media(max-width:750px){.bd-request-grid,.bd-eod-summary{grid-template-columns:1fr}.bd-edit-values,.bd-eod-row{grid-template-columns:1fr}.bd-arrow{transform:rotate(90deg)}}

        .progress-card{padding:13px;border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-top:12px}.progress-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.progress-title{font-size:10px;font-weight:900;color:#344054}.progress-value{font-size:11px;font-weight:950}.progress-track{height:9px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:8px}.progress-fill{height:100%;border-radius:999px;transition:width .25s}.progress-start .progress-fill{background:#94a3b8}.progress-low .progress-fill{background:#f59e0b}.progress-mid .progress-fill{background:#3b82f6}.progress-high .progress-fill{background:#8b5cf6}.progress-complete .progress-fill{background:#16a34a}.progress-start .progress-value{color:#64748b}.progress-low .progress-value{color:#b45309}.progress-mid .progress-value{color:#1d4ed8}.progress-high .progress-value{color:#7c3aed}.progress-complete .progress-value{color:#15803d}.collapse-panel{border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-bottom:14px;overflow:hidden}.collapse-panel summary{list-style:none;cursor:pointer;padding:12px 14px;font-size:11px;font-weight:900;color:#1d2939;display:flex;justify-content:space-between;align-items:center}.collapse-panel summary::-webkit-details-marker{display:none}.collapse-panel summary:after{content:'+';font-size:17px;color:#667085}.collapse-panel[open] summary:after{content:'−'}.collapse-panel .collapse-body{border-top:1px solid #eef0f3;padding:14px}.task-update-note{padding:10px 12px;border-radius:10px;background:#fffaeb;border:1px solid #fedf89;color:#93370d;font-size:10px;margin-bottom:12px}.rework-box{padding:13px;border:1px solid #fecaca;background:#fff7f7;border-radius:12px;margin-bottom:14px}.update-file{margin-top:8px;font-size:9px}.history-section-title{font-size:11px;font-weight:900;margin:18px 0 9px;color:#1d2939}.history-section-title:first-child{margin-top:0}

        .history-switcher{display:flex;gap:6px;padding:6px;background:#f4f5f7;border:1px solid #e4e7ec;border-radius:12px;margin-bottom:14px;width:max-content}
        .history-switch-btn{border:0;background:transparent;color:#667085;border-radius:8px;padding:8px 13px;font-size:10px;font-weight:900;cursor:pointer;transition:.16s ease}
        .history-switch-btn:hover{background:#fff;color:#101828}
        .history-switch-btn.active{background:#101828;color:#fff;box-shadow:0 4px 12px rgba(16,24,40,.14)}
        .history-view-card{border:1px solid #e5e7eb;border-radius:14px;background:#fff;overflow:hidden;box-shadow:0 5px 16px rgba(16,24,40,.04)}
        .history-view-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;background:linear-gradient(180deg,#fff,#f9fafb);border-bottom:1px solid #eaecf0}
        .history-view-title{font-size:12px;font-weight:950;color:#101828}
        .history-view-subtitle{font-size:9px;color:#667085;margin-top:3px}
        .history-view-count{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:4px 9px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:9px;font-weight:900}
        .history-view-body{padding:12px}
        .history-pipeline-card{border:1px solid #e7e9ee;border-left:4px solid #2563eb;border-radius:11px;background:#fff;padding:11px 12px;margin-bottom:8px}
        .history-pipeline-card:last-child{margin-bottom:0}
        .history-pipeline-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
        .history-pipeline-title{font-size:10px;font-weight:950;color:#101828}
        .history-pipeline-meta{margin-top:4px;font-size:9px;color:#667085;line-height:1.45}
        .history-role-badge{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:8px;font-weight:900;white-space:nowrap}
        .history-task-batch{border:1px solid #e7e9ee;border-left:4px solid #7c3aed;border-radius:11px;background:#fff;margin-bottom:9px;overflow:hidden}
        .history-task-batch:last-child{margin-bottom:0}
        .history-task-head{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;background:#faf9ff;border-bottom:1px solid #ede9fe}
        .history-task-editor{font-size:10px;font-weight:900;color:#4c1d95}
        .history-task-time{font-size:9px;color:#7c8492}
        .history-task-row{padding:10px 12px;border-bottom:1px solid #f0f1f3}
        .history-task-row:last-child{border-bottom:0}
        .history-task-field{font-size:8px;font-weight:950;text-transform:uppercase;letter-spacing:.045em;color:#667085;margin-bottom:7px}
        .history-task-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}
        .history-task-old,.history-task-new{padding:8px 9px;border-radius:8px;font-size:9px;line-height:1.45;overflow-wrap:anywhere}
        .history-task-old{background:#fff1f1;color:#b42318;border:1px solid #fecaca}
        .history-task-new{background:#ecfdf3;color:#067647;border:1px solid #abefc6;font-weight:800}
        .history-task-arrow{text-align:center;color:#98a2b3;font-weight:900}
        .history-nothing{text-align:center;padding:34px 14px;color:#98a2b3;font-size:10px}
        @media(max-width:700px){.history-task-values{grid-template-columns:1fr}.history-task-arrow{transform:rotate(90deg)}}


    /* Professional comments */
    .bd-comments-shell{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}
    .bd-comment-feed{display:flex;flex-direction:column;gap:10px}
    .bd-comment{margin:0;padding:14px;border-radius:13px;background:#fff;box-shadow:0 2px 8px rgba(16,24,40,.025)}
    .bd-comment-head{align-items:center}
    .bd-comment-person{display:flex;align-items:center;gap:9px;min-width:0}
    .bd-comment-avatar{width:30px;height:30px;border-radius:9px;background:#f2f4f7;display:grid;place-items:center;font-size:10px;font-weight:950;color:#344054;flex:0 0 auto}
    .bd-comment-name{font-size:10px;font-weight:900;color:#101828}
    .bd-comment-date{font-size:8px;color:#98a2b3;margin-top:2px}
    .bd-comment-message{font-size:10px;line-height:1.65;color:#344054;font-weight:450}
    .bd-comment-files{margin-top:11px;display:flex;flex-direction:column;gap:6px}
    .bd-comment-file{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 9px;border:1px solid #eaecf0;border-radius:9px;background:#fafbfc}
    .bd-comment-file-primary{min-width:0;display:flex;align-items:center;gap:7px}
    .bd-comment-file-name{font-size:9px;font-weight:750;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px}
    .bd-comment-open{font-size:8px;font-weight:900;color:#e30613;text-decoration:none;white-space:nowrap}
    .bd-comment-open:hover{text-decoration:underline}
    .bd-comment-download{font-size:8px;font-weight:800;color:#667085;text-decoration:none;white-space:nowrap}
    .bd-comment-composer{position:sticky;top:18px;border:1px solid #e4e7ec;border-radius:13px;background:#fff;padding:14px}
    .bd-comment-composer-title{font-size:12px;font-weight:900;color:#101828;margin-bottom:3px}
    .bd-comment-composer-subtitle{font-size:9px;color:#667085;margin-bottom:12px}
    @media(max-width:950px){.bd-comments-shell{grid-template-columns:1fr}.bd-comment-composer{position:static}.bd-comment-file-name{max-width:60vw}}


        /* Pipeline History role colors + responsive safety */
        .history-view-card{min-width:0}
        .history-view-body{min-width:0}
        .history-pipeline-card{
            min-width:0;
            overflow:hidden;
            border-left-width:4px!important;
            overflow-wrap:anywhere;
        }
        .history-pipeline-card.role-bd{border-left-color:#e30613!important;background:linear-gradient(90deg,#fff7f7 0,#fff 72px)}
        .history-pipeline-card.role-designer{border-left-color:#2563eb!important;background:linear-gradient(90deg,#f7fbff 0,#fff 72px)}
        .history-pipeline-card.role-designer_head{border-left-color:#7c3aed!important;background:linear-gradient(90deg,#faf8ff 0,#fff 72px)}
        .history-pipeline-card.role-admin{border-left-color:#111827!important;background:linear-gradient(90deg,#f7f8fa 0,#fff 72px)}
        .history-pipeline-card.role-default{border-left-color:#98a2b3!important}
        .history-role-badge.role-bd{background:#fff1f1;color:#b42318;border:1px solid #fecaca}
        .history-role-badge.role-designer{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
        .history-role-badge.role-designer_head{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
        .history-role-badge.role-admin{background:#111827;color:#fff;border:1px solid #111827}
        .history-role-badge.role-default{background:#f2f4f7;color:#667085;border:1px solid #e4e7ec}
        .history-pipeline-title,
        .history-pipeline-meta{overflow-wrap:anywhere;word-break:break-word}
        .history-pipeline-top{min-width:0}
        .history-pipeline-title{min-width:0;flex:1}
        .history-role-badge{flex:0 0 auto}
        .history-switcher{max-width:100%;overflow-x:auto}
        @media(max-width:700px){
            .history-view-head{align-items:flex-start;flex-wrap:wrap}
            .history-view-count{flex:0 0 auto}
            .history-pipeline-top{gap:8px}
            .history-pipeline-card{padding:10px}
            .history-pipeline-meta{font-size:8.5px;line-height:1.55}
            .history-role-badge{font-size:7.5px;min-height:20px;padding:3px 6px}
        }
        @media(max-width:460px){
            .history-pipeline-top{flex-direction:column;align-items:flex-start}
            .history-role-badge{align-self:flex-start}
            .history-view-head{display:block}
            .history-view-count{margin-top:7px}
        }

</style>

<div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'overview' }">
    <div class="page-head">
        <div>
            <h1>{{ $task->display_task_name ?? $task->task_name }}</h1>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('bd.tasks.index') }}" class="btn btn-secondary">Back to My Tasks</a>
            @if(!in_array($task->status, ['waiting_confirmation','rework','completed'], true))
                <a href="{{ route('bd.tasks.edit',$task) }}" class="btn btn-primary">Edit Task</a>
            @endif
        </div>
    </div>


    <div class="bd-detail-tabs">
        <button class="bd-detail-tab" :class="{active:tab==='overview'}" @click="tab='overview'">Overview</button>
        
        <button class="bd-detail-tab" :class="{active:tab==='comments'}" @click="tab='comments'">Comments</button>
        @if($splitRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='split-details'}" @click="tab='split-details'">Split Details</button>@endif
        @if($swapRequests->isNotEmpty())<button class="bd-detail-tab" :class="{active:tab==='swap-details'}" @click="tab='swap-details'">Swap Details</button>@endif
        <button class="bd-detail-tab" :class="{active:tab==='history'}" @click="tab='history'">History</button>
        @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
            <button class="bd-detail-tab" :class="{active:tab==='eod'}" @click="tab='eod'">Progress Updates</button>
        @endif
        @if($task->status === 'completed' && $taskRating)
            <button class="bd-detail-tab" :class="{active:tab==='ratings'}" @click="tab='ratings'">Ratings</button>
        @endif
    </div>

    <section class="bd-tab-panel" x-show="tab==='overview'">
        <div class="detail-grid">
            <div>
                <details class="collapse-panel"><summary>Task Information</summary><div class="collapse-body"><div class="info-grid">
                    @foreach(['Client / Agency'=>ucfirst($task->party_type).' · '.$task->party_name,'Contact Person'=>$task->contact_person,'Mobile Number'=>$task->mobile_number,'Vertical'=>ucwords(str_replace('_',' ',$task->vertical)),'Task Nature'=>ucwords(str_replace('_',' ',$task->task_nature)),'Priority'=>ucfirst($task->priority),'Designer'=>$task->designer?->name ?? '—','Total Creatives'=>$task->total_creatives,'Due Date'=>$task->due_at?->format('d M Y'),'Assigned At'=>$task->assigned_at?->format('d M Y')] as $key=>$value)
                        <div class="info-item"><span>{{ $key }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </div></div></details>
                <details class="collapse-panel"><summary>Task Requirements</summary><div class="collapse-body"><div class="requirement-list">
                    @forelse(($task->requirements ?? []) as $key=>$value)
                        @php
                            $isRequirementFile = (is_string($value) && str_contains($value,'/') && !filter_var($value,FILTER_VALIDATE_URL))
                                || (is_array($value) && collect($value)->contains(fn($item) => is_string($item) && str_contains($item,'/') && !filter_var($item,FILTER_VALIDATE_URL)));
                        @endphp
                        @continue(str_starts_with((string)$key,'_') || $isRequirementFile)
                        <div class="requirement-row"><div class="requirement-key">{{ ucwords(str_replace('_',' ',$key)) }}</div><div>@if($key === 'board_details' && is_array($value)) @include('partials.board-details-table',['rows'=>$value]) @else {{ is_array($value) ? json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $value }} @endif</div></div>
                    @empty<div class="empty-state">No requirement data available.</div>@endforelse
                </div></div></details>
                <details class="collapse-panel"><summary><span class="collapse-summary-title">Attachments <span class="bd-tab-count">{{ $requirementAttachmentCount }}</span></span></summary><div class="collapse-body">
                    @forelse($requirementAttachmentGroups as $group)
                        <div class="bd-attachment-group">
                            <div class="bd-attachment-title">{{ $group['label'] }}</div>
                            @foreach($group['files'] as $file)
                                <div class="bd-file">
                                    <div class="bd-file-main">
                                        <div class="bd-file-name" title="{{ $file['name'] }}">{{ $file['name'] }}</div>
                                    </div>
                                    <div class="bd-file-actions">
                                        <a class="bd-file-btn bd-file-open" target="_blank" href="{{ $file['url'] }}">Open</a>
                                        <a class="bd-file-btn bd-file-download" href="{{ $file['url'] }}" download>Download</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="empty-state">No task creation/edit attachments.</div>
                    @endforelse
                </div></details>

                @php
                    $showClarification = $task->status === 'need_clarification' || $clarificationComments->isNotEmpty();
                @endphp
                @if($showClarification)
                <details class="collapse-panel" open>
                    <summary><span class="collapse-summary-title">Clarification <span class="bd-tab-count">{{ $clarificationComments->count() }}</span></span></summary>
                    <div class="collapse-body">
                        <div class="bd-comment-feed">
                            @forelse($clarificationComments as $comment)
                                @php
                                    $clarificationName = $comment->user?->name ?? 'User';
                                    $clarificationInitial = strtoupper(mb_substr($clarificationName, 0, 1));
                                @endphp
                                <article class="bd-comment role-{{ $comment->user?->role ?? 'default' }}">
                                    <div class="bd-comment-head">
                                        <div class="bd-comment-person">
                                            <div class="bd-comment-avatar">{{ $clarificationInitial }}</div>
                                            <div>
                                                <div class="bd-comment-name">{{ $clarificationName }}</div>
                                                <div class="bd-comment-date">{{ $comment->created_at?->format('d M Y \\• g:i A') }}</div>
                                            </div>
                                        </div>
                                        <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$comment->user?->role ?? 'user')) }}</span>
                                    </div>

                                    <div class="bd-comment-message">{{ $comment->comment }}</div>

                                    @if($comment->attachments->isNotEmpty())
                                        <div class="bd-comment-files">
                                            @foreach($comment->attachments as $attachment)
                                                <div class="bd-comment-file">
                                                    <div class="bd-comment-file-primary">
                                                        <span class="bd-comment-file-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span>
                                                        <a target="_blank" class="bd-comment-open" href="{{ $attachment->url }}">Open</a>
                                                    </div>
                                                    <a class="bd-comment-download" href="{{ $attachment->url }}" download>Download</a>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="empty-state">No clarification messages yet.</div>
                            @endforelse
                        </div>

                        @if($task->status === 'need_clarification')
                            <form
                                method="POST"
                                action="{{ route('bd.tasks.comments.store', $task) }}"
                                enctype="multipart/form-data"
                                style="margin-top:14px;padding-top:14px;border-top:1px solid #eef0f3"
                                onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerText='Sending...';"
                            >
                                @csrf
                                <input type="hidden" name="redirect_tab" value="overview">
                                <label class="label">Reply to Designer</label>
                                <textarea
                                    class="premium-input"
                                    name="comment"
                                    rows="4"
                                    maxlength="10000"
                                    placeholder="Reply to Designer..."
                                    required
                                >{{ old('comment') }}</textarea>

                                @error('comment')
                                    <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                                @enderror

                                <div style="margin-top:11px">
                                    <label class="label">Attach File (Optional)</label>
                                    <input class="premium-input" type="file" name="attachments[]" multiple data-accumulate-files>
                                    <div style="font-size:8px;color:#98a2b3;margin-top:5px">Up to 10 files · Maximum 100 MB each</div>
                                    @error('attachments.*')
                                        <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px">Send Reply</button>
                            </form>
                        @endif
                    </div>
                </details>
                @endif
            </div>
            <aside><section class="panel"><div class="panel-header"><div class="panel-title">Current Status</div></div><div class="panel-body"><span class="badge badge-red">{{ $statuses[$task->status] ?? ucwords(str_replace('_',' ',$task->status)) }}</span>@if(in_array($task->status,['in_progress','waiting_confirmation','rework'],true))<div class="progress-card progress-{{ $progressColorKey }}"><div class="progress-head"><span class="progress-title">Creative Progress</span><span class="progress-value">{{ $eodCompletedTotal }} / {{ $task->total_creatives }} · {{ $progressPercentage }}%</span></div><div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div></div>@endif<div class="activity-item" style="margin-top:12px"><strong>Assigned Designer</strong><p>{{ $task->designer?->name ?? '—' }}</p></div><div class="activity-item" style="margin-top:8px"><strong>Due Date</strong><p>{{ $task->due_at?->format('d M Y') }}</p></div>@if(!in_array($task->status, ['waiting_confirmation','rework','completed'], true))<a href="{{ route('bd.tasks.edit',$task) }}" class="btn btn-primary" style="width:100%;margin-top:12px">Edit Task</a>@endif</div></section></aside>
        </div>
    </section>

    <section class="bd-tab-panel" x-show="tab==='comments'" x-cloak>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Comments</div>
                    <div style="font-size:9px;color:#667085;margin-top:4px">Shared communication between BD and Designer.</div>
                </div>
                <span class="bd-tab-count">{{ $comments->count() }}</span>
            </div>

            <div class="panel-body">
                <div class="bd-comments-shell">
                    <div class="bd-comment-feed">
                        @forelse($comments as $comment)
                            @php
                                $bdCommentName = $comment->user?->name ?? 'User';
                                $bdCommentInitial = strtoupper(mb_substr($bdCommentName, 0, 1));
                            @endphp
                            <article class="bd-comment role-{{ $comment->user?->role ?? 'default' }}">
                                <div class="bd-comment-head">
                                    <div class="bd-comment-person">
                                        <div class="bd-comment-avatar">{{ $bdCommentInitial }}</div>
                                        <div>
                                            <div class="bd-comment-name">{{ $bdCommentName }}</div>
                                            <div class="bd-comment-date">{{ $comment->created_at?->format('d M Y · h:i A') }}</div>
                                        </div>
                                    </div>
                                    <span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$comment->user?->role ?? 'user')) }}</span>
                                </div>

                                <div class="bd-comment-message">{{ $comment->comment }}</div>

                                @if($comment->attachments->isNotEmpty())
                                    <div class="bd-comment-files">
                                        @foreach($comment->attachments as $attachment)
                                            <div class="bd-comment-file">
                                                <div class="bd-comment-file-primary">
                                                    <span class="bd-comment-file-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span>
                                                    <a target="_blank" class="bd-comment-open" href="{{ $attachment->url }}">Open</a>
                                                </div>
                                                <a class="bd-comment-download" href="{{ $attachment->url }}" download>Download</a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="empty-state">No comments yet. Start the task conversation from the form on the right.</div>
                        @endforelse
                    </div>

                    <div class="bd-comment-composer">
                        <div class="bd-comment-composer-title">Add Comment</div>
                        <div class="bd-comment-composer-subtitle">Share a clear update, clarification or feedback with the Designer.</div>

                        <form
                            method="POST"
                            action="{{ route('bd.tasks.comments.store', $task) }}"
                            enctype="multipart/form-data"
                            onsubmit="const b=this.querySelector('button[type=submit]');b.disabled=true;b.innerText='Posting...';"
                        >
                            @csrf
                            <label class="label">Comment</label>
                            <textarea
                                class="premium-input"
                                name="comment"
                                rows="5"
                                maxlength="10000"
                                placeholder="Write your comment..."
                                required
                            >{{ old('comment') }}</textarea>

                            @error('comment')
                                <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                            @enderror

                            <div style="margin-top:11px">
                                <label class="label">Attachments</label>
                                <input class="premium-input" type="file" name="attachments[]" multiple data-accumulate-files>
                                <div style="font-size:8px;color:#98a2b3;margin-top:5px">Up to 10 files · Maximum 100 MB each</div>
                                @error('attachments.*')
                                    <div style="font-size:9px;color:#b42318;margin-top:5px">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px">Post Comment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($splitRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='split-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Split Details</div></div><div class="panel-body">
        @foreach($splitRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Split {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y · h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested At</strong>{{ $request->created_at?->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded At</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? 'Pending Response' : optional($request->admin_action_at ?: $request->designer_head_action_at)->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded By</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? '—' : (($request->adminActor ?: $request->designerHeadActor)?->name ?? '—') }}</div><div class="bd-request-field"><strong>Requested Split</strong>{{ data_get($request,'split_count') ?? data_get($request,'split_details.requested_count') ?? '—' }}</div><div class="bd-request-field"><strong>Approved Split</strong>{{ data_get($request,'approved_split_count') ?? data_get($request,'split_details.approved_count') ?? '—' }}</div><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    @if($swapRequests->isNotEmpty())<section class="bd-tab-panel" x-show="tab==='swap-details'" x-cloak><div class="panel"><div class="panel-header"><div class="panel-title">Swap Details</div></div><div class="panel-body">
        @foreach($swapRequests as $request)<div class="bd-request-card"><div class="bd-request-head"><div><div class="bd-request-title">Swap {{ ucwords(str_replace('_',' ',$request->overall_status)) }}</div><div class="bd-request-meta">Requested by {{ $request->requester?->name ?? 'Designer' }} · {{ $request->created_at?->format('d M Y · h:i A') }}</div></div><span class="badge badge-dark">{{ ucwords(str_replace('_',' ',$request->overall_status)) }}</span></div><div class="bd-request-grid"><div class="bd-request-field"><strong>Requested At</strong>{{ $request->created_at?->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded At</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? 'Pending Response' : optional($request->admin_action_at ?: $request->designer_head_action_at)->format('d M Y · h:i A') }}</div><div class="bd-request-field"><strong>Responded By</strong>{{ in_array($request->overall_status,['pending_approval','pending_designer_head','pending_admin'],true) ? '—' : (($request->adminActor ?: $request->designerHeadActor)?->name ?? '—') }}</div><div class="bd-request-field"><strong>Preferred Designer</strong>{{ $request->targetDesigner?->name ?? '—' }}</div><div class="bd-request-field"><strong>Approved Designer</strong>{{ $request->approvedDesigner?->name ?? '—' }}</div>@if($request->decision_reason)<div class="bd-request-field" style="grid-column:1/-1"><strong>Decision Reason</strong>{{ $request->decision_reason }}</div>@endif</div></div>@endforeach
    </div></div></section>@endif

    <section class="bd-tab-panel" x-show="tab==='history'" x-cloak x-data="{ historyView: 'pipeline' }">
        <div class="history-switcher">
            <button
                type="button"
                class="history-switch-btn"
                :class="{ 'active': historyView === 'pipeline' }"
                @click="historyView='pipeline'"
            >
                Pipeline History
            </button>

            <button
                type="button"
                class="history-switch-btn"
                :class="{ 'active': historyView === 'task' }"
                @click="historyView='task'"
            >
                Edit History
            </button>
        </div>

        <div x-show="historyView === 'pipeline'">
            <div class="history-view-card">
                <div class="history-view-head">
                    <div>
                        <div class="history-view-title">Pipeline History</div>
                        <div class="history-view-subtitle">Complete workflow, comment and status activity.</div>
                    </div>
                    <span class="history-view-count">{{ $pipelineEvents->count() }} Events</span>
                </div>

                <div class="history-view-body">
                    @forelse($pipelineEvents as $event)
                        @php
                            $historyRole = $event['role'] ?? 'default';
                        @endphp

                        <div class="history-pipeline-card role-{{ $historyRole }}">
                            <div class="history-pipeline-top">
                                <div class="history-pipeline-title">{{ $event['title'] }}</div>
                                <span class="history-role-badge role-{{ $historyRole }}">
                                    {{ $historyRole === 'default' ? 'System' : ucwords(str_replace('_', ' ', $historyRole)) }}
                                </span>
                            </div>

                            <div class="history-pipeline-meta">
                                {{ str_ireplace('reconciled', 'moved to', $event['description']) }}
                                · {{ $event['created_at']?->format('d M Y · h:i A') }}
                            </div>
                        </div>
                    @empty
                        <div class="history-nothing">No pipeline events have been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="historyView === 'task'" x-cloak>
            <div class="history-view-card">
                <div class="history-view-head">
                    <div>
                        <div class="history-view-title">Edit History</div>
                        <div class="history-view-subtitle">Every recorded change to task information.</div>
                    </div>
                    <span class="history-view-count">{{ $editHistory->count() }} Updates</span>
                </div>

                <div class="history-view-body">
                    @forelse($editHistory as $changes)
                        @php
                            $firstChange = $changes->first();
                        @endphp

                        <div class="history-task-batch">
                            <div class="history-task-head">
                                <div class="history-task-editor">
                                    {{ $firstChange->editor?->name ?? 'User' }}
                                </div>
                                <div class="history-task-time">
                                    {{ $firstChange->created_at?->format('d M Y') }}
                                </div>
                            </div>

                            @foreach($changes as $change)
                                <div class="history-task-row">
                                    <div class="history-task-field">{{ $change->field_name }}</div>

                                    <div class="history-task-values">
                                        <div class="history-task-old">
                                            <del>{{ $change->old_value ?: '—' }}</del>
                                        </div>
                                        <div class="history-task-arrow">→</div>
                                        <div class="history-task-new">
                                            {{ $change->new_value ?: '—' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="history-nothing">No task edits have been recorded yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
    <section class="bd-tab-panel" x-show="tab==='eod'" x-cloak>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Progress Updates</div>
                    <div style="font-size:10px;color:#667085;margin-top:3px">Designer Progress Updates records and Rework uploads.</div>
                </div>
            </div>
            <div class="panel-body">

                <div class="bd-eod-summary">
                    <div class="bd-eod-card"><span>Total Creatives</span><strong>{{ $task->total_creatives }}</strong></div>
                    <div class="bd-eod-card"><span>Completed</span><strong>{{ $eodCompletedTotal }}</strong></div>
                    <div class="bd-eod-card"><span>Remaining</span><strong>{{ $eodRemaining }}</strong></div>
                    <div class="bd-eod-card rework-stat"><span>Rework Count</span><strong>{{ $reworkCount }}</strong></div>
                </div>

                @forelse($eodRecords as $record)
                    @php
                        $isReworkRecord = ($record->update_type ?? 'progress') === 'rework';
                    @endphp
                    <div class="bd-eod-row {{ $isReworkRecord ? 'is-rework' : 'is-progress' }}">
                        <div>
                            <span class="bd-eod-type-badge {{ $isReworkRecord ? 'rework' : 'progress' }}">
                                {{ $isReworkRecord ? 'Rework Submission' : 'Progress Submission' }}
                            </span>
                            <strong>Submitted By</strong>
                            {{ $record->designer?->name ?? '—' }}<br>
                            {{ $record->submitted_at?->format('d M Y · h:i A') }}
                            @if($record->attachment_url)
                                <br><a target="_blank" href="{{ $record->attachment_url }}">{{ $record->attachment_original_name ?? 'Download ZIP' }}</a>
                            @endif
                        </div>
                        <div>
                            <strong>{{ $isReworkRecord ? 'Reworked Creatives' : 'Progress Added' }}</strong>
                            {{ $record->completed_count }}
                            @if($isReworkRecord)
                                <div style="margin-top:4px;font-size:8px;font-weight:900;color:#9a6700">Rework #{{ $record->rework_count_snapshot }}</div>
                            @endif
                        </div>
                        <div><strong>Total Creatives</strong>{{ $record->total_creatives_snapshot }}</div>
                        <div><strong>Total Completed</strong>{{ $record->cumulative_completed }}</div>
                        <div><strong>Remaining</strong>{{ $record->remaining_creatives }}</div>
                    </div>
                @empty
                    <div class="empty-state">No Progress Updates records have been submitted yet.</div>
                @endforelse

                @if($task->status === 'waiting_confirmation')
                    <div class="bd-review-box" x-data="{
                        showRating: {{ ($errors->has('designer_attitude') || $errors->has('design_satisfaction') || $errors->has('rework_iteration') || $errors->has('meeting_deadline') || $errors->has('client_satisfaction') || $errors->has('rating_comment')) ? 'true' : 'false' }},
                        designerAttitude: {{ (float) old('designer_attitude', 0) }},
                        designSatisfaction: {{ (float) old('design_satisfaction', 0) }},
                        reworkIteration: {{ (float) old('rework_iteration', 0) }},
                        meetingDeadline: {{ (float) old('meeting_deadline', 0) }},
                        clientSatisfaction: {{ (float) old('client_satisfaction', 0) }},
                        overall(){
                            const values=[this.designerAttitude,this.designSatisfaction,this.reworkIteration,this.meetingDeadline,this.clientSatisfaction].map(Number);
                            if(values.some(v => !v)) return 0;
                            return Math.round((values.reduce((a,b)=>a+b,0)/5)*100)/100;
                        }
                    }">
                        <h3>BD Confirmation</h3>
                        <p>Choose Rework if corrections are required, or complete the task after submitting the Designer rating.</p>

                        <form method="POST" action="{{ route('bd.tasks.rework', $task) }}">
                            @csrf
                            <div class="bd-review-grid">
                                <div>
                                    <label class="label">Number of Creatives</label>
                                    <input class="premium-input" type="number" name="number_of_creatives" min="1" max="{{ $task->total_creatives }}" value="{{ old('number_of_creatives') }}" placeholder="e.g. 2">
                                    @error('number_of_creatives')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div>
                                    <label class="label">Comments</label>
                                    <textarea class="premium-input" name="comment" rows="3" maxlength="10000" placeholder="Describe the corrections required...">{{ old('comment') }}</textarea>
                                    @error('comment')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="bd-review-actions">
                                <button type="submit" class="btn bd-danger-btn">Rework</button>
                                <button type="button" class="btn bd-complete-btn" @click="showRating = !showRating">Completed</button>
                            </div>
                        </form>

                        <div class="bd-rating-panel" x-show="showRating" x-cloak>
                            <form method="POST" action="{{ route('bd.tasks.complete-with-rating', $task) }}">
                                @csrf

                                @php
                                    $ratingFields = [
                                        ['key' => 'designer_attitude', 'label' => 'Designer Attitude', 'model' => 'designerAttitude'],
                                        ['key' => 'design_satisfaction', 'label' => 'Design Satisfaction', 'model' => 'designSatisfaction'],
                                        ['key' => 'rework_iteration', 'label' => 'Rework Iteration', 'model' => 'reworkIteration'],
                                        ['key' => 'meeting_deadline', 'label' => 'Meeting Deadline', 'model' => 'meetingDeadline'],
                                        ['key' => 'client_satisfaction', 'label' => 'Client Satisfaction', 'model' => 'clientSatisfaction'],
                                    ];
                                @endphp

                                @foreach($ratingFields as $ratingField)
                                    <div class="rating-row">
                                        <div class="rating-label">{{ $ratingField['label'] }}</div>
                                        <div class="star-picker">
                                            <input type="hidden" name="{{ $ratingField['key'] }}" x-model.number="{{ $ratingField['model'] }}">
                                            @for($star = 1; $star <= 5; $star++)
                                                <span class="star-unit">
                                                    <span class="star-empty">★</span>
                                                    <span class="star-fill" :style="`width:${Math.max(0, Math.min(1, {{ $ratingField['model'] }} - {{ $star - 1 }})) * 100}%`">★</span>
                                                    <button type="button" class="star-half-hit left" aria-label="{{ $star - 0.5 }} stars" @click="{{ $ratingField['model'] }}={{ $star - 0.5 }}"></button>
                                                    <button type="button" class="star-half-hit right" aria-label="{{ $star }} stars" @click="{{ $ratingField['model'] }}={{ $star }}"></button>
                                                </span>
                                            @endfor
                                        </div>
                                        <div class="rating-value" x-text="{{ $ratingField['model'] }} ? Number({{ $ratingField['model'] }}).toFixed(1) : '—'"></div>
                                    </div>
                                    @error($ratingField['key'])<div class="error">{{ $message }}</div>@enderror
                                @endforeach

                                <div class="overall-rating-card">
                                    <span>Overall Rating</span>
                                    <strong><span x-text="overall() ? overall().toFixed(2) : '0.00'"></span> / 5</strong>
                                </div>

                                <div style="margin-top:12px">
                                    <label class="label">Comments <span x-show="overall() > 0 && overall() < 3" style="color:#dc2626">(Required below 3 stars)</span></label>
                                    <textarea class="premium-input" name="rating_comment" rows="4" maxlength="10000" :required="overall() > 0 && overall() < 3" placeholder="Add rating comments...">{{ old('rating_comment') }}</textarea>
                                    @error('rating_comment')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="bd-review-actions">
                                    <button type="submit" class="btn bd-complete-btn" :disabled="overall() === 0">Submit Rating & Complete Task</button>
                                    <button type="button" class="btn btn-secondary" @click="showRating=false">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    @if($task->status === 'completed' && $taskRating)
    <section class="bd-tab-panel" x-show="tab==='ratings'" x-cloak>
        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Ratings</div>
                    <div style="font-size:9px;color:#667085;margin-top:3px">Final BD rating submitted when the task was completed.</div>
                </div>
            </div>

            <div class="panel-body">
                @php
                    $overallRatingValue = max(0, min(5, (float) $taskRating->overall_rating));
                @endphp

                <div class="rating-summary-shell">
                    <div class="rating-summary-top">
                        <div>
                            <div class="rating-summary-kicker">Overall Rating</div>
                            <div class="rating-summary-stars" aria-label="{{ number_format($overallRatingValue, 2) }} out of 5 stars">
                                @for($starIndex = 1; $starIndex <= 5; $starIndex++)
                                    @php
                                        $starFill = $overallRatingValue >= $starIndex
                                            ? 100
                                            : ($overallRatingValue >= ($starIndex - 0.5) ? 50 : 0);
                                    @endphp
                                    <span class="rating-static-star" style="--star-fill:{{ $starFill }}%;" aria-hidden="true">★</span>
                                @endfor
                            </div>
                        </div>

                        <div class="rating-summary-score">
                            {{ number_format($overallRatingValue, 2) }} / 5
                        </div>
                    </div>

                    <div class="rating-compact-grid">
                        @foreach([
                            'Designer Attitude' => $taskRating->designer_attitude,
                            'Design Satisfaction' => $taskRating->design_satisfaction,
                            'Rework Iteration' => $taskRating->rework_iteration,
                            'Meeting Deadline' => $taskRating->meeting_deadline,
                            'Client Satisfaction' => $taskRating->client_satisfaction,
                            'Overall Rating' => $taskRating->overall_rating,
                        ] as $label => $value)
                            @php
                                $ratingValue = max(0, min(5, (float) $value));
                            @endphp

                            <div class="rating-compact-item {{ $label === 'Overall Rating' ? 'rating-overall-item' : '' }}">
                                <div class="rating-compact-head">
                                    <span class="rating-compact-label">{{ $label }}</span>
                                    <span class="rating-compact-score">
                                        {{ number_format($ratingValue, $label === 'Overall Rating' ? 2 : 1) }} / 5
                                    </span>
                                </div>

                                <div class="rating-compact-stars" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                    @for($starIndex = 1; $starIndex <= 5; $starIndex++)
                                        @php
                                            $starFill = $ratingValue >= $starIndex
                                                ? 100
                                                : ($ratingValue >= ($starIndex - 0.5) ? 50 : 0);
                                        @endphp

                                        <span
                                            class="rating-static-star"
                                            style="--star-fill:{{ $starFill }}%;"
                                            aria-hidden="true"
                                        >★</span>
                                    @endfor
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="rating-meta-row">
                        <div class="rating-comment-compact">
                            <strong>Comments</strong><br>
                            {{ $taskRating->comment ?: 'No comments added.' }}
                        </div>

                        <div class="rating-submitted-compact">
                            Submitted by <strong>{{ $taskRating->submitter?->name ?? 'BD' }}</strong><br>
                            <span>{{ $taskRating->created_at?->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif
</div>
@endsection