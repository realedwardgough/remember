<template>
    <main class="min-h-dvh bg-canvas text-stone-800">
        <header
            v-if="page.props.auth?.user && !hideMobileHeader"
            class="sticky top-0 z-30 flex items-center justify-between border-b bg-white/95 px-4 pt-[env(safe-area-inset-top)] backdrop-blur-lg lg:hidden"
        >
            <Link :href="home()" class="story-font text-2xl"
                >Remember <span class="text-rose-500">♡</span></Link
            >
            <NotificationBell />
        </header>
        <div
            v-if="$slots.sidebar"
            class="lg:grid lg:min-h-dvh lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start"
        >
            <slot name="sidebar" />
            <div class="min-w-0">
                <div
                    class="mx-auto w-full max-w-5xl px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-[calc(5.5rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-12 lg:py-10"
                >
                    <slot />
                </div>
            </div>
        </div>
        <template v-else>
            <header
                class="hidden items-center justify-between border-b bg-white px-5 py-3 lg:flex"
            >
                <Link :href="home()" class="story-font text-2xl"
                    >Remember <span class="text-primary">♡</span></Link
                >
                <div
                    v-if="page.props.auth?.user"
                    class="flex items-center gap-3"
                >
                    <NotificationBell />
                    <Link
                        :href="profileEdit()"
                        class="button-secondary"
                        :aria-label="`Open ${userInitials} profile`"
                        >{{ userInitials }}</Link
                    >
                </div>
            </header>
            <div class="mx-auto w-full max-w-175 px-4 py-3 lg:px-5 lg:py-8">
                <slot />
            </div>
        </template>
        <ToastNotifications />
    </main>
</template>
<script setup lang="ts">
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import NotificationBell from '@/components/NotificationBell.vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
import { connectMessaging } from '@/lib/messaging-live';
import { reconcilePushDevice } from '@/lib/notifications';
import { home } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { PageProps } from '@/types';
defineProps<{ hideMobileHeader?: boolean }>();
const page = usePage<PageProps>();
const notificationProps = ['unreadNotificationsCount', 'activityNotifications'];
const { start: startNotificationPolling } = usePoll(
    30_000,
    { only: notificationProps },
    { autoStart: false },
);
const refreshNotifications = () => {
    if (page.props.auth?.user && document.visibilityState === 'visible') {
        router.reload({ only: notificationProps });
    }
};
let disconnectMessaging: (() => void) | undefined;
onMounted(() => {
    if (page.props.auth.user) {
        startNotificationPolling();
        window.addEventListener('focus', refreshNotifications);
        document.addEventListener('visibilitychange', refreshNotifications);
        void reconcilePushDevice().catch(() => {});
        disconnectMessaging = connectMessaging(
            page.props.auth.user.id,
            page.props.messagingRealtime,
            (count) => {
                page.props.unreadMessagesCount = count;
            },
        );
    }
});
onBeforeUnmount(() => {
    disconnectMessaging?.();
    window.removeEventListener('focus', refreshNotifications);
    document.removeEventListener('visibilitychange', refreshNotifications);
});
const userInitials = computed(() =>
    (page.props.auth.user?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);
</script>
