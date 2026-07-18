<template>
    <li>
        <Link
            :href="link"
            class="flex w-full items-center gap-3 border-3 px-3 py-2.5 text-sm font-black transition focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#77c8b5]"
            :class="
                active
                    ? 'border-zinc-950 bg-[#e06573] text-white shadow-[3px_3px_0_#18181b]'
                    : 'border-transparent text-zinc-700 hover:border-zinc-950 hover:bg-[#fff0b8] hover:text-zinc-950'
            "
            :aria-current="active ? 'page' : undefined"
        >
            <span
                class="flex size-8 shrink-0 items-center justify-center border-2 border-zinc-950 text-sm text-zinc-950"
                :class="active ? 'bg-white' : 'bg-[#f7f1e7]'"
                aria-hidden="true"
            >
                <i :class="icon"></i>
            </span>
            <span>{{ label }}</span>
        </Link>
    </li>
</template>

<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

defineProps<{
    icon: string;
    label: string;
    link: LinkComponentBaseProps['href'];
    active?: boolean;
}>();
</script>
