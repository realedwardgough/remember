<template>
    <header class="flex items-center justify-between gap-3 lg:gap-4">
        <div class="min-w-0">
            <p
                v-if="eyebrow"
                class="mb-2 hidden text-xs font-medium text-primary lg:block"
            >
                {{ eyebrow }}
            </p>
            <h1 class="page-heading break-words">{{ title }}</h1>
            <p
                v-if="description"
                class="mt-1 max-w-xl text-xs leading-5 break-words text-stone-500 lg:mt-3 lg:text-sm lg:leading-6"
            >
                {{ description }}
            </p>
        </div>
        <div
            v-if="$slots.default"
            class="flex shrink-0 items-center gap-2 lg:gap-3"
        >
            <slot />
        </div>
    </header>
</template>
<script setup lang="ts">
defineProps<{ title: string; eyebrow?: string; description?: string }>();
</script>

<style scoped>
@media (max-width: 1023px) {
    .page-heading {
        font-size: 1.25rem;
        line-height: 1.3;
    }
}
</style>
