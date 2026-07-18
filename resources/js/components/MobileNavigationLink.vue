<template>
    <Link
        v-if="resolvedLink"
        :href="resolvedLink"
        :method="post ? 'post' : undefined"
        as="button"
        class="group m-auto inline-flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 px-1 font-bold text-zinc-700"
        :class="
            cta
                ? 'h-15 w-15 -translate-y-2 border-3 border-zinc-950 bg-[#e06573] text-white shadow-[4px_4px_0_0_#18181b]'
                : 'w-full'
        "
    >
        <i :class="cta ? icon + ' text-2xl' : icon + ' text-sm'"></i>
        <span
            v-if="!cta"
            class="text-body group-hover:text-fg-brand max-w-full truncate text-xs"
        >
            {{ label }}
        </span>
    </Link>

    <button
        v-else
        type="button"
        class="group m-auto inline-flex min-h-12 min-w-0 flex-col items-center justify-center gap-1 px-1 font-bold text-zinc-700"
        :class="
            cta
                ? 'h-15 w-15 -translate-y-2 border-3 border-zinc-950 bg-[#e06573] text-white shadow-[4px_4px_0_0_#18181b]'
                : 'w-full'
        "
        @click="emit('activate')"
    >
        <i :class="cta ? icon + ' text-2xl' : icon + ' text-sm'"></i>
        <span
            v-if="!cta"
            class="text-body group-hover:text-fg-brand max-w-full truncate text-xs"
        >
            {{ label }}
        </span>
    </button>
</template>

<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        icon: string;
        label: string;
        cta?: boolean;
        link?: LinkComponentBaseProps['href'];
        href?: LinkComponentBaseProps['href'];
        post?: boolean;
    }>(),
    {
        cta: false,
        post: false,
    },
);

const emit = defineEmits<{
    activate: [];
}>();

const resolvedLink = computed(() => props.link ?? props.href);
</script>
