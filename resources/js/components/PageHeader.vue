<template>
    <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
    >
        <div class="min-w-0">
            <p v-if="eyebrow" class="mb-2 text-xs font-medium text-primary">
                {{ eyebrow }}
            </p>
            <h1 class="page-heading break-words">{{ title }}</h1>
            <p
                v-if="description"
                class="mt-3 max-w-xl text-sm leading-6 text-stone-500"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 items-center gap-3">
            <slot />
        </div>
    </header>
</template>
<script setup lang="ts">
defineProps<{ title: string; eyebrow?: string; description?: string }>();
</script>
