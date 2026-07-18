<template>
    <main class="min-h-dvh bg-[#fff9e8] text-zinc-950">
        <div
            v-if="$slots.sidebar"
            class="lg:grid lg:min-h-dvh lg:grid-cols-[19rem_minmax(0,1fr)] lg:items-start"
        >
            <slot name="sidebar" />

            <div class="min-w-0 bg-[#fff9e8]">
                <div
                    class="mx-auto w-full max-w-5xl px-5 pt-8 pb-[calc(2rem+env(safe-area-inset-bottom))] sm:px-8 lg:px-10 lg:py-10"
                >
                    <slot />
                </div>
            </div>
        </div>

        <template v-else>
            <header
                class="flex items-center justify-end gap-3 border-b-3 border-zinc-950 bg-white p-4"
            >
                <Link
                    :href="profileEdit()"
                    class="flex size-10 items-center justify-center rounded-full bg-[#e06573] text-sm font-bold text-white shadow-sm hover:bg-slate-800"
                    :aria-label="`Open ${userInitials} profile`"
                >
                    {{ userInitials }}
                </Link>
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-100"
                >
                    Log out
                </Link>
            </header>

            <div class="mx-auto w-full max-w-175 px-6 py-8">
                <slot />
            </div>
        </template>
    </main>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? '';

    return name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});
</script>
