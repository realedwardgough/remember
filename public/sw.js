/* Notifications only: this worker never caches private pages, APIs or images. */
self.addEventListener('install', (event) => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

function destination(value) {
    return typeof value === 'string' && (/^\/(?:messages|posts)\/[1-9]\d*$/.test(value) || value === '/profile#notifications')
        ? value : null;
}
function preview(value, limit) {
    return typeof value === 'string'
        ? Array.from(value.replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/g, '').trim()).slice(0, limit).join('') : '';
}
self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data?.json() ?? {}; } catch { /* Empty/malformed pushes still get a visible alert. */ }
    const target = destination(payload.url);
    const url = target ?? '/profile#notifications';
    const message = target?.startsWith('/messages/');
    const post = target?.startsWith('/posts/');
    const test = !message && !post && payload.tag === 'remember-test';
    const title = message || post ? preview(payload.title, 120) || 'Remember' : 'Remember';
    const body = test ? 'Notifications are working on this device.'
        : message || post ? preview(payload.body, 240) || (post ? 'There is a new post in Remember.' : 'You have a new message in Remember.')
        : 'You have a new message in Remember.';
    event.waitUntil(self.registration.showNotification(title, {
        body,
        icon: '/apple-touch-icon.png',
        tag: message ? `remember-conversation-${url.split('/').pop()}` : post ? `remember-post-${url.split('/').pop()}` : 'remember-test',
        data: { url },
    }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = destination(event.notification.data?.url) ?? '/messages';
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
