import { reactive } from 'vue';
import type { InjectionKey } from 'vue';
import type { ConversationDraft } from '@/types/messaging';

// In-memory state belongs to the Messages page, never browser storage or another account.
export function createMessageDrafts() {
    const drafts = reactive<Record<number, ConversationDraft>>({});

    return {
        forConversation(id: number): ConversationDraft {
            drafts[id] ??= { body: '', photos: [], pending: [] };

            return drafts[id];
        },
    };
}

export const messageDraftsKey: InjectionKey<
    ReturnType<typeof createMessageDrafts>
> = Symbol('messageDrafts');
