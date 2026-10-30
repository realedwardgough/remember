/* Remember only handles visible notifications. It never caches pages, APIs or images. */
self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data?.json() ?? {}; } catch { /* Show a generic alert even for an invalid payload. */ }
    const url = typeof payload.url === 'string' && /^\/messages\/[1-9]\d*$/.test(payload.url)
        ? payload.url : '/profile#notifications';
    const test = payload.tag === 'remember-test';
    event.waitUntil(self.registration.showNotification('Remember', {
        body: test ? 'Notifications are working on this device.' : 'You have a new message in Remember.',
        icon: '/apple-touch-icon.png',
        tag: url.startsWith('/messages/') ? `remember-conversation-${url.split('/').pop()}` : 'remember-test',
        data: { url },
    }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const path = event.notification.data?.url;
    const url = typeof path === 'string' && (/^\/messages\/[1-9]\d*$/.test(path) || path === '/profile#notifications')
        ? path : '/messages';
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find((client) => new URL(client.url).origin === self.location.origin);
        if (existing) {
            await existing.navigate(url);
            await existing.focus();
        } else {
            await self.clients.openWindow(url);
        }
    })());
});
self.addEventListener('message', (event) => {
    if (event.data === 'remember-logout') {
        event.waitUntil((async () => {
            await Promise.allSettled([
                self.registration.getNotifications().then((notifications) => notifications.forEach((notification) => notification.close())),
                self.registration.pushManager.getSubscription().then((subscription) => subscription?.unsubscribe()),
            ]);
        })());
    }
});
