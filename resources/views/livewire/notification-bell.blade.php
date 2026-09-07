<div class="notif-bell-wrap" x-data="{ open: false, permission: (window.Notification ? Notification.permission : 'unsupported') }" @click.outside="open = false" wire:poll.20s="$refresh">
    <button type="button" class="notif-bell-btn" @click="open = !open" aria-label="Notifications">
        🔔
        @if($unreadCount > 0)
            <span class="notif-badge">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    <div class="notif-dropdown" x-show="open" x-cloak x-transition.opacity.duration.120ms>
        <div class="notif-dropdown-head">
            <span class="notif-dropdown-title">Notifications</span>
            <div style="display:flex;align-items:center;gap:12px">
                <button type="button" class="notif-mark-all" x-show="permission === 'default'" @click="Notification.requestPermission().then(p => permission = p)">Enable alerts</button>
                <button type="button" class="notif-mark-all" wire:click="markAllAsRead" @disabled($unreadCount === 0)>Mark all as read</button>
            </div>
        </div>

        <div class="notif-list">
            @forelse($notifications as $n)
                <div class="notif-item {{ $n['read'] ? '' : 'is-unread' }}" wire:key="notif-{{ $n['id'] }}">
                    <button type="button" class="notif-item-open" wire:click="openNotification('{{ $n['id'] }}')">
                        <div class="notif-item-top">
                            @if(! $n['read'])<span class="notif-dot"></span>@endif
                            <span class="notif-item-title">{{ $n['title'] }}</span>
                        </div>
                        @if($n['message'])
                            <div class="notif-item-message">{{ $n['message'] }}</div>
                        @endif
                        <div class="notif-item-meta">
                            @if($n['task_ref'])<span>{{ $n['task_ref'] }}</span>@endif
                            <span>{{ $n['created_at'] }}</span>
                        </div>
                    </button>
                    @if(! $n['read'])
                        <button type="button" class="notif-item-read-btn" wire:click="markAsRead('{{ $n['id'] }}')" title="Mark as read">✓</button>
                    @endif
                </div>
            @empty
                <div class="notif-empty">No new notifications</div>
            @endforelse
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    document.addEventListener('livewire:init', () => {
        let audioCtx = null;
        const getAudioCtx = () => {
            if (!audioCtx) {
                try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; }
            }
            return audioCtx;
        };
        document.addEventListener('click', () => {
            const ctx = getAudioCtx();
            if (ctx && ctx.state === 'suspended') { ctx.resume().catch(() => {}); }
        }, { passive: true });

        const playBeep = () => {
            try {
                const ctx = getAudioCtx();
                if (!ctx || ctx.state === 'suspended') return;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.28);
                osc.connect(gain).connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.3);
            } catch (e) { /* autoplay blocked or unsupported: fail silently */ }
        };

        const seenKey = 'adinn_notif_seen_ids';
        const getSeen = () => {
            try { return new Set(JSON.parse(localStorage.getItem(seenKey) || '[]')); } catch (e) { return new Set(); }
        };
        const saveSeen = (set) => {
            try { localStorage.setItem(seenKey, JSON.stringify(Array.from(set).slice(-200))); } catch (e) { /* storage unavailable */ }
        };

        let seen = getSeen();
        let initialized = false;

        Livewire.on('notifications-updated', (data) => {
            const list = (data && data.items) || [];
            const newOnes = list.filter(n => !seen.has(n.id));

            if (!initialized) {
                // First tick after a page load only records the current unread
                // set as "known" — it must never replay existing history as if
                // it just arrived.
                list.forEach(n => seen.add(n.id));
                saveSeen(seen);
                initialized = true;
                return;
            }

            if (newOnes.length === 0) return;

            newOnes.forEach(n => seen.add(n.id));
            saveSeen(seen);

            playBeep();

            if (window.Notification && Notification.permission === 'granted') {
                newOnes.slice(0, 3).forEach(n => {
                    try {
                        const browserNotif = new Notification(n.title, {
                            body: (n.message || '').split('\n')[0],
                            tag: 'adinn-notif-' + n.id,
                        });
                        browserNotif.onclick = () => {
                            window.focus();
                            if (n.url) { window.location.href = n.url; }
                        };
                    } catch (e) { /* fail silently */ }
                });
            }
        });

        Livewire.on('notification-open-url', (data) => {
            if (data && data.url) { window.location.href = data.url; }
        });
    });
    </script>
    @endpush
@endonce
