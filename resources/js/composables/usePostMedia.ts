import { onBeforeUnmount, ref } from 'vue';
import type { GalleryImage, GalleryVideo, TimelineMedia } from '@/types';

export function usePostMedia<T extends GalleryImage | GalleryVideo>(
    type: 'image' | 'video',
) {
    const media = ref<{ postId: number; items: T[] } | null>(null);
    const error = ref('');
    let controller: AbortController | undefined;
    let generation = 0;

    const load = async (selected: T) => {
        if (media.value?.postId === selected.postId) {
            return;
        }

        controller?.abort();
        controller = new AbortController();
        const current = ++generation;
        error.value = '';

        try {
            const response = await fetch(
                `/api/posts/${selected.postId}/media?type=${type}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            );

            if (!response.ok) {
                throw new Error('Media unavailable');
            }

            const result: { data: TimelineMedia[] } = await response.json();

            if (current === generation) {
                media.value = {
                    postId: selected.postId,
                    items: result.data.map(
                        (item) => ({ ...selected, ...item }) as T,
                    ),
                };
            }
        } catch {
            if (current === generation && !controller.signal.aborted) {
                error.value =
                    'Other attachments could not be loaded. Close and reopen to retry.';
            }
        }
    };

    onBeforeUnmount(() => {
        generation++;
        controller?.abort();
    });

    return { media, error, load };
}
