/*
 * Service worker for desktop notifications (Web Push). Registered from
 * layouts/app.blade.php; its scope is the folder it is served from, so it
 * works both at the site root (local) and under /design-workflow/ (live).
 */
self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'Design Task Manager', {
        body: data.body || '',
        icon: data.icon || undefined,
        badge: data.icon || undefined,
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || self.registration.scope },
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || self.registration.scope;
    var target = new URL(url, self.location.href);

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        for (var i = 0; i < windows.length; i++) {
            var open = new URL(windows[i].url);
            // Same task page already open (any tab): bring it forward and ask it to
            // refresh — the page skips the reload if it holds unsaved input.
            if (open.origin === target.origin && open.pathname === target.pathname && 'focus' in windows[i]) {
                windows[i].postMessage({ type: 'push-open', url: target.href });
                return windows[i].focus();
            }
        }
        // A new tab rather than navigating an open one, which may hold unsaved work.
        return self.clients.openWindow(url);
    }));
});

// A browser can rotate its subscription; re-register it with the same keys.
self.addEventListener('pushsubscriptionchange', function (event) {
    var options = event.oldSubscription && event.oldSubscription.options;
    if (!options) return;
    event.waitUntil(self.registration.pushManager.subscribe(options).then(function (subscription) {
        return self.clients.matchAll({ type: 'window' }).then(function (windows) {
            windows.forEach(function (w) { w.postMessage({ type: 'push-resubscribed', subscription: subscription.toJSON() }); });
        });
    }));
});
