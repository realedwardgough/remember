<template>
    <component
        :is="resolvedLink ? Link : 'button'"
        :href="resolvedLink"
        :type="resolvedLink ? undefined : 'button'"
        :aria-label="label"
        :aria-current="active ? 'page' : undefined"
        class="flex min-h-14 w-full flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-medium"
        :class="active ? 'text-primary' : 'text-stone-500'"
        @click="emit('activate')"
    >
        <span
            class="flex h-8 w-12 items-center justify-center rounded-xl"
            :class="cta ? 'bg-primary text-white' : active ? 'bg-lilac' : ''"
            ><AppIcon :name="icon" class="text-lg" aria-hidden="true"
        /></span>
        <span>{{ label }}</span>
    </component>
</template>
<script setup lang="ts">
import type { LinkComponentBaseProps } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
const props = defineProps<{
    icon: string;
    label: string;
    cta?: boolean;
    active?: boolean;
    link?: LinkComponentBaseProps['href'];
    href?: LinkComponentBaseProps['href'];
}>();
const emit = defineEmits<{ activate: [] }>();
const resolvedLink = computed(() => props.link ?? props.href);
</script>
