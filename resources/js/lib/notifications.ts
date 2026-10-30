import { messagingRequest } from '@/lib/messaging';

export type NotificationSettings = {
    available: boolean;
    public_key: string | null;
    message_alerts: boolean;
    device_enabled: boolean;
};

export const notificationSettings = () =>
    messagingRequest<NotificationSettings>('/api/notifications');

export function pushSupported(): boolean {
    return (
        window.isSecureContext &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function preparePush(): Promise<ServiceWorkerRegistration> {
    await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
        updateViaCache: 'none',
    });

    return navigator.serviceWorker.ready;
}

export async function currentSubscription(): Promise<PushSubscription | null> {
    const registration = await navigator.serviceWorker.getRegistration('/');

    return (await registration?.pushManager.getSubscription()) ?? null;
}

export function applicationServerKey(
    publicKey: string,
): Uint8Array<ArrayBuffer> {
    const key = atob(
        publicKey
            .replace(/-/g, '+')
            .replace(/_/g, '/')
            .padEnd(Math.ceil(publicKey.length / 4) * 4, '='),
    );

    return Uint8Array.from(key, (character) => character.charCodeAt(0));
}

export async function clearBrowserPush(): Promise<void> {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const registration = await navigator.serviceWorker.getRegistration('/');
    registration?.active?.postMessage('remember-logout');
    const notifications = await registration
        ?.getNotifications()
        .catch(() => []);
    notifications?.forEach((notification) => notification.close());
    await (await registration?.pushManager.getSubscription())?.unsubscribe();
}

// Never silently attach a browser's previous subscription to a newly signed-in account.
export async function reconcilePushDevice(): Promise<void> {
    if (!pushSupported() || !(await currentSubscription())) {
        return;
    }

    const settings = await notificationSettings();

    if (!settings.device_enabled) {
        await clearBrowserPush();
    }
}
