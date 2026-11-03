{{-- Browser desktop notifications (Web Push) for Designer / BD.
     Permission can only be asked from a click, so a small card offers it while
     the browser has not decided yet; once allowed, this browser is (re)saved
     for the logged-in user on every page load. --}}
@php
    $pushService = app(\App\Services\WebPushService::class);
@endphp
@if($pushService->isConfigured() && $pushService->isEnabledFor(auth()->user()))
<div id="pushPrompt" style="display:none;position:fixed;right:18px;bottom:18px;z-index:80;max-width:320px;background:#fff;border:1px solid #e4e7ec;border-radius:12px;box-shadow:0 12px 28px rgba(16,24,40,.16);padding:14px 16px;font-size:12px;color:#344054">
    <div style="font-weight:800;font-size:13px;margin-bottom:4px">🔔 Turn on desktop notifications</div>
    <div style="color:#667085;line-height:1.5">
        @if(auth()->user()->role === 'bd')
            Get an alert on this PC when a designer submits a task for your review.
        @elseif(in_array(auth()->user()->role, ['admin', 'designer_head'], true))
            Get task alerts from the Design Task Manager on this PC.
        @else
            Get an alert on this PC for new tasks, rework, approvals and overdue reminders.
        @endif
    </div>
    <div style="display:flex;gap:8px;margin-top:10px">
        <button type="button" id="pushPromptEnable" class="btn btn-primary" style="padding:6px 12px;font-size:11px">Turn on</button>
        <button type="button" id="pushPromptLater" class="btn" style="padding:6px 12px;font-size:11px">Later</button>
    </div>
    <div id="pushPromptStatus" style="margin-top:8px;font-size:10px;color:#b42318;display:none"></div>
</div>
<script>
(function () {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;

    var cfg = {
        publicKey: @json(config('webpush.public_key')),
        sw: @json(asset('push-sw.js')),
        subscribe: @json(route('push.subscribe')),
        unsubscribe: @json(route('push.unsubscribe')),
        test: @json(route('push.test')),
    };
    var prompt = document.getElementById('pushPrompt');
    var statusEl = document.getElementById('pushPromptStatus');

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function post(url, body, keepalive) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: !!keepalive,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body || {}),
        });
    }

    function urlBase64ToUint8Array(base64) {
        var padding = '='.repeat((4 - base64.length % 4) % 4);
        var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function save(subscription) {
        var json = subscription.toJSON();
        json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm']).indexOf('aes128gcm') !== -1 ? 'aes128gcm' : 'aesgcm';
        return post(cfg.subscribe, json);
    }

    function subscribe() {
        return navigator.serviceWorker.register(cfg.sw).then(function () {
            return navigator.serviceWorker.ready;
        }).then(function (registration) {
            return registration.pushManager.getSubscription().then(function (existing) {
                return existing || registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(cfg.publicKey),
                });
            });
        }).then(save);
    }

    function dismissed() {
        try { return sessionStorage.getItem('pushPromptLater') === '1'; } catch (e) { return false; }
    }

    // Used by the My Profile "Web Push Notifications" toggle.
    window.AdinnPush = { subscribe: subscribe, test: function () { return post(cfg.test); } };

    if (Notification.permission === 'granted') {
        subscribe().catch(function () {});
    } else if (Notification.permission === 'default' && !dismissed() && @json($pushService->isOnFor(auth()->user()))) {
        prompt.style.display = 'block';
    }

    document.getElementById('pushPromptLater').addEventListener('click', function () {
        try { sessionStorage.setItem('pushPromptLater', '1'); } catch (e) {}
        prompt.style.display = 'none';
    });

    document.getElementById('pushPromptEnable').addEventListener('click', function () {
        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                prompt.style.display = 'none';
                return;
            }
            return subscribe().then(function () {
                prompt.style.display = 'none';
                return post(cfg.test);
            });
        }).catch(function () {
            statusEl.textContent = 'Could not turn on notifications in this browser. Please try again.';
            statusEl.style.display = 'block';
        });
    });

    navigator.serviceWorker.addEventListener('message', function (event) {
        if (!event.data) return;
        if (event.data.type === 'push-resubscribed') post(cfg.subscribe, event.data.subscription);

        // A desktop notification was clicked for the page already open here:
        // refresh it so the new data shows — unless something typed or chosen
        // on it would be lost.
        if (event.data.type === 'push-open') {
            var dirty = Array.prototype.some.call(document.querySelectorAll('textarea, input[type=text], input[type=file]'), function (el) {
                return el.type === 'file' ? el.files && el.files.length > 0 : (el.value || '').trim() !== '';
            });
            if (dirty) return;

            var target = new URL(event.data.url, window.location.href);
            if (target.search === window.location.search) {
                window.location.reload();
            } else {
                window.location.href = target.href;
            }
        }
    });

    // On logout, stop this browser getting the user's alerts (the next person
    // to log in here re-saves it for themselves).
    document.querySelectorAll('form[action="{{ route('logout') }}"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.pushCleared) return;
            event.preventDefault();
            form.dataset.pushCleared = '1';
            var submitted = false;
            var done = function () { if (!submitted) { submitted = true; form.submit(); } };
            setTimeout(done, 1500);
            navigator.serviceWorker.getRegistration(cfg.sw).then(function (registration) {
                return registration && registration.pushManager.getSubscription();
            }).then(function (subscription) {
                return subscription ? post(cfg.unsubscribe, { endpoint: subscription.endpoint }, true) : null;
            }).then(done, done);
        });
    });
})();
</script>
@endif
