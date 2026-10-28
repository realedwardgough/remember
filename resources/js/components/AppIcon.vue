<template>
    <component
        :is="resolved"
        :size="size"
        aria-hidden="true"
        class="inline-block shrink-0 align-middle"
    />
</template>
<script setup lang="ts">
import {
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    Download,
    ExternalLink,
    Heart,
    House,
    Image,
    Images,
    KeyRound,
    Mail,
    MessageCircle,
    Plus,
    Search,
    Sparkles,
    Star,
    Trash2,
    TriangleAlert,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';
const props = withDefaults(defineProps<{ name: string; size?: number }>(), {
    size: 18,
});
const icons = {
    'fa-house': House,
    'fa-camera-retro': Image,
    'fa-image': Image,
    'fa-images': Images,
    'fa-calendar': CalendarDays,
    'fa-calendar-day': CalendarDays,
    'fa-star': Star,
    'fa-envelope': Mail,
    'fa-envelope-open-text': Mail,
    'fa-magnifying-glass': Search,
    'fa-plus': Plus,
    'fa-user': UserRound,
    'fa-address-card': UserRound,
    'fa-key': KeyRound,
    'fa-heart': Heart,
    'fa-comment': MessageCircle,
    'fa-trash': Trash2,
    'fa-download': Download,
    'fa-xmark': X,
    'fa-up-right-from-square': ExternalLink,
    'fa-arrow-right': ArrowRight,
    'fa-wand-magic-sparkles': Sparkles,
    'fa-circle-check': CheckCircle2,
    'fa-triangle-exclamation': TriangleAlert,
};
const resolved = computed(
    () =>
        icons[
            props.name
                .split(' ')
                .find((name) => name in icons) as keyof typeof icons
        ] ?? Image,
);
</script>
