{{-- Shared by the Designer (Livewire) and BD task detail floating chat. --}}
<style>
            [x-cloak]{display:none!important}
            .cb-root{position:fixed;right:24px;bottom:24px;z-index:900;font-family:inherit}
            .cb-launch{position:relative;width:58px;height:58px;border:0;border-radius:50%;background:linear-gradient(135deg,#f0303d,#d4111f);color:#fff;display:grid;place-items:center;cursor:pointer;box-shadow:0 10px 26px rgba(227,6,19,.38);transition:transform .15s ease,box-shadow .15s ease}
            .cb-launch:hover{transform:translateY(-2px) scale(1.04);box-shadow:0 14px 30px rgba(227,6,19,.45)}
            .cb-launch svg{width:26px;height:26px}
            .cb-badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#101828;color:#fff;border:2px solid #fff;font-size:10px;font-weight:900;display:grid;place-items:center}
            .cb-panel{position:absolute;right:0;bottom:0;width:390px;max-width:calc(100vw - 32px);height:min(600px,calc(100vh - 120px));background:#fff;border:1px solid #eceff4;border-radius:20px;box-shadow:0 24px 60px rgba(16,24,40,.28);display:flex;flex-direction:column;overflow:hidden;transform-origin:bottom right}
            .cb-head{display:flex;align-items:center;gap:10px;padding:13px 14px;background:linear-gradient(135deg,#f0303d,#c8101c);color:#fff}
            .cb-head-ico{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.2);display:grid;place-items:center;flex:none}
            .cb-head-ico svg{width:18px;height:18px}
            .cb-title{font-size:13px;font-weight:900;line-height:1.2}
            .cb-sub{font-size:10.5px;opacity:.85;margin-top:2px;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .cb-min{margin-left:auto;width:32px;height:32px;border:0;border-radius:50%;background:rgba(255,255,255,.18);color:#fff;display:grid;place-items:center;cursor:pointer}
            .cb-min:hover{background:rgba(255,255,255,.3)}
            .cb-min svg{width:18px;height:18px}
            .cb-body{flex:1;overflow-y:auto;padding:14px;background:#f7f8fa;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth}
            .cb-empty{margin:auto;text-align:center;color:#98a2b3;font-size:11.5px;padding:20px}
            .cb-empty strong{display:block;color:#344054;font-size:12.5px;margin-bottom:4px}
            .cb-msg{display:flex;flex-direction:column;max-width:84%;gap:3px}
            .cb-msg.is-me{align-self:flex-end;align-items:flex-end}
            .cb-msg.is-them{align-self:flex-start;align-items:flex-start}
            .cb-who{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:800;color:#475467}
            .cb-bubble{padding:9px 12px;border-radius:16px;font-size:12px;line-height:1.5;word-break:break-word;white-space:pre-wrap;box-shadow:0 1px 2px rgba(16,24,40,.06)}
            .cb-msg.is-me .cb-bubble{background:#e30613;color:#fff;border-bottom-right-radius:5px}
            .cb-msg.is-them .cb-bubble{background:#fff;color:#101828;border:1px solid #eceff4;border-bottom-left-radius:5px}
            .cb-time{font-size:9.5px;color:#98a2b3}
            .cb-files{display:flex;flex-direction:column;gap:5px;margin-top:2px;max-width:100%}
            .cb-file{display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:12px;font-size:10.5px;font-weight:700;background:#fff;border:1px solid #eceff4;color:#344054;max-width:100%}
            .cb-msg.is-me .cb-file{background:#fff4f5;border-color:#f6cdd1}
            .cb-file-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
            .cb-file a{color:#d4111f;font-weight:900;text-decoration:none;font-size:10px;white-space:nowrap}
            .cb-file a:hover{text-decoration:underline}
            .cb-foot{padding:10px 12px 12px;background:#fff;border-top:1px solid #eceff4}
            .cb-compose{display:flex;align-items:flex-end;gap:8px}
            .cb-attach{width:38px;height:38px;border-radius:50%;border:1px solid #e1e5ec;background:#fff;color:#475467;display:grid;place-items:center;cursor:pointer;flex:none;transition:background .15s,color .15s}
            .cb-attach:hover{background:#fff4f5;color:#d4111f}
            .cb-attach svg{width:18px;height:18px}
            .cb-pick{position:relative}
            .cb-pick:empty{display:none}
            .cb-file-input{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden;pointer-events:none}
            .cb-input{flex:1;min-height:38px;max-height:110px;resize:none;border:1px solid #dfe3ea;border-radius:19px;padding:9px 14px;font:inherit;font-size:12px;line-height:1.4;outline:none;background:#f7f8fa}
            .cb-input:focus{border-color:#e30613;background:#fff;box-shadow:0 0 0 3px rgba(227,6,19,.08)}
            .cb-send{width:38px;height:38px;border:0;border-radius:50%;background:#e30613;color:#fff;display:grid;place-items:center;cursor:pointer;flex:none;transition:background .15s,opacity .15s}
            .cb-send:hover{background:#b9050f}
            .cb-send:disabled{opacity:.55;cursor:wait}
            .cb-send svg{width:18px;height:18px}
            .cb-note{margin-top:7px;font-size:9.5px;color:#98a2b3;padding-left:2px}
            .cb-err{margin-top:6px;font-size:10.5px;color:#d92d20;font-weight:700}
            .cb-readonly{padding:14px;text-align:center;font-size:11px;color:#667085;background:#fff;border-top:1px solid #eceff4}
            .cb-foot .multi-file-list{margin-top:8px;max-height:96px;overflow:auto}
            @media(max-width:520px){.cb-root{right:12px;bottom:12px}.cb-panel{width:calc(100vw - 24px);height:calc(100vh - 90px)}}
            @media(prefers-reduced-motion:reduce){.cb-launch,.cb-body{transition:none;scroll-behavior:auto}}
    .cb-root .role-pill{display:inline-flex;align-items:center;border-radius:999px;padding:3px 7px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;background:#f2f4f7;color:#667085}
            .cb-root .role-pill.role-designer{background:#eff6ff;color:#1d4ed8}.cb-root .role-pill.role-bd{background:#ecfdf3;color:#15803d}.cb-root .role-pill.role-designer_head{background:#f5f3ff;color:#6d28d9}.cb-root .role-pill.role-admin{background:#fff1f2;color:#e30613}
    .cb-tabs{display:flex;gap:4px;padding:8px 12px 0;background:#fff;border-bottom:1px solid #eceff4}
            .cb-tabs button{border:0;background:transparent;padding:9px 14px;font-size:12px;font-weight:800;color:#667085;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;display:inline-flex;align-items:center;gap:6px}
            .cb-tabs button:hover{color:#101828}
            .cb-tabs button.is-active{color:#d4111f;border-bottom-color:#e30613}
            .cb-tab-count{min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#f2f4f7;color:#475467;font-size:10px;font-weight:900;display:inline-grid;place-items:center}
            .cb-tabs button.is-active .cb-tab-count{background:#fff1f2;color:#d4111f}
            .cb-filesbody{flex:1;overflow-y:auto;padding:14px;background:#f7f8fa;display:flex;flex-direction:column;gap:8px}
            .cb-filerow{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #eceff4;border-radius:12px;background:#fff}
            .cb-filerow-ico{width:34px;height:34px;border-radius:10px;background:#fff1f2;color:#d4111f;display:grid;place-items:center;font-size:15px;flex:none}
            .cb-filerow-main{min-width:0;flex:1}
            .cb-filerow-name{font-size:12px;font-weight:800;color:#101828;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .cb-filerow-meta{font-size:10.5px;color:#98a2b3;margin-top:2px}
            .cb-filerow-actions{display:flex;gap:8px;flex:none}
            .cb-filerow-actions a{font-size:11px;font-weight:900;color:#d4111f;text-decoration:none}
            .cb-filerow-actions a:hover{text-decoration:underline}
            .cb-root .mf-notice{font-size:10.5px;font-weight:700;color:#b4232f;margin-top:5px}
            .cb-root .mf-notice:empty{display:none}
            .cb-root .mf-row{position:relative;border:1px solid #e4e7ec;border-radius:10px;padding:7px 26px 7px 10px;margin-top:6px;background:#fff}
            .cb-root .mf-top{display:flex;justify-content:space-between;gap:8px;font-size:11px;font-weight:700;color:#344054}
            .cb-root .mf-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
            .cb-root .mf-bar{height:4px;border-radius:3px;background:#eef1f5;margin-top:5px;overflow:hidden}
            .cb-root .mf-fill{height:100%;width:0;background:#2970ff;transition:width .2s ease}
            .cb-root .mf-detail{font-size:10px;color:#667085;margin-top:3px}
            .cb-root .mf-status{font-size:10px}
            .cb-root .mf-ok{font-size:10px;font-weight:700;color:#067647}
            .cb-root .mf-done .mf-fill{background:#12b76a}
            .cb-root .mf-failed{border-color:#fecdca;background:#fef3f2}
            .cb-root .mf-failed .mf-fill{background:#f04438}
            .cb-root .mf-remove{position:absolute;top:3px;right:6px;border:none;background:none;color:#667085;font-size:17px;line-height:1;cursor:pointer}
            .cb-foot #cbUploadList{max-height:130px;overflow:auto}
            .cb-compose .cb-send:disabled{opacity:.55;cursor:not-allowed}
        </style>
