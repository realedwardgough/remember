<template>
    <li>
        <Link
            :href="link"
            class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors"
            :class="
                active
                    ? 'bg-lilac text-primary'
                    : 'text-stone-600 hover:bg-canvas'
            "
            :aria-current="active ? 'page' : undefined"
            ><AppIcon
                :name="icon"
                class="w-5 text-center text-lg"
                aria-hidden="true"
            />{{ label }}</Link
        >
    </li>
</template>
<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/components/AppIcon.vue';
defineProps<{
    icon: string;
    label: string;
    link: LinkComponentBaseProps['href'];
    active?: boolean;
}>();
</script>
