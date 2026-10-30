import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(
    new URL('../../public/sw.js', import.meta.url),
    'utf8',
);
function worker(windows = [], failNotificationListing = false) {
    const handlers = new Map();
    const shown = [];
    const opened = [];
    let closed = 0;
    let unsubscribed = 0;
    const registration = {
        showNotification: async (title, options) =>
            shown.push({ title, ...options }),
        getNotifications: async () => {
            if (failNotificationListing) {
                throw new Error('Notification access unavailable');
            }

            return shown.map(() => ({
                close: () => {
                    closed++;
                },
            }));
        },
        pushManager: {
            getSubscription: async () => ({
                unsubscribe: async () => {
                    unsubscribed++;
                },
            }),
        },
    };
    vm.runInNewContext(source, {
        URL,
        self: {
            addEventListener: (name, callback) => handlers.set(name, callback),
            registration,
            location: { origin: 'https://remember.test' },
            clients: {
                matchAll: async () => windows,
                openWindow: async (url) => opened.push(url),
            },
        },
    });

    return {
        shown,
        opened,
        handlers,
        closed: () => closed,
        unsubscribed: () => unsubscribed,
        async event(name, input) {
            let work;
            handlers.get(name)({
                ...input,
                waitUntil: (promise) => {
                    work = promise;
                },
            });
            assert.ok(
                work instanceof Promise || typeof work?.then === 'function',
                'Keep the service worker alive until work completes',
            );
            await work;
        },
    };
}

test('push always displays a generic visible notification and ignores private previews', async () => {
    const app = worker();
    await app.event('push', {
        data: {
            json: () => ({
                title: 'Private sender',
                body: 'Private content',
                url: '/messages/123',
            }),
        },
    });
    assert.equal(app.shown.length, 1);
    assert.equal(app.shown[0].title, 'Remember');
    assert.equal(app.shown[0].body, 'You have a new message in Remember.');
    assert.equal(app.shown[0].data.url, '/messages/123');
    assert.equal(app.shown[0].tag, 'remember-conversation-123');
    assert.ok(!JSON.stringify(app.shown).includes('Private'));
    assert.ok(!app.handlers.has('fetch'), 'Never cache private pages or APIs');
});

test('empty and malformed pushes still display a visible generic notification', async () => {
    for (const data of [
        null,
        {
            json: () => {
                throw new Error('Bad payload');
            },
        },
    ]) {
        const app = worker();
        await app.event('push', { data });
        assert.equal(app.shown[0].body, 'You have a new message in Remember.');
    }
});

test('push links cannot leave the app or target arbitrary authenticated pages', async () => {
    for (const url of [
        'https://evil.test/',
        '//evil.test/',
        '/timeline/manage',
        '/messages/../profile',
        '/messages/1?token=private',
    ]) {
        const app = worker();
        await app.event('push', { data: { json: () => ({ url }) } });
        assert.equal(app.shown[0].data.url, '/profile#notifications');
    }
});

test('test notifications use generic confirmation', async () => {
    const app = worker();
    await app.event('push', {
        data: {
            json: () => ({
                tag: 'remember-test',
                url: '/profile#notifications',
            }),
        },
    });
    assert.equal(
        app.shown[0].body,
        'Notifications are working on this device.',
    );
});

test('click focuses an existing same-origin client and opens the authorized conversation route', async () => {
    const calls = [];
    const app = worker([
        {
            url: 'https://elsewhere.test/',
            navigate: () => assert.fail('Must not navigate a foreign client'),
        },
        {
            url: 'https://remember.test/profile',
            navigate: async (url) => calls.push(['navigate', url]),
            focus: async () => calls.push(['focus']),
        },
    ]);
    await app.event('notificationclick', {
        notification: {
            close: () => calls.push(['close']),
            data: { url: '/messages/42' },
        },
    });
    assert.deepEqual(calls, [
        ['close'],
        ['navigate', '/messages/42'],
        ['focus'],
    ]);
    assert.equal(app.opened.length, 0);
});

test('click opens a window when the app is closed and sanitizes forged targets', async () => {
    const app = worker();
    await app.event('notificationclick', {
        notification: { close() {}, data: { url: '//evil.test/path' } },
    });
    assert.deepEqual(app.opened, ['/messages']);
});

test('logout closes existing notifications and unsubscribes the device', async () => {
    const app = worker();
    await app.event('push', { data: { json: () => ({ url: '/messages/1' }) } });
    await app.event('message', { data: 'remember-logout' });
    assert.equal(app.closed(), 1);
    assert.equal(app.unsubscribed(), 1);
});

test('logout still unsubscribes if native notification listing fails', async () => {
    const app = worker([], true);
    await app.event('message', { data: 'remember-logout' });
    assert.equal(app.unsubscribed(), 1);
});
