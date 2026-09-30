{{-- "Not submitted yet" state for admin task-detail tabs whose data comes
     from another role. Expects $title, $message, $role ('bd'|'designer'). --}}
@once
<style>
    .adm-pending{position:relative;overflow:hidden;border:1.5px dashed #f5c26b;border-radius:14px;background:linear-gradient(180deg,#fffcf5 0%,#fff7e8 100%);padding:30px 20px 26px;text-align:center}
    .adm-pending::before{content:'';position:absolute;inset:0;background:linear-gradient(100deg,transparent 30%,rgba(255,255,255,.8) 50%,transparent 70%);transform:translateX(-100%);animation:adm-pending-shimmer 2.8s ease-in-out infinite;pointer-events:none}
    .adm-pending>*{position:relative;z-index:1}
    .adm-pending-icon{width:58px;height:58px;margin:0 auto 13px;display:grid;place-items:center;border-radius:50%;background:#fff1d2;color:#b54708}
    .adm-pending-icon::before,.adm-pending-icon::after{content:'';position:absolute;inset:0;border-radius:50%;border:2px solid #f5b54a;opacity:0;animation:adm-pending-ripple 2.4s ease-out infinite}
    .adm-pending-icon::after{animation-delay:1.2s}
    .adm-pending-icon svg{animation:adm-pending-flip 2.4s ease-in-out infinite}
    .adm-pending-title{font-size:12px;font-weight:950;color:#7a4b00}
    .adm-pending-message{max-width:430px;margin:5px auto 0;font-size:10px;line-height:1.6;color:#8a6a33}
    .adm-pending-badge{display:inline-flex;align-items:center;gap:7px;margin-top:13px;padding:5px 10px;border-radius:999px;background:#fff;border:1px solid #f5d08a;color:#9a5b00;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.045em}
    .adm-pending-dots{display:inline-flex;gap:3px}
    .adm-pending-dots i{width:4px;height:4px;border-radius:50%;background:currentColor;animation:adm-pending-bounce 1.2s ease-in-out infinite}
    .adm-pending-dots i:nth-child(2){animation-delay:.15s}
    .adm-pending-dots i:nth-child(3){animation-delay:.3s}
    @keyframes adm-pending-shimmer{to{transform:translateX(100%)}}
    @keyframes adm-pending-ripple{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.75);opacity:0}}
    @keyframes adm-pending-flip{0%,40%{transform:rotate(0)}50%,100%{transform:rotate(180deg)}}
    @keyframes adm-pending-bounce{0%,80%,100%{transform:translateY(0);opacity:.45}40%{transform:translateY(-3px);opacity:1}}
    @media (prefers-reduced-motion: reduce){
        .adm-pending::before{display:none}
        .adm-pending-icon::before,.adm-pending-icon::after,.adm-pending-icon svg,.adm-pending-dots i{animation:none}
        .adm-pending-dots i{opacity:.7}
    }
</style>
@endonce

<div class="adm-pending" role="status">
    <div class="adm-pending-icon" aria-hidden="true">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2h12M6 22h12M7 2v4a5 5 0 0 0 5 5 5 5 0 0 0 5-5V2M7 22v-4a5 5 0 0 1 5-5 5 5 0 0 1 5 5v4"/>
        </svg>
    </div>
    <div class="adm-pending-title">{{ $title }}</div>
    <div class="adm-pending-message">{{ $message }}</div>
    <span class="adm-pending-badge">Awaiting {{ $role === 'bd' ? 'BD' : 'Designer' }}<span class="adm-pending-dots" aria-hidden="true"><i></i><i></i><i></i></span></span>
</div>
