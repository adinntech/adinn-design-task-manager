<div
    x-data="{
        tab: 'overview',
        toast: '',
        attachmentPreviewOpen: false,
        attachmentPreviewUrl: '',
        attachmentPreviewName: '',
        attachmentDownloadUrl: '',
        openAttachment(url, name, downloadUrl) {
            this.attachmentPreviewUrl = url;
            this.attachmentPreviewName = name || 'Attachment';
            this.attachmentDownloadUrl = downloadUrl || '';
            this.attachmentPreviewOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeAttachment() {
            this.attachmentPreviewOpen = false;
            this.attachmentPreviewUrl = '';
            this.attachmentPreviewName = '';
            this.attachmentDownloadUrl = '';
            document.body.style.overflow = '';
        }
    }"
    x-on:task-status-changed.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:comment-added.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:eod-updated.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
    x-on:request-created.window="toast = $event.detail.message; setTimeout(() => toast = '', 2600)"
>
    <style>
        .task-operation-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 10px;border-radius:999px;font-size:9px;font-weight:950;letter-spacing:.055em;text-transform:uppercase;border:1px solid transparent;vertical-align:middle;white-space:nowrap}.task-operation-pill-split{color:#6938ef;background:linear-gradient(135deg,#f4f0ff,#ede9fe);border-color:#d9d6fe}.task-operation-pill-swap{color:#175cd3;background:linear-gradient(135deg,#eff8ff,#e6f1ff);border-color:#b2ddff}.task-operation-pill-pending{color:#9a6700;background:#fff8e6;border-color:#f5d680}.task-operation-pill-declined{color:#b42318;background:#fff1f0;border-color:#f7b4ae}.task-operation-pill-approved{box-shadow:0 2px 8px rgba(16,24,40,.05)}
        .detail-tabs{display:flex;gap:5px;padding:5px;background:#f5f6f8;border-radius:11px;width:max-content;margin-bottom:14px;max-width:100%;overflow:auto}
        .detail-tab{border:0;background:transparent;border-radius:8px;padding:8px 12px;font-size:10px;font-weight:850;color:#697386;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center}
        .detail-tab.active{background:#fff;color:#e30613;box-shadow:0 3px 10px rgba(16,24,40,.06)}
        .comment-box{border:1px solid #e7e9ef;border-radius:13px;padding:14px;background:#fff}
.comment-shell{display:grid;grid-template-columns:minmax(0,1fr);gap:14px}.comment-compose{border:1px solid #e4e7ec;border-radius:16px;background:linear-gradient(180deg,#ffffff 0%,#fcfcfd 100%);box-shadow:0 6px 18px rgba(16,24,40,.045);overflow:hidden}.comment-compose-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid #eef0f3;background:#fff}.comment-compose-title{font-size:12px;font-weight:900;color:#101828;letter-spacing:-.015em}.comment-compose-stage{display:inline-flex;align-items:center;padding:5px 8px;border-radius:999px;background:#fff1f2;color:#b4232f;border:1px solid #fecdd3;font-size:9px;font-weight:900}.comment-compose-body{padding:14px}.comment-textarea{min-height:110px!important;border-radius:12px!important;border:1px solid #d0d5dd!important;background:#fff!important;font-size:12px!important;line-height:1.6!important;color:#101828!important;padding:12px 13px!important;resize:vertical!important}.comment-textarea:focus{outline:none!important;border-color:#f04438!important;box-shadow:0 0 0 3px rgba(240,68,56,.09)!important}.comment-upload-row{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:11px;padding-top:11px;border-top:1px solid #f0f1f3}.comment-upload-box{flex:1;min-width:0}.comment-upload-box input[type=file]{width:100%;font-size:10px;color:#475467}.comment-upload-note{font-size:9px;color:#98a2b3;margin-top:5px}.comment-submit{min-width:116px;min-height:38px;border-radius:10px!important;font-size:10px!important;font-weight:900!important}.comment-list-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:2px 0 10px}.comment-list-title{font-size:11px;font-weight:900;color:#101828}.comment-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:9px;font-weight:900}.comment-feed{display:grid;gap:10px}.comment-item{position:relative;border:1px solid #e4e7ec!important;border-radius:14px!important;background:#fff!important;padding:0!important;overflow:hidden;box-shadow:0 3px 10px rgba(16,24,40,.035)}.comment-item:before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:#d0d5dd}.comment-item.role-bd:before{background:#f04438}.comment-item.role-designer:before{background:#175cd3}.comment-item.role-designer_head:before{background:#7f56d9}.comment-item.role-admin:before{background:#101828}.comment-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:11px 13px 9px 15px;border-bottom:1px solid #f2f4f7;background:#fcfcfd}.comment-user-wrap{display:flex;gap:9px;align-items:center;min-width:0}.comment-avatar{width:30px;height:30px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f2f4f7;color:#344054;font-size:10px;font-weight:900;flex:0 0 30px}.comment-item.role-bd .comment-avatar{background:#fff1f0;color:#b42318}.comment-item.role-designer .comment-avatar{background:#eff8ff;color:#175cd3}.comment-item.role-designer_head .comment-avatar{background:#f4f3ff;color:#6941c6}.comment-user-meta{min-width:0}.comment-user-name{font-size:11px;font-weight:900;color:#101828;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.comment-meta-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:2px}.comment-date{font-size:9px;color:#98a2b3;font-weight:700}.comment-status{font-size:8px!important;padding:4px 7px!important;white-space:nowrap}.comment-card-body{padding:12px 13px 13px 15px}.comment-message{margin:0;font-size:11px;line-height:1.65;font-weight:450;color:#344054;white-space:pre-wrap;letter-spacing:-.005em;overflow-wrap:anywhere}.comment-attachments{display:grid;gap:7px;margin-top:11px;padding-top:10px;border-top:1px solid #f2f4f7}.comment-attachment{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 9px;border:1px solid #eaecf0;border-radius:10px;background:#fcfcfd}.comment-attachment-main{display:flex;align-items:center;gap:8px;min-width:0}.comment-file-icon{width:26px;height:26px;border-radius:8px;background:#fff1f0;color:#b42318;display:flex;align-items:center;justify-content:center;font-size:11px;flex:0 0 26px}.comment-file-name{font-size:9px;font-weight:800;color:#344054;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.comment-file-actions{display:flex;align-items:center;gap:5px;flex:0 0 auto}.comment-file-btn{display:inline-flex;align-items:center;justify-content:center;padding:5px 7px;border-radius:7px;border:1px solid #d0d5dd;background:#fff;color:#344054;font-size:8px;font-weight:900;text-decoration:none}.comment-file-btn:hover{background:#f9fafb}.comment-empty{padding:34px 16px;border:1px dashed #d0d5dd;border-radius:14px;text-align:center;background:#fcfcfd}.comment-empty-icon{width:36px;height:36px;border-radius:12px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;background:#f2f4f7;color:#667085;font-size:16px}.comment-empty strong{display:block;font-size:11px;color:#344054}.comment-empty span{display:block;font-size:9px;color:#98a2b3;margin-top:4px}@media(max-width:720px){.comment-upload-row{align-items:stretch;flex-direction:column}.comment-submit{width:100%}.comment-card-head{flex-direction:column}.comment-card-head>.comment-status{align-self:flex-start}.comment-attachment{align-items:flex-start;flex-direction:column}.comment-file-actions{width:100%}.comment-file-btn{flex:1}}

        .comment-textarea{width:100%;min-height:115px;border:1px solid #dfe2e8;border-radius:10px;padding:11px;font:inherit;font-size:11px;resize:vertical;outline:none}
        .comment-textarea:focus{border-color:#e30613;box-shadow:0 0 0 3px rgba(227,6,19,.08)}
        .comment-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
        .comment-item{padding:12px;border:1px solid #e7e9ef;border-left:4px solid #d0d5dd;border-radius:11px;background:#fff;margin-top:8px}
        .comment-item.role-designer{border-left-color:#2563eb}.comment-item.role-bd{border-left-color:#16a34a}.comment-item.role-designer_head{border-left-color:#7c3aed}.comment-item.role-admin{border-left-color:#e30613}
        .role-pill{display:inline-flex;align-items:center;border-radius:999px;padding:3px 7px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .role-pill.role-designer{background:#eff6ff;color:#1d4ed8}.role-pill.role-bd{background:#ecfdf3;color:#15803d}.role-pill.role-designer_head{background:#f5f3ff;color:#6d28d9}.role-pill.role-admin{background:#fff1f2;color:#e30613}.role-pill.role-default{background:#f2f4f7;color:#667085}
        .history-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border:1px solid #ebe7ff;background:linear-gradient(180deg,#fbfaff 0%,#f7f5ff 100%);border-radius:12px;margin-bottom:12px}.history-header-title{font-size:13px;font-weight:900;color:#4f2db8;letter-spacing:-.01em}.history-count{display:inline-flex;align-items:center;justify-content:center;min-width:62px;padding:5px 9px;border-radius:999px;background:#efe9ff;color:#6d28d9;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.03em}.history-list{display:flex;flex-direction:column;gap:9px}.history-item{border:1px solid #e7e9ef;border-left:4px solid #d0d5dd;border-radius:12px;padding:12px 14px;background:#fff;box-shadow:0 2px 8px rgba(16,24,40,.025)}.history-item.role-designer{border-left-color:#2563eb}.history-item.role-bd{border-left-color:#e30613}.history-item.role-designer_head{border-left-color:#7c3aed}.history-item.role-admin{border-left-color:#111827}.history-event-title{font-size:12px;font-weight:900;color:#17191f;line-height:1.35}.history-meta{margin-top:5px;font-size:10px;color:#7a8494;line-height:1.55}.history-description{color:#4f5b6b;font-weight:600}.history-time{color:#98a2b3}.history-item:hover{border-color:#dfe3ea;box-shadow:0 5px 16px rgba(16,24,40,.045)}
        .special-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.special-detail-card{border:1px solid #e7e9ef;border-radius:12px;padding:12px;background:#fff}.special-detail-card span{display:block;font-size:9px;text-transform:uppercase;color:#7c8492;font-weight:800;letter-spacing:.05em}.special-detail-card strong{display:block;margin-top:5px;font-size:12px;color:#16181d}
        .eod-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .eod-stat{border:1px solid #e7e9ef;border-radius:12px;padding:13px;background:#fff}
        .eod-stat span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#7c8492;font-weight:850}
        .eod-stat strong{display:block;margin-top:5px;font-size:18px;color:#111827}
        .eod-entry-form{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;padding:14px;border:1px solid #e7e9ef;border-radius:12px;background:#f9fafb;margin-bottom:14px}
        .eod-field{min-width:220px;flex:1}
        .eod-history{display:flex;flex-direction:column;gap:9px}
        .eod-record{display:grid;grid-template-columns:1.1fr repeat(4,minmax(90px,.7fr));gap:10px;align-items:center;padding:12px 14px;border:1px solid #e7e9ef;border-radius:12px;background:#fff}
        .eod-record-main strong{display:block;font-size:11px;color:#111827}
        .eod-record-main span{display:block;margin-top:3px;font-size:9px;color:#7c8492}
        .eod-record-cell span{display:block;font-size:8px;text-transform:uppercase;color:#98a2b3;font-weight:800;letter-spacing:.04em}
        .eod-record-cell strong{display:block;margin-top:3px;font-size:11px;color:#344054}
        .eod-zero{color:#15803d!important}.eod-stat.rework-stat{background:#fff9eb;border-color:#f5d16a}.eod-stat.rework-stat span{color:#9a6700}.eod-stat.rework-stat strong{color:#7a5200}.eod-record.is-rework{border-color:#f2ce68;background:linear-gradient(180deg,#fffdf7,#fff9e8);box-shadow:inset 4px 0 0 #f5b301}.eod-record.is-progress{border-left:4px solid #d9dee7}.eod-kind{display:inline-flex;align-items:center;min-height:21px;padding:3px 7px;border-radius:999px;font-size:8px;font-weight:900;margin-bottom:5px}.eod-kind.rework{background:#fff0c2;color:#7a5200;border:1px solid #f2cf68}.eod-kind.progress{background:#f2f4f7;color:#475467;border:1px solid #e4e7ec}@media(max-width:900px){.eod-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.eod-summary{grid-template-columns:1fr}}
        .muted{color:#7c8492;font-size:10px}
        .attachment-actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
        .attachment-download{display:inline-flex;align-items:center;justify-content:center;padding:6px 9px;border:1px solid #dfe3ea;border-radius:8px;background:#fff;color:#344054;font-size:9px;font-weight:850;text-decoration:none;white-space:nowrap}
        .attachment-download:hover{background:#f7f8fa;border-color:#cfd4dc;color:#111827}
        .attachment-preview-backdrop{position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.62);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:24px}
        .attachment-preview-modal{width:min(1120px,96vw);height:min(820px,92vh);background:#fff;border-radius:16px;box-shadow:0 28px 90px rgba(0,0,0,.3);overflow:hidden;display:flex;flex-direction:column}
        .attachment-preview-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 15px;border-bottom:1px solid #e7e9ef;background:#fff}
        .attachment-preview-title{min-width:0;font-size:12px;font-weight:900;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .attachment-preview-actions{display:flex;align-items:center;gap:7px;flex-shrink:0}
        .attachment-preview-download,.attachment-preview-close{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:7px 11px;border-radius:9px;font-size:10px;font-weight:850;text-decoration:none;cursor:pointer}
        .attachment-preview-download{background:#111827;color:#fff;border:1px solid #111827}
        .attachment-preview-close{background:#fff;color:#344054;border:1px solid #dfe3ea}
        .attachment-preview-body{flex:1;min-height:0;background:#f3f4f6;padding:10px}
        .attachment-preview-frame{width:100%;height:100%;border:0;border-radius:10px;background:#fff}
        @media(max-width:700px){.attachment-preview-backdrop{padding:10px}.attachment-preview-modal{width:100%;height:92vh;border-radius:13px}.attachment-preview-head{padding:10px}.attachment-preview-download,.attachment-preview-close{padding:6px 9px;font-size:9px}}
        .swap-readonly-note{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:750;margin-bottom:12px}
        .toast{position:fixed;right:22px;bottom:22px;z-index:9999;background:#15171c;color:#fff;border-left:4px solid #e30613;padding:12px 15px;border-radius:10px;font-size:11px;box-shadow:0 15px 40px rgba(0,0,0,.2)}
        .btn[disabled],button[disabled]{opacity:.55;cursor:not-allowed!important;pointer-events:none;transform:none!important}
        .btn.is-loading{position:relative}
        .btn.is-loading::after{content:'';width:11px;height:11px;margin-left:7px;border:2px solid currentColor;border-right-color:transparent;border-radius:999px;display:inline-block;vertical-align:-2px;animation:btn-spin .65s linear infinite}
        @keyframes btn-spin{to{transform:rotate(360deg)}}
        @media(max-width:900px){.comment-actions{align-items:flex-start;flex-direction:column}.special-detail-grid{grid-template-columns:1fr}.eod-summary{grid-template-columns:1fr}.eod-record{grid-template-columns:1fr 1fr}.eod-entry-form{align-items:stretch;flex-direction:column}.eod-field{min-width:0;width:100%}}

        .edit-history-list{display:flex;flex-direction:column;gap:10px}
        .edit-history-batch{border:1px solid #e7e9ee;border-radius:12px;background:#fff;overflow:hidden}
        .edit-history-batch-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:#f8f9fb;border-bottom:1px solid #eceef2}
        .edit-history-editor{font-size:10px;font-weight:850;color:#344054}.edit-history-time{font-size:9px;color:#7b8493}
        .edit-history-row{padding:11px 12px;border-bottom:1px solid #f0f1f3}.edit-history-row:last-child{border-bottom:0}
        .edit-history-field{font-size:9px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;color:#667085;margin-bottom:7px}
        .edit-history-values{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);gap:8px;align-items:center}
        .edit-history-old,.edit-history-new{padding:9px 10px;border-radius:9px;font-size:10px;line-height:1.45;overflow-wrap:anywhere}
        .edit-history-old{background:#fff1f1;border:1px solid #fecaca;color:#9b1c1c}.edit-history-old del{text-decoration-thickness:1.5px}
        .edit-history-new{background:#ecfdf3;border:1px solid #abefc6;color:#067647;font-weight:750}.edit-history-arrow{text-align:center;color:#98a2b3;font-weight:900}

        .progress-card{padding:13px;border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-top:12px}.progress-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.progress-title{font-size:10px;font-weight:900;color:#344054}.progress-value{font-size:11px;font-weight:950}.progress-track{height:9px;background:#eef0f3;border-radius:999px;overflow:hidden;margin-top:8px}.progress-fill{height:100%;border-radius:999px;transition:width .25s}.progress-start .progress-fill{background:#94a3b8}.progress-low .progress-fill{background:#f59e0b}.progress-mid .progress-fill{background:#3b82f6}.progress-high .progress-fill{background:#8b5cf6}.progress-complete .progress-fill{background:#16a34a}.progress-start .progress-value{color:#64748b}.progress-low .progress-value{color:#b45309}.progress-mid .progress-value{color:#1d4ed8}.progress-high .progress-value{color:#7c3aed}.progress-complete .progress-value{color:#15803d}.collapse-panel{border:1px solid #e7e9ef;border-radius:12px;background:#fff;margin-bottom:14px;overflow:hidden}.collapse-panel summary{list-style:none;cursor:pointer;padding:12px 14px;font-size:11px;font-weight:900;color:#1d2939;display:flex;justify-content:space-between;align-items:center}.collapse-panel summary::-webkit-details-marker{display:none}.collapse-panel summary:after{content:'+';font-size:17px;color:#667085}.collapse-panel[open] summary:after{content:'−'}.collapse-panel .collapse-body{border-top:1px solid #eef0f3;padding:14px}.task-update-note{padding:10px 12px;border-radius:10px;background:#fffaeb;border:1px solid #fedf89;color:#93370d;font-size:10px;margin-bottom:12px}.rework-box{padding:13px;border:1px solid #fecaca;background:#fff7f7;border-radius:12px;margin-bottom:14px}.rework-flow-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:12px 0}.rework-flow-card{padding:11px;border:1px solid #fee2e2;border-radius:10px;background:#fff}.rework-flow-card span{display:block;font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#991b1b}.rework-flow-card strong{display:block;margin-top:4px;font-size:15px;color:#111827}.rework-route-note{display:flex;gap:9px;align-items:flex-start;padding:10px 11px;border-radius:10px;background:#fff;border:1px solid #fed7aa;color:#9a3412;font-size:9px;line-height:1.5;margin:10px 0}.rework-upload-wrap{padding:12px;border:1px dashed #fca5a5;border-radius:10px;background:#fff}.rework-upload-wrap input[type=file]{width:100%;padding:9px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}.rework-submit{margin-top:10px;min-width:190px}.rework-stage-note{padding:10px 12px;border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:9px;line-height:1.5;margin-top:10px}@media(max-width:700px){.rework-flow-grid{grid-template-columns:1fr}}.update-file{margin-top:8px;font-size:9px}.history-section-title{font-size:11px;font-weight:900;margin:18px 0 9px;color:#1d2939}.history-section-title:first-child{margin-top:0}
    
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


        /* Rework/status readability refresh */
        .page-head h1{font-size:20px!important;font-weight:900!important;letter-spacing:-.025em;color:#101828}
        .page-head p{font-size:10px!important;font-weight:650;color:#667085;margin-top:5px}
        .detail-tab{font-size:10px!important;font-weight:850!important;letter-spacing:-.005em}
        .panel-title{font-size:12px!important;font-weight:900!important;letter-spacing:-.01em;color:#101828}
        .label{font-size:10px!important;font-weight:800!important;color:#344054}
        .muted{font-size:9px;line-height:1.55;color:#667085}
        .stage-shell{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;width:100%}
        .stage-title{font-size:11px;font-weight:900;color:#101828}
        .stage-pill{display:inline-flex;align-items:center;gap:6px;min-height:26px;padding:5px 10px;border-radius:999px;font-size:9px;font-weight:900;border:1px solid transparent}
        .stage-pill::before{content:'';width:7px;height:7px;border-radius:999px;background:currentColor;opacity:.85}
        .stage-in_progress{color:#1d4ed8;background:#eff6ff;border-color:#bfdbfe}
        .stage-waiting_confirmation{color:#7c3aed;background:#f5f3ff;border-color:#ddd6fe}
        .stage-rework{color:#b42318;background:#fff1f1;border-color:#fecaca}
        .stage-completed{color:#15803d;background:#ecfdf3;border-color:#bbf7d0}
        .stage-default{color:#475467;background:#f2f4f7;border-color:#e4e7ec}
        .progress-card{padding:14px!important;border-radius:14px!important;box-shadow:0 5px 18px rgba(16,24,40,.05)}
        .progress-title{font-size:9px!important;text-transform:uppercase;letter-spacing:.055em}
        .progress-value{font-size:12px!important}
        .progress-track{height:10px!important;background:#eef2f6!important}
        .progress-complete{background:#f0fdf4!important;border-color:#bbf7d0!important}
        .rework-stage-note{font-size:10px!important;border-radius:12px!important;padding:11px 12px!important}
        .rework-box{padding:16px!important;border-radius:15px!important;background:linear-gradient(180deg,#fff7f7,#fff)!important;box-shadow:0 8px 22px rgba(180,35,24,.06)}
        .rework-flow-card{padding:13px!important;border-radius:12px!important;box-shadow:0 2px 8px rgba(16,24,40,.03)}
        .rework-flow-card:nth-child(1){background:#fff7ed;border-color:#fed7aa}
        .rework-flow-card:nth-child(1) span{color:#c2410c}
        .rework-flow-card:nth-child(2){background:#eff6ff;border-color:#bfdbfe}
        .rework-flow-card:nth-child(2) span{color:#1d4ed8}
        .rework-flow-card:nth-child(3){background:#fff1f1;border-color:#fecaca}
        .rework-flow-card:nth-child(3) span{color:#b42318}
        .rework-flow-card strong{font-size:18px!important;font-weight:950!important}
        .rework-upload-wrap{padding:14px!important;border-radius:12px!important;background:#fff!important}
        .rework-upload-wrap .premium-input,.rework-upload-wrap input[type=file]{font-size:10px!important;min-height:40px}
        .rework-submit{min-height:40px!important;padding:9px 15px!important;font-size:10px!important;font-weight:900!important}
        .eod-summary{gap:10px!important}
        .eod-stat{border-radius:12px!important;padding:13px!important}
        .eod-stat:nth-child(1){background:#f8fafc;border-color:#e2e8f0}
        .eod-stat:nth-child(2){background:#ecfdf3;border-color:#bbf7d0}
        .eod-stat:nth-child(3){background:#fff7ed;border-color:#fed7aa}
        .eod-stat span{font-size:8px!important;font-weight:900!important;letter-spacing:.055em;text-transform:uppercase}
        .eod-stat strong{font-size:18px!important;font-weight:950!important}
        .rework-complete-banner{display:flex;align-items:flex-start;gap:9px;padding:11px 12px;border:1px solid #bbf7d0;border-radius:12px;background:#ecfdf3;color:#166534;font-size:10px;line-height:1.5;font-weight:700;margin-bottom:12px}
        .rework-active-banner{display:flex;align-items:flex-start;gap:9px;padding:11px 12px;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed;color:#9a3412;font-size:10px;line-height:1.5;font-weight:700;margin-bottom:12px}
        @media(max-width:700px){.page-head h1{font-size:17px!important}.stage-shell{align-items:flex-start}.rework-upload-wrap>div{grid-template-columns:1fr!important}}

    .btn-progress-disabled{background:#fda4af!important;border-color:#fda4af!important;color:#fff!important;cursor:not-allowed!important;box-shadow:none!important;opacity:.72;pointer-events:none;transform:none!important}
.progress-gate-note{margin-top:6px;font-size:9px;font-weight:700;color:#c2410c}

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

    <div class="page-head">
        <div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <h1 style="margin:0">{{ $task->display_task_name }}</h1>
                @foreach($task->operation_pills as $pill)
                    <span class="{{ $pill['class'] }}">{{ $pill['label'] }}</span>
                @endforeach
                @if($swapInitiatorReadOnly)
                    <span class="badge badge-dark">Comment Only</span>
                @endif
                @if($selfDeclinedReadOnly)
                    <span class="badge badge-dark">Self Declined · Read Only</span>
                @endif
                @if($task->decline_outcome_label)
                    <span class="badge {{ str_contains($task->decline_outcome_label,'Rejected') ? 'badge-danger' : 'badge-success' }}">{{ $task->decline_outcome_label }}</span>
                @endif
            </div>
            <p>{{ $task->task_id }} · {{ $statuses[$task->status] ?? $task->status }}</p>
        </div>

        <div class="page-actions">
            <a class="btn btn-secondary" href="{{ route('designer.tasks.index') }}">Back to My Tasks</a>

            @unless($swapInitiatorReadOnly || $selfDeclinedReadOnly)

            @if(in_array('decline', $allowedRequestTypes, true))
                @if(in_array('decline', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Decline Pending</span>
                @else
                    <button class="btn btn-danger" wire:click="$dispatch('open-request-modal', { type: 'decline' })">Decline</button>
                @endif
            @endif

            @if(in_array('split', $allowedRequestTypes, true))
                @if(in_array('split', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Split Pending</span>
                @else
                    <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'split' })">Request Task Split</button>
                @endif
            @endif

            @if(in_array('swap', $allowedRequestTypes, true))
                @if(in_array('swap', $pendingRequestTypes, true))
                    <span class="badge badge-warning">Swap Pending</span>
                @else
                    <button class="btn btn-secondary" wire:click="$dispatch('open-request-modal', { type: 'swap' })">Request Task Transfer</button>
                @endif
            @endif

            @if($nextStatus)
                @php
                    $hasPendingDecline = in_array('decline', $pendingRequestTypes, true);
                    $waitingForBdReviewBlocked = $nextStatus === 'waiting_confirmation' && $progressPercentage < 100;
                    $moveBlocked = $hasPendingDecline || $waitingForBdReviewBlocked;
                    $moveBlockedReason = $hasPendingDecline ? 'Resolve the pending Decline request first.' : 'Complete 100% creative progress first.';
                @endphp
                <div style="display:flex;flex-direction:column;align-items:stretch">
                    <button
                        type="button"
                        class="btn btn-primary {{ $moveBlocked ? 'btn-progress-disabled' : '' }}"
                        @if($moveBlocked)
                            disabled
                            aria-disabled="true"
                            title="{{ $moveBlockedReason }}"
                        @else
                            wire:click="moveToNextStatus"
                            wire:loading.attr="disabled"
                            wire:target="moveToNextStatus"
                        @endif
                    >
                        @if($nextStatus === 'waiting_confirmation')
                            Move to Waiting for BD Review
                        @else
                            Move to {{ $statuses[$nextStatus] ?? ucwords(str_replace('_', ' ', $nextStatus)) }}
                        @endif
                    </button>
                    @if($moveBlocked)
                        <div class="progress-gate-note">{{ $moveBlockedReason }}</div>
                    @endif
                </div>
            @endif
            @endunless
        </div>
    </div>

    @if($swapInitiatorReadOnly)
        <div class="swap-readonly-note">
            Swap approved. You can view this task and add comments only.
        </div>
    @endif

    <div class="detail-tabs">
        <button class="detail-tab" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
        <button class="detail-tab" :class="{ active: tab === 'comments' }" @click="tab = 'comments'">Comments</button>
        @if($requests->where('request_type', 'decline')->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'decline-details' }" @click="tab = 'decline-details'">Decline Details</button>
        @endif
@if($splitRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'split-details' }" @click="tab = 'split-details'">Task Split Details</button>
        @endif
        @if($swapRequests->isNotEmpty())
            <button class="detail-tab" :class="{ active: tab === 'swap-details' }" @click="tab = 'swap-details'">Task Transfer Details</button>
        @endif
        <button class="detail-tab" :class="{ active: tab === 'history' }" @click="tab = 'history'">History</button>
        @if($clarificationComments->isNotEmpty())<button class="detail-tab" @click="tab = 'overview'; $nextTick(() => { $refs.clarificationSection.open = true; $refs.clarificationSection.scrollIntoView({behavior:'smooth'}); })">Clarification</button>@endif
        @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
            <button class="detail-tab" :class="{ active: tab === 'eod' }" @click="tab = 'eod'">Progress Updates</button>
        @endif
    </div>

    <section x-show="tab === 'overview'">
        <div class="detail-grid">
            <div>
                <details class="collapse-panel">
                    <summary>Task Information</summary>
                    <div class="collapse-body">
                        <div class="info-grid">
                            @foreach([
                                'Client / Agency' => ucfirst($task->party_type).' · '.$task->party_name,
                                'Contact Person' => $task->contact_person,
                                'Mobile Number' => $task->mobile_number,
                                'Vertical' => ucwords(str_replace('_', ' ', $task->vertical)),
                                'Task Nature' => ucwords(str_replace('_', ' ', $task->task_nature)),
                                'Priority' => ucfirst($task->priority),
                                'Due Date' => \Illuminate\Support\Carbon::parse($task->due_at)->format('d M Y'),
                                'Assigned By' => $task->assigner?->name ?? 'BD',
                                'Assigned At' => \Illuminate\Support\Carbon::parse($task->assigned_at)->format('d M Y'),
                                'Total Creatives' => $task->total_creatives,
                            ] as $key => $value)
                                <div class="info-item">
                                    <span>{{ $key }}</span>
                                    <strong>{{ $value }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>

                <details class="collapse-panel">
                    <summary>Task Requirements</summary>
                    <div class="collapse-body">
                        <div class="requirement-list">
                            @forelse(($task->requirements ?? []) as $key => $value)
                                @php
                                    $isRequirementFile = (is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                        || (is_array($value) && collect($value)->contains(fn ($item) => is_string($item) && str_contains($item, '/') && !filter_var($item, FILTER_VALIDATE_URL)));
                                @endphp
                                @continue(str_starts_with((string) $key, '_') || $isRequirementFile)
                                <div class="requirement-row">
                                    <div class="requirement-key">{{ ucwords(str_replace('_', ' ', $key)) }}</div>

                                    <div>
                                        @if($key === 'board_details' && is_array($value))
                                            @include('partials.board-details-table',['rows'=>$value])
                                        @elseif(is_array($value))
                                            @if(isset($value['square_feet']))
                                                {{ $value['width'] ?? '' }} × {{ $value['height'] ?? '' }} feet = {{ $value['square_feet'] }} sq.ft
                                            @else
                                                @foreach($value as $item)
                                                    @if(is_string($item) && str_contains($item, '/') && !filter_var($item, FILTER_VALIDATE_URL))
                                                        <div class="attachment-actions" style="margin-bottom:4px">
                                                            <a class="file-link" href="#" @click.prevent="openAttachment('{{ Storage::disk('spaces')->url($item) }}', '{{ basename($item) }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($item)]) }}')">{{ basename($item) }}</a>
                                                            <a class="attachment-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($item)]) }}">Download</a>
                                                        </div>
                                                    @else
                                                        <div>{{ is_scalar($item) ? $item : json_encode($item) }}</div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        @elseif(is_string($value) && str_contains($value, '/') && !filter_var($value, FILTER_VALIDATE_URL))
                                            <span class="attachment-actions">
                                                <a class="file-link" href="#" @click.prevent="openAttachment('{{ Storage::disk('spaces')->url($value) }}', '{{ basename($value) }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($value)]) }}')">{{ basename($value) }}</a>
                                                <a class="attachment-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($value)]) }}">Download</a>
                                            </span>
                                        @elseif(is_string($value) && filter_var($value, FILTER_VALIDATE_URL))
                                            <a class="file-link" href="{{ $value }}">{{ $value }}</a>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">No requirement data available.</div>
                            @endforelse
                        </div>
                    </div>
                </details>

                <details class="collapse-panel">
                    <summary>Attachments <span class="tab-count">{{ $requirementAttachmentCount }}</span></summary>
                    <div class="collapse-body">
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
                                            <a class="bd-file-btn bd-file-download" href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($file['path'])]) }}">Download</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <div class="empty-state">No task creation/edit attachments.</div>
                        @endforelse
                    </div>
                </details>

                @php
                    $showClarification = $task->status === 'need_clarification' || $clarificationComments->isNotEmpty();
                @endphp
                @if($showClarification)
                <details class="collapse-panel" open x-ref="clarificationSection">
                    <summary>Clarification <span class="tab-count">{{ $clarificationComments->count() }}</span></summary>
                    <div class="collapse-body">
                        <div class="comment-feed">
                            @forelse($clarificationComments as $item)
                                @php
                                    $clarificationRole = $item->user?->role ?? 'default';
                                    $clarificationName = $item->user?->name ?? 'User';
                                    $clarificationInitial = strtoupper(mb_substr($clarificationName, 0, 1));
                                @endphp

                                <article class="comment-item role-{{ $clarificationRole }}">
                                    <div class="comment-card-head">
                                        <div class="comment-user-wrap">
                                            <div class="comment-avatar">{{ $clarificationInitial }}</div>
                                            <div class="comment-user-meta">
                                                <div class="comment-user-name">{{ $clarificationName }}</div>
                                                <div class="comment-meta-row">
                                                    <span class="role-pill role-{{ $clarificationRole }}">{{ ucwords(str_replace('_', ' ', $clarificationRole)) }}</span>
                                                    <span class="comment-date">{{ $item->created_at->format('d M Y \\• g:i A') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="comment-card-body">
                                        <p class="comment-message">{{ $item->comment }}</p>

                                        @if($item->attachments->isNotEmpty())
                                            <div class="comment-attachments">
                                                @foreach($item->attachments as $attachment)
                                                    <div class="comment-attachment">
                                                        <div class="comment-attachment-main">
                                                            <div class="comment-file-icon">↗</div>
                                                            <div class="comment-file-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</div>
                                                        </div>

                                                        <div class="comment-file-actions">
                                                            <a
                                                                class="comment-file-btn"
                                                                href="#"
                                                                @click.prevent="openAttachment('{{ $attachment->url }}', '{{ $attachment->original_name }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}')"
                                                            >
                                                                Open
                                                            </a>
                                                            <a
                                                                class="comment-file-btn"
                                                                href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}"
                                                            >
                                                                Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="comment-empty">
                                    <div class="comment-empty-icon">✦</div>
                                    <strong>No clarification messages yet</strong>
                                    <span>Send a clarification message to start the conversation with the BD.</span>
                                </div>
                            @endforelse
                        </div>

                        @if($task->status === 'need_clarification' && ! $selfDeclinedReadOnly)
                            <div class="comment-compose" style="margin-top:14px">
                                <div class="comment-compose-head">
                                    <div>
                                        <div class="comment-compose-title">Clarification</div>
                                        <div class="muted" style="margin-top:3px;font-size:9px">Ask the BD for the information you need before continuing.</div>
                                    </div>
                                </div>

                                <div class="comment-compose-body">
                                    <textarea
                                        class="comment-textarea"
                                        wire:model="clarificationMessage"
                                        placeholder="Type your clarification here..."
                                    ></textarea>

                                    @error('clarificationMessage')
                                        <div class="error" style="margin-top:6px">{{ $message }}</div>
                                    @enderror

                                    <div class="comment-upload-row">
                                        <div class="comment-upload-box">
                                            <label class="label" style="font-size:9px;margin-bottom:5px">Attach File (Optional)</label>
                                            <input type="file" wire:model="clarificationAttachments" multiple data-accumulate-files>
                                            <div class="comment-upload-note">Up to 10 files · Maximum 100 MB each</div>
                                            <div class="comment-upload-note" wire:loading wire:target="clarificationAttachments">Preparing attachment...</div>
                                            @error('clarificationAttachments.*')
                                                <div class="error" style="margin-top:5px">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <button
                                            class="btn btn-primary comment-submit"
                                            wire:click="addClarification"
                                            wire:loading.attr="disabled"
                                            wire:target="addClarification,clarificationAttachments"
                                            wire:loading.class="is-loading"
                                        >
                                            Send Clarification
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </details>
                @endif
            </div>

            <aside>
                <div class="panel">
                    <div class="panel-header">
                        <div class="stage-shell">
                            <div class="stage-title">Current Status</div>
                            <span class="stage-pill stage-{{ in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true) ? $task->status : 'default' }}">
                                {{ $statuses[$task->status] ?? $task->status }}
                            </span>
                        </div>
                    </div>
                    <div class="panel-body">

                        @if(in_array($task->status, ['in_progress','waiting_confirmation','rework'], true))
                        <div class="progress-card progress-{{ $progressColorKey }}">
                            <div class="progress-head">
                                <span class="progress-title">Creative Progress</span>
                                <span class="progress-value">{{ $eodCompletedTotal }} / {{ $task->total_creatives }} · {{ $progressPercentage }}%</span>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ $progressPercentage }}%"></div></div>
                        </div>
                        @endif

                        <div class="activity-item" style="margin-top:12px">
                            <strong>Last Updated</strong>
                            <p>{{ $task->updated_at->diffForHumans() }}</p>
                        </div>

                        @if($task->status === 'rework')
                            @if($progressPercentage >= 100)
                                <div class="rework-complete-banner">
                                    <span>✓</span>
                                    <span><strong>100% complete.</strong> This Rework is ready for BD confirmation and will automatically move to Waiting for Confirmation.</span>
                                </div>
                            @else
                                <div class="rework-stage-note">
                                    Submit the remaining corrected creatives in <strong>Progress Updates</strong>.
                                    Rework stays active until progress reaches <strong>100%</strong>.
                                </div>
                            @endif
                        @elseif($nextStatus)
                            @php
                                $sidebarPendingDecline = in_array('decline', $pendingRequestTypes, true);
                                $waitingGateBlocked = $nextStatus === 'waiting_confirmation'
                                    && $progressPercentage < 100;
                                $sidebarMoveBlocked = $sidebarPendingDecline || $waitingGateBlocked;
                            @endphp

                            <button
                                class="btn btn-primary"
                                style="width:100%;margin-top:10px"
                                wire:click="moveToNextStatus"
                                wire:loading.attr="disabled"
                                wire:target="moveToNextStatus"
                                @disabled($sidebarMoveBlocked)
                            >
                                {{ $nextStatus === 'waiting_confirmation' ? 'Move to Waiting for BD Review' : 'Move to Next Stage' }}
                            </button>

                            @if($sidebarMoveBlocked)
                                <div class="muted" style="margin-top:7px;color:#b45309">
                                    {{ $sidebarPendingDecline ? 'Resolve the pending Decline request first.' : 'Complete 100% creative progress first.' }}
                                </div>
                            @endif
                        @endif

                        <button class="btn btn-secondary" style="width:100%;margin-top:8px" @click="tab = 'comments'">Add Comment</button>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section x-show="tab === 'comments'" style="display:none">
        <div class="comment-shell">
            @unless($selfDeclinedReadOnly)
            <div class="comment-compose">
                <div class="comment-compose-head">
                    <div>
                        <div class="comment-compose-title">Add Comment</div>
                        <div class="muted" style="margin-top:3px;font-size:9px">Share an update, clarification, or design note with the task team.</div>
                    </div>
                    <span class="comment-compose-stage">{{ $statuses[$task->status] ?? $task->status }}</span>
                </div>

                <div class="comment-compose-body">
                    <textarea
                        class="comment-textarea"
                        wire:model="comment"
                        placeholder="Write your comment here..."
                    ></textarea>

                    @error('comment')
                        <div class="error" style="margin-top:6px">{{ $message }}</div>
                    @enderror

                    <div class="comment-upload-row">
                        <div class="comment-upload-box">
                            <label class="label" style="font-size:9px;margin-bottom:5px">Attachments</label>
                            <input type="file" wire:model="attachments" multiple data-accumulate-files>
                            <div class="comment-upload-note">Up to 10 files · Maximum 100 MB each</div>
                            <div class="comment-upload-note" wire:loading wire:target="attachments">Preparing attachment...</div>
                            @error('attachments.*')
                                <div class="error" style="margin-top:5px">{{ $message }}</div>
                            @enderror
                        </div>

                        <button
                            class="btn btn-primary comment-submit"
                            wire:click="addComment"
                            wire:loading.attr="disabled"
                            wire:target="addComment,attachments"
                            wire:loading.class="is-loading"
                        >
                            Add Comment
                        </button>
                    </div>
                </div>
            </div>
            @endunless

            <div>
                <div class="comment-list-head">
                    <div class="comment-list-title">Conversation</div>
                    <span class="comment-count">{{ $generalComments->count() }}</span>
                </div>

                <div class="comment-feed">
                    @forelse($generalComments as $item)
                        @php
                            $commentRole = $item->user?->role ?? 'default';
                            $commentName = $item->user?->name ?? 'User';
                            $commentInitial = strtoupper(mb_substr($commentName, 0, 1));
                        @endphp

                        <article class="comment-item role-{{ $commentRole }}">
                            <div class="comment-card-head">
                                <div class="comment-user-wrap">
                                    <div class="comment-avatar">{{ $commentInitial }}</div>
                                    <div class="comment-user-meta">
                                        <div class="comment-user-name">{{ $commentName }}</div>
                                        <div class="comment-meta-row">
                                            <span class="role-pill role-{{ $commentRole }}">{{ ucwords(str_replace('_', ' ', $commentRole)) }}</span>
                                            <span class="comment-date">{{ $item->created_at->format('d M Y · h:i A') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <span class="badge badge-dark comment-status">{{ $statuses[$item->status_at_comment] ?? $item->status_at_comment }}</span>
                            </div>

                            <div class="comment-card-body">
                                <p class="comment-message">{{ $item->comment }}</p>

                                @if($item->attachments->isNotEmpty())
                                    <div class="comment-attachments">
                                        @foreach($item->attachments as $attachment)
                                            <div class="comment-attachment">
                                                <div class="comment-attachment-main">
                                                    <div class="comment-file-icon">↗</div>
                                                    <div class="comment-file-name" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</div>
                                                </div>

                                                <div class="comment-file-actions">
                                                    <a
                                                        class="comment-file-btn"
                                                        href="#"
                                                        @click.prevent="openAttachment('{{ $attachment->url }}', '{{ $attachment->original_name }}', '{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}')"
                                                    >
                                                        Open
                                                    </a>
                                                    <a
                                                        class="comment-file-btn"
                                                        href="{{ route('designer.tasks.attachments.download', ['task' => $task->id, 'file' => base64_encode($attachment->path)]) }}"
                                                    >
                                                        Download
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="comment-empty">
                            <div class="comment-empty-icon">✦</div>
                            <strong>No comments yet</strong>
                            <span>Start the conversation with the first task update.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>


        @if(in_array($task->status, ['in_progress','waiting_confirmation','rework','completed'], true))
    <section x-show="tab === 'eod'" style="display:none">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <div class="panel-title">Progress Updates</div>
                        <div class="muted" style="margin-top:3px">
                            Update creative progress with a mandatory ZIP attachment.
                        </div>
                    </div>
                    <span class="badge badge-dark">{{ $eodRecords->count() }} Updates</span>
                </div>
    
                <div class="panel-body">
                    <div class="eod-summary">
                        <div class="eod-stat">
                            <span>Total Creatives</span>
                            <strong>{{ $task->total_creatives }}</strong>
                        </div>
                        <div class="eod-stat">
                            <span>Completed</span>
                            <strong>{{ $eodCompletedTotal }}</strong>
                        </div>
                        <div class="eod-stat">
                            <span>Remaining</span>
                            <strong class="{{ $eodRemaining === 0 ? 'eod-zero' : '' }}">{{ $eodRemaining }}</strong>
                        </div>
                        <div class="eod-stat rework-stat">
                            <span>Rework Count</span>
                            <strong>{{ $reworkCount }}</strong>
                        </div>
                    </div>
    
    
                    @if($task->status === 'rework')
                        @if($progressPercentage >= 100)
                            <div class="rework-complete-banner">
                                <span>✓</span>
                                <span><strong>All creatives are complete.</strong> Status is being moved to Waiting for Confirmation.</span>
                            </div>
                        @elseif($currentReworkPending > 0)
                            <div class="rework-active-banner">
                                <span>↻</span>
                                <span><strong>Rework #{{ $reworkCount }} is active.</strong> {{ $currentReworkPending }} creative(s) still need corrected submission.</span>
                            </div>
                        @endif

                        <div class="rework-box">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">
                                <div>
                                    <strong style="font-size:13px">Rework #{{ $reworkCount }}</strong>
                                    <div class="muted" style="margin-top:3px">Rework number changes only when BD starts a new Rework cycle.</div>
                                </div>
                                <span class="badge badge-danger">{{ $currentReworkPending }} Pending</span>
                            </div>

                            <div class="rework-flow-grid">
                                <div class="rework-flow-card">
                                    <span>BD Sent for Rework</span>
                                    <strong>{{ $currentReworkRequested }}</strong>
                                </div>
                                <div class="rework-flow-card">
                                    <span>Reworked Submitted</span>
                                    <strong>{{ $currentReworkCompleted }}</strong>
                                </div>
                                <div class="rework-flow-card">
                                    <span>Rework Pending</span>
                                    <strong>{{ $currentReworkPending }}</strong>
                                </div>
                            </div>

                            @if($latestReworkReview?->comment)
                                <div style="padding:10px 11px;border-radius:10px;background:#fff;border:1px solid #fee2e2;margin-bottom:10px">
                                    <div style="font-size:8px;font-weight:900;text-transform:uppercase;color:#991b1b">BD Rework Comment</div>
                                    <div style="margin-top:4px;font-size:10px;font-weight:500;line-height:1.5;color:#344054;white-space:pre-wrap">{{ $latestReworkReview->comment }}</div>
                                </div>
                            @endif

                            @if($currentReworkPending > 0 && $progressPercentage < 100 && ! $selfDeclinedReadOnly)
                            <div class="rework-upload-wrap">
                                <div style="display:grid;grid-template-columns:minmax(150px,.55fr) minmax(240px,1fr);gap:10px;align-items:end">
                                    <div>
                                        <label class="label">Number of Creatives Reworked *</label>
                                        <input
                                            class="premium-input"
                                            type="number"
                                            min="1"
                                            max="{{ $currentReworkPending }}"
                                            wire:model="reworkCompletedCount"
                                            placeholder="e.g. 1"
                                        >
                                        @error('reworkCompletedCount')<div class="error">{{ $message }}</div>@enderror
                                    </div>

                                    <div>
                                        <label class="label">Corrected Rework ZIP *</label>
                                        <input type="file" accept=".zip,application/zip" wire:model="reworkAttachment">
                                        <div class="muted" style="margin-top:5px">ZIP only · Maximum 100 MB</div>
                                        @error('reworkAttachment')<div class="error">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <button
                                    class="btn btn-primary rework-submit"
                                    wire:click="submitReworkUpdate"
                                    wire:loading.attr="disabled"
                                    wire:target="submitReworkUpdate,reworkAttachment"
                                    wire:loading.class="is-loading"
                                    @disabled($currentReworkPending < 1)
                                >
                                    Submit Rework Progress
                                </button>
                            </div>
                            @endif
                        </div>
                    @endif
                    @if($swapInitiatorReadOnly || $selfDeclinedReadOnly)
                        <div class="empty-state" style="margin-bottom:14px">
                            Progress Updates history is view-only.
                        </div>
                    @elseif($task->status === 'in_progress' && $eodRemaining > 0)
                        <div class="eod-entry-form">
                            <div class="eod-field">
                                <label class="label" for="eodCompletedCount">Progress Added</label>
                                <input
                                    id="eodCompletedCount"
                                    class="premium-input"
                                    type="number"
                                    min="1"
                                    max="{{ $eodRemaining }}"
                                    wire:model="eodCompletedCount"
                                    placeholder="Enter completed creatives"
                                >
                                @error('eodCompletedCount')
                                    <div class="muted" style="color:#b4232f;margin-top:5px">{{ $message }}</div>
                                @enderror
                            </div>
    
                            <div class="eod-field">
                                <label class="label">Progress Updates ZIP *</label>
                                <input class="field" type="file" accept=".zip,application/zip" wire:model="taskUpdateAttachment">
                                @error('taskUpdateAttachment')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            <button
                                class="btn btn-primary"
                                wire:click="submitEod"
                                wire:loading.attr="disabled"
                                wire:target="submitEod"
                                wire:loading.class="is-loading"
                            >
                                Submit Progress Updates
                            </button>
                        </div>
                    @elseif(in_array($task->status, ['waiting_confirmation','completed'], true))
                        <div class="empty-state" style="margin-bottom:14px">
                            Creative progress is complete. Progress Updates history is view-only in this stage.
                        </div>
                    @endif
    
                    <div class="eod-history">
                        @forelse($eodRecords as $record)
                            @php
                                $isReworkRecord = ($record->update_type ?? 'progress') === 'rework';
                            @endphp
                            <div class="eod-record {{ $isReworkRecord ? 'is-rework' : 'is-progress' }}">
                                <div class="eod-record-main">
                                    <span class="eod-kind {{ $isReworkRecord ? 'rework' : 'progress' }}">{{ $isReworkRecord ? 'Rework Submission' : 'Progress Submission' }}</span>
                                    <strong>Submitted by {{ $record->designer?->name ?? 'Designer' }}</strong>
                                    <span>{{ $record->submitted_at->format('d M Y · h:i A') }}</span>
                                    @if($record->attachment_url)
                                        <a class="update-file" target="_blank" href="{{ $record->attachment_url }}">{{ $record->attachment_original_name ?? 'Download ZIP' }}</a>
                                    @endif
                                    @if(($record->update_type ?? 'progress') === 'rework')
                                        <span class="badge badge-danger" style="margin-top:5px">Rework #{{ $record->rework_count_snapshot }}</span>
                                    @endif
                                </div>
    
                                <div class="eod-record-cell">
                                    <span>{{ $isReworkRecord ? 'Reworked Creatives' : 'Progress Added' }}</span>
                                    <strong>{{ $record->completed_count }}</strong>
                                </div>
    
                                <div class="eod-record-cell">
                                    <span>Total Creatives</span>
                                    <strong>{{ $record->total_creatives_snapshot }}</strong>
                                </div>
    
                                <div class="eod-record-cell">
                                    <span>Total Completed</span>
                                    <strong>{{ $record->cumulative_completed }}</strong>
                                </div>
    
                                <div class="eod-record-cell">
                                    <span>Remaining</span>
                                    <strong class="{{ $record->remaining_creatives === 0 ? 'eod-zero' : '' }}">{{ $record->remaining_creatives }}</strong>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">No Progress Updates records have been submitted yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    
    @endif

    @if($requests->where('request_type', 'decline')->isNotEmpty())
        <section x-show="tab === 'decline-details'" style="display:none">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">Decline Details</div>
                </div>
                <div class="panel-body">
                    @foreach($requests->where('request_type', 'decline') as $declineRequest)
                        @php
                            $declinePending = in_array(
                                $declineRequest->overall_status,
                                ['pending_approval', 'pending_designer_head', 'pending_admin'],
                                true
                            );

                            $declineBadge = $declineRequest->overall_status === 'approved'
                                ? 'badge-success'
                                : ($declineRequest->overall_status === 'rejected' ? 'badge-danger' : 'badge-warning');

                            $declineDecider = $declineRequest->adminActor ?: $declineRequest->designerHeadActor;
                        @endphp

                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Decline Request</strong>
                                <span class="badge {{ $declineBadge }}">
                                    {{ $declinePending ? 'Pending' : ($declineRequest->overall_status === 'rejected' ? 'Declined' : 'Approved') }}
                                </span>
                            </div>

                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card">
                                    <span>Requested By</span>
                                    <strong>{{ $declineRequest->requester?->name ?? 'Designer' }}</strong>
                                </div>
                                <div class="special-detail-card">
                                    <span>Requested At</span>
                                    <strong>{{ $declineRequest->created_at?->format('d M Y · h:i A') }}</strong>
                                </div>

                                <div class="special-detail-card">
                                    <span>Approved Designer</span>
                                    <strong>{{ $declineRequest->approvedDesigner?->name ?? '—' }}</strong>
                                </div>

                                <div class="special-detail-card">
                                    <span>Responded By</span>
                                    <strong>{{ $declineDecider?->name ?? '—' }}</strong>
                                </div>

                                <div class="special-detail-card">
                                    <span>Responded At</span>
                                    <strong>
                                        {{ $declinePending ? 'Pending Response' : ($declineRequest->admin_action_at?->format('d M Y · h:i A')
                                            ?? $declineRequest->designer_head_action_at?->format('d M Y · h:i A')
                                            ?? '—') }}
                                    </strong>
                                </div>
                            </div>

                            <div style="margin-top:10px">
                                <strong>Request Reason</strong>
                                <p style="white-space:pre-wrap">{{ $declineRequest->reason }}</p>
                            </div>

                            @if($declineRequest->overall_status === 'rejected')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#fff5f5;color:#991b1b">
                                    <strong>Decline Reason</strong>
                                    <p style="margin:4px 0 0;white-space:pre-wrap">{{ $declineRequest->decision_reason }}</p>
                                </div>
                            @elseif($declineRequest->overall_status === 'approved')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#f0fdf4;color:#08784b">
                                    <strong>Approval Reason</strong>
                                    <p style="margin:4px 0 0;white-space:pre-wrap">{{ $declineRequest->decision_reason ?: '—' }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif


    @if($splitRequests->isNotEmpty())
        <section x-show="tab === 'split-details'" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Task Split Details · {{ $splitRequests->count() }} Attempt{{ $splitRequests->count() === 1 ? '' : 's' }}</div></div>
                <div class="panel-body">
                    @if($splitOriginTask)
                        <div class="activity-item" style="margin-bottom:12px">
                            <strong>This task was created from a split</strong>
                            <p>Original task: {{ $splitOriginTask->task_id }} · {{ $splitOriginTask->display_task_name ?? $splitOriginTask->task_name }}</p>
                        </div>
                    @endif
                    @foreach($splitRequests as $splitRequest)
                        @php
                            $splitChild = $splitChildren->get($splitRequest->split_details['created_task_id'] ?? null);
                            $splitDecider = $splitRequest->adminActor ?: $splitRequest->designerHeadActor;
                            $splitPending = in_array($splitRequest->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                            $splitBadge = $splitRequest->overall_status === 'approved' ? 'badge-success' : ($splitRequest->overall_status === 'rejected' ? 'badge-danger' : 'badge-warning');
                            $requestedSplit = $splitRequest->split_details['requested_creative_count'] ?? $splitRequest->split_details['creative_count'] ?? '—';
                            $approvedSplit = $splitRequest->split_details['approved_creative_count'] ?? '—';
                        @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Split Request</strong>
                                <span class="badge {{ $splitBadge }}">{{ $splitPending ? 'Pending' : ($splitRequest->overall_status === 'rejected' ? 'Declined' : 'Approved') }}</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Requested By</span><strong>{{ $splitRequest->requester?->name ?? 'Designer' }}</strong></div>
                                <div class="special-detail-card"><span>Requested At</span><strong>{{ $splitRequest->created_at?->format('d M Y · h:i A') }}</strong></div>
                                <div class="special-detail-card"><span>Requested Split</span><strong>{{ $requestedSplit }} creatives</strong></div>
                                <div class="special-detail-card"><span>Approved Split</span><strong>{{ $approvedSplit === '—' ? '—' : $approvedSplit.' creatives' }}</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $splitRequest->targetDesigner?->name ?? 'No preference' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $splitRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Created Split Task</span><strong>{{ $splitChild?->task_id ?? ($splitRequest->split_details['created_task_code'] ?? '—') }}</strong></div>
                                <div class="special-detail-card"><span>Responded By</span><strong>{{ $splitDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Responded At</span><strong>{{ $splitPending ? 'Pending Response' : ($splitRequest->admin_action_at?->format('d M Y · h:i A') ?? $splitRequest->designer_head_action_at?->format('d M Y · h:i A') ?? '—') }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Request Reason</strong><p style="white-space:pre-wrap">{{ $splitRequest->reason }}</p></div>
                            @if($splitRequest->overall_status === 'rejected')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#fff5f5;color:#991b1b"><strong>Decline Reason</strong><p style="margin:4px 0 0;white-space:pre-wrap">{{ $splitRequest->decision_reason }}</p></div>
                            @endif
                            @if(!empty($splitRequest->split_details['details']))<div style="margin-top:8px"><strong>Split Notes</strong><p style="white-space:pre-wrap">{{ $splitRequest->split_details['details'] }}</p></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($swapRequests->isNotEmpty())
        <section x-show="tab === 'swap-details'" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Task Transfer Details · {{ $swapRequests->count() }} Attempt{{ $swapRequests->count() === 1 ? '' : 's' }}</div></div>
                <div class="panel-body">
                    @foreach($swapRequests as $swapRequest)
                        @php
                            $swapDecider = $swapRequest->adminActor ?: $swapRequest->designerHeadActor;
                            $swapPending = in_array($swapRequest->overall_status, ['pending_approval','pending_designer_head','pending_admin'], true);
                            $swapBadge = $swapRequest->overall_status === 'approved' ? 'badge-success' : ($swapRequest->overall_status === 'rejected' ? 'badge-danger' : 'badge-warning');
                        @endphp
                        <div class="activity-item" style="margin-bottom:12px">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                <strong>Swap Request</strong>
                                <span class="badge {{ $swapBadge }}">{{ $swapPending ? 'Pending' : ($swapRequest->overall_status === 'rejected' ? 'Declined' : 'Approved') }}</span>
                            </div>
                            <div class="special-detail-grid" style="margin-top:10px">
                                <div class="special-detail-card"><span>Requested By</span><strong>{{ $swapRequest->requester?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Requested At</span><strong>{{ $swapRequest->created_at?->format('d M Y · h:i A') }}</strong></div>
                                <div class="special-detail-card"><span>Preferred Designer</span><strong>{{ $swapRequest->targetDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Approved Designer</span><strong>{{ $swapRequest->approvedDesigner?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Responded By</span><strong>{{ $swapDecider?->name ?? '—' }}</strong></div>
                                <div class="special-detail-card"><span>Responded At</span><strong>{{ $swapPending ? 'Pending Response' : ($swapRequest->admin_action_at?->format('d M Y · h:i A') ?? $swapRequest->designer_head_action_at?->format('d M Y · h:i A') ?? '—') }}</strong></div>
                                <div class="special-detail-card"><span>Current Task Designer</span><strong>{{ $task->designer?->name ?? '—' }}</strong></div>
                            </div>
                            <div style="margin-top:10px"><strong>Request Reason</strong><p style="white-space:pre-wrap">{{ $swapRequest->reason }}</p></div>
                            @if($swapRequest->overall_status === 'rejected')
                                <div style="margin-top:8px;padding:10px 12px;border-radius:10px;background:#fff5f5;color:#991b1b"><strong>Decline Reason</strong><p style="margin:4px 0 0;white-space:pre-wrap">{{ $swapRequest->decision_reason }}</p></div>
                            @endif
                            @if(!empty($swapRequest->split_details['notes']))<div style="margin-top:8px"><strong>Notes</strong><p style="white-space:pre-wrap">{{ $swapRequest->split_details['notes'] }}</p></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section x-show="tab === 'history'" style="display:none" x-data="{ historyView: 'pipeline' }">
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
                                · {{ $event['created_at']->format('d M Y · h:i A') }}
                            </div>
                        </div>
                    @empty
                        <div class="history-nothing">No pipeline activity has been recorded yet.</div>
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
                                    {{ $firstChange->created_at?->format('d M Y · h:i A') }}
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


    <div
        class="attachment-preview-backdrop"
        x-show="attachmentPreviewOpen"
        x-transition.opacity
        x-cloak
        @click.self="closeAttachment()"
        @keydown.escape.window="if (attachmentPreviewOpen) closeAttachment()"
        style="display:none"
    >
        <div class="attachment-preview-modal">
            <div class="attachment-preview-head">
                <div class="attachment-preview-title" x-text="attachmentPreviewName"></div>
                <div class="attachment-preview-actions">
                    <a
                        class="attachment-preview-download"
                        :href="attachmentDownloadUrl"
                    >
                        Download
                    </a>
                    <button type="button" class="attachment-preview-close" @click="closeAttachment()">Close</button>
                </div>
            </div>

            <div class="attachment-preview-body">
                <iframe
                    class="attachment-preview-frame"
                    :src="attachmentPreviewOpen ? attachmentPreviewUrl : 'about:blank'"
                    :title="attachmentPreviewName"
                ></iframe>
            </div>
        </div>
    </div>

    <div class="toast" x-show="toast" x-transition x-text="toast" style="display:none"></div>

    @unless($swapInitiatorReadOnly || $selfDeclinedReadOnly)
        <livewire:designer.task-request-modal :task="$task" :key="'task-request-modal-'.$task->id" />
    @endunless
</div>
