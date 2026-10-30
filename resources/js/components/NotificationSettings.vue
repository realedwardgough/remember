<template>
    <section
        id="notifications"
        class="surface scroll-mt-6 p-5 sm:p-6"
        aria-labelledby="notification-heading"
    >
        <h2 id="notification-heading" class="text-xl font-semibold">
            Notifications
        </h2>
        <p class="mt-2 text-sm text-stone-600">
            Get a private heads-up when someone messages you. Alerts say “You
            have a new message in Remember.” Names, message text and photos stay
            inside the app.
        </p>
        <p v-if="loading" class="mt-4" role="status">
            Checking notification settings…
        </p>
        <template v-if="settings">
            <div class="mt-5 border-b pb-5">
                <label class="flex min-h-12 items-center gap-3 font-semibold">
                    <input
                        type="checkbox"
                        class="size-5 accent-primary"
                        :checked="settings.message_alerts"
                        :disabled="busy"
                        @change="
                            setPreference(
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    Message alerts for my account
                </label>
                <p class="mt-1 text-sm text-stone-600">
                    {{
                        settings.message_alerts
                            ? 'On — alerts can reach devices you have enabled.'
                            : 'Off — new-message alerts are paused on all your devices.'
                    }}
                    Enabling a device is a separate step below.
                </p>
            </div>
            <div class="mt-5 grid gap-3">
                <h3 class="font-semibold">This device</h3>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-stone-600">Permission</dt>
                    <dd>{{ permissionLabel }}</dd>
                    <dt class="text-stone-600">Device alerts</dt>
                    <dd>
                        {{
                            deviceEnabled
                                ? 'Enabled for this account'
                                : 'Not enabled for this account'
                        }}
                    </dd>
                </dl>
                <p
                    v-if="!settings.available"
                    class="rounded-xl bg-butter p-3 text-sm"
                >
                    Notifications are awaiting setup by the person running
                    Remember. You can save your account preference now. Messages
                    continue to work.
                </p>
                <p
                    v-else-if="ios && !standalone"
                    class="rounded-xl bg-butter p-3 text-sm"
                >
                    Open Remember from its Home Screen icon to enable
                    notifications on this iPhone or iPad.
                </p>
                <p
                    v-else-if="!supported"
                    class="rounded-xl bg-butter p-3 text-sm"
                >
                    {{
                        secure
                            ? 'This browser does not provide Web Push here. Try an up-to-date Safari, Chrome or Firefox browser, then use the guidance below.'
                            : 'Notifications need a secure HTTPS connection. Open Remember using its secure address.'
                    }}
                </p>
                <p
                    v-else-if="permission === 'denied'"
                    class="rounded-xl bg-butter p-3 text-sm"
                >
                    Notifications are blocked. Allow them in your device or
                    browser settings using the guidance below, then return here
                    and recheck.
                </p>
                <p
                    v-if="deviceEnabled && !settings.message_alerts"
                    class="text-sm text-stone-600"
                >
                    This device is ready. Turn on message alerts above to
                    receive new-message notifications. You can still send a
                    test.
                </p>
                <div class="flex flex-wrap gap-3">
                    <button
                        v-if="!deviceEnabled"
                        type="button"
                        class="button-primary"
                        :disabled="busy || !canEnable"
                        @click="enable"
                    >
                        Enable on this device
                    </button>
                    <button
                        v-if="deviceEnabled || subscribed"
                        type="button"
                        class="button-secondary"
                        :disabled="busy"
                        @click="disable"
                    >
                        Disable on this device
                    </button>
                    <button
                        type="button"
                        class="button-secondary"
                        :disabled="
                            busy || !deviceEnabled || !settings.available
                        "
                        @click="sendTest"
                    >
                        Send test notification
                    </button>
                    <button
                        type="button"
                        class="button-secondary"
                        :disabled="busy"
                        @click="refresh"
                    >
                        Recheck settings
                    </button>
                </div>
            </div>
        </template>
        <p v-if="feedback" role="status" class="mt-4 text-sm text-stone-700">
            {{ feedback }}
        </p>
        <p
            v-if="error"
            role="alert"
            class="mt-4 text-sm font-semibold text-red-700"
        >
            {{ error }}
        </p>
        <button
            v-if="!settings && !loading"
            type="button"
            class="button-secondary mt-3"
            @click="refresh"
        >
            Try again
        </button>
        <details
            class="mt-5 border-t pt-4"
            :open="ios || android || permission === 'denied'"
        >
            <summary class="min-h-10 cursor-pointer font-semibold">
                Phone setup & troubleshooting
            </summary>
            <div class="mt-3 grid gap-4 text-sm leading-6 text-stone-600">
                <div v-if="ios || !android">
                    <h3 class="font-semibold text-stone-800">iPhone & iPad</h3>
                    <ol class="list-decimal space-y-1 pl-5">
                        <li>
                            Use iOS/iPadOS 16.4 or newer. Open Remember in
                            Safari, tap Share, then Add to Home Screen.
                        </li>
                        <li>
                            Open Remember from that Home Screen icon, sign in,
                            and return to Account → Notifications.
                        </li>
                        <li>
                            Tap Enable on this device, then Allow when asked.
                            Turn on message alerts for your account above.
                        </li>
                        <li>
                            If blocked, open device Settings → Notifications →
                            Remember and allow notifications. Menu locations can
                            vary by OS version.
                        </li>
                    </ol>
                    <p class="mt-2">
                        If alerts are enabled but silent, check Focus,
                        notification summaries, and Lock Screen/sound settings.
                        Return here, recheck, and send a test.
                    </p>
                </div>
                <div v-if="android || !ios">
                    <h3 class="font-semibold text-stone-800">Android</h3>
                    <p>
                        In Chrome, open ⋮ → Settings → Site settings →
                        Notifications. Allow Remember. You can also open
                        Remember, tap the site information icon beside its
                        address → Permissions → Notifications.
                    </p>
                    <p class="mt-2">
                        Check Android Settings → Notifications → App
                        notifications for Remember (if installed) or your
                        browser. Menu names vary by manufacturer and browser.
                        Installing a Home Screen app is optional in supported
                        Android browsers.
                    </p>
                    <p class="mt-2">
                        If alerts are silent, check Do Not Disturb and
                        battery/background restrictions for Remember or your
                        browser. Return here, recheck, and send a test.
                    </p>
                </div>
                <p>
                    On other devices, use this site’s notification permissions
                    in your browser settings. Remember cannot change phone
                    settings or detect every Focus or battery restriction.
                    Logging out disables this device for your account; other
                    enabled devices keep working.
                </p>
                <p>
                    <a
                        class="underline"
                        href="https://support.apple.com/guide/iphone/change-notification-settings-iph7c3d96bab/ios"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Apple notification help</a
                    >
                    ·
                    <a
                        class="underline"
                        href="https://support.google.com/chrome/answer/3220216?co=GENIE.Platform%3DAndroid&amp;hl=en"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Chrome notification help</a
                    >
                </p>
            </div>
        </details>
    </section>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { messageError, messagingRequest } from '@/lib/messaging';
import {
    applicationServerKey,
    clearBrowserPush,
    currentSubscription,
    notificationSettings,
    preparePush,
    pushSupported,
} from '@/lib/notifications';
import type { NotificationSettings } from '@/lib/notifications';

const settings = ref<NotificationSettings | null>(null);
const permission = ref<NotificationPermission | 'unknown'>('unknown');
const subscribed = ref(false);
const supported = ref(false);
const secure = ref(false);
const ios = ref(false);
const android = ref(false);
const standalone = ref(false);
const ready = ref(false);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const feedback = ref('');
let registration: ServiceWorkerRegistration | null = null;
let disposed = false;
const deviceEnabled = computed(
    () =>
        settings.value?.device_enabled &&
        subscribed.value &&
        permission.value === 'granted',
);
const canEnable = computed(
    () =>
        settings.value?.available &&
        supported.value &&
        ready.value &&
        permission.value !== 'denied' &&
        (!ios.value || standalone.value),
);
const permissionLabel = computed(() =>
    permission.value === 'granted'
        ? 'Allowed by browser'
        : permission.value === 'denied'
          ? 'Blocked'
          : permission.value === 'default'
            ? 'Not requested yet'
            : 'Unavailable or unknown',
);

async function refresh() {
    if (busy.value || disposed) {
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        settings.value = await notificationSettings();
        permission.value =
            'Notification' in window ? Notification.permission : 'unknown';
        supported.value = pushSupported();

        if (supported.value) {
            const subscription = await currentSubscription();

            if (subscription && !settings.value.device_enabled) {
                await clearBrowserPush();
            }

            subscribed.value =
                settings.value.device_enabled && subscription !== null;

            if (settings.value.available && (!ios.value || standalone.value)) {
                registration = await preparePush();
                ready.value = true;
            }
        }
    } catch (cause) {
        error.value = messageError(cause);
    } finally {
        loading.value = false;
    }
}
async function setPreference(enabled: boolean) {
    busy.value = true;
    error.value = '';
    feedback.value = '';

    try {
        settings.value = await messagingRequest<NotificationSettings>(
            '/api/notifications/preferences',
            { method: 'PUT', body: { message_alerts: enabled } },
        );
        feedback.value = enabled
            ? 'Message alerts turned on for your account.'
            : 'Message alerts paused on all your devices.';
    } catch (cause) {
        error.value = messageError(cause);
    } finally {
        busy.value = false;
    }
}
async function enable() {
    if (!canEnable.value || !registration || !settings.value?.public_key) {
        return;
    }

    // Call immediately inside the tap handler, before any asynchronous preparation.
    const permissionRequest = Notification.requestPermission();
    busy.value = true;
    error.value = '';
    feedback.value = '';

    try {
        permission.value = await permissionRequest;

        if (permission.value !== 'granted') {
            feedback.value =
                'Permission was not allowed. You can keep messaging and enable alerts later.';

            return;
        }

        const existing = await registration.pushManager.getSubscription();
        const subscription =
            existing ??
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey(
                    settings.value.public_key,
                ),
            }));
        settings.value = await messagingRequest<NotificationSettings>(
            '/api/notifications/subscription',
            { method: 'POST', body: subscription.toJSON() },
        );
        subscribed.value = true;
        feedback.value = settings.value.message_alerts
            ? 'This device is ready for message alerts. Send a test to check your phone settings.'
            : 'This device is ready. Turn on message alerts above, or send a test now.';
    } catch (cause) {
        error.value =
            cause instanceof DOMException
                ? 'Couldn’t enable notifications. Check the guidance below, then try again.'
                : messageError(cause);
    } finally {
        busy.value = false;
    }
}
async function disable() {
    busy.value = true;
    error.value = '';
    feedback.value = '';

    try {
        // Revoke server ownership first; a browser unsubscribe failure must not keep sending.
        settings.value = await messagingRequest<NotificationSettings>(
            '/api/notifications/subscription',
            { method: 'DELETE' },
        );
        subscribed.value = false;
        await clearBrowserPush();
        feedback.value =
            'Notifications disabled on this device. Your other devices and account preference are unchanged.';
    } catch (cause) {
        error.value = messageError(cause);
    } finally {
        busy.value = false;
    }
}
async function sendTest() {
    busy.value = true;
    error.value = '';
    feedback.value = '';

    try {
        await messagingRequest('/api/notifications/test', { method: 'POST' });
        feedback.value =
            'Test queued. Look for a Remember notification. If it doesn’t appear shortly, check the phone guidance below and try again.';
    } catch (cause) {
        error.value = messageError(cause);
    } finally {
        busy.value = false;
    }
}
function returning() {
    if (document.visibilityState === 'visible') {
        void refresh();
    }
}
onMounted(() => {
    secure.value = window.isSecureContext;
    ios.value =
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    android.value = /Android/.test(navigator.userAgent);
    standalone.value =
        matchMedia('(display-mode: standalone)').matches ||
        Boolean((navigator as Navigator & { standalone?: boolean }).standalone);
    void refresh();
    window.addEventListener('focus', returning);
    document.addEventListener('visibilitychange', returning);
});
onBeforeUnmount(() => {
    disposed = true;
    window.removeEventListener('focus', returning);
    document.removeEventListener('visibilitychange', returning);
});
</script>
