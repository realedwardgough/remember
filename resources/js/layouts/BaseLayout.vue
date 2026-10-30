<template>
    <main class="min-h-dvh bg-canvas text-stone-800">
        <div
            v-if="$slots.sidebar"
            class="lg:grid lg:min-h-dvh lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start"
        >
            <slot name="sidebar" />
            <div class="min-w-0">
                <div
                    class="mx-auto w-full max-w-5xl px-4 pt-5 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-12 lg:py-10"
                >
                    <slot />
                </div>
            </div>
        </div>
        <template v-else>
            <header
                class="flex items-center justify-between border-b bg-white px-5 py-3"
            >
                <Link :href="home()" class="story-font text-2xl"
                    >Remember <span class="text-primary">♡</span></Link
                >
                <Link
                    v-if="page.props.auth?.user"
                    :href="profileEdit()"
                    class="button-secondary"
                    :aria-label="`Open ${userInitials} profile`"
                    >{{ userInitials }}</Link
                >
            </header>
            <div class="mx-auto w-full max-w-175 px-5 py-8"><slot /></div>
        </template>
        <ToastNotifications />
    </main>
</template>
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
import { connectMessaging } from '@/lib/messaging-live';
import { home } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { PageProps } from '@/types';
const page = usePage<PageProps>();
let disconnectMessaging: (() => void) | undefined;
onMounted(() => {
    if (page.props.auth.user) {
        disconnectMessaging = connectMessaging(
            page.props.auth.user.id,
            page.props.messagingRealtime,
            (count) => {
                page.props.unreadMessagesCount = count;
            },
        );
    }
});
onBeforeUnmount(() => disconnectMessaging?.());
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
