import type { DirectConversation, DirectMessage } from '@/types/messaging';

export function conversationName(conversation: DirectConversation): string {
    return conversation.is_group
        ? conversation.name || 'Group conversation'
        : conversation.participants.find((person) => !person.is_self)?.name ||
              'Conversation';
}

export function messageReceipt(
    message: DirectMessage,
    conversation: DirectConversation | null,
): string {
    if (!conversation?.is_group) {
        return message.id <= (conversation?.recipient_last_read_message_id ?? 0)
            ? 'Seen'
            : 'Sent';
    }

    const recipients = conversation.read_receipts;
    const seen = recipients.filter(
        (receipt) => message.id <= (receipt.last_read_message_id ?? 0),
    ).length;

    return seen ? `Seen by ${seen} of ${recipients.length}` : 'Sent';
}

export function typingLabel(names: string[]): string {
    if (!names.length) {
        return '';
    }

    if (names.length === 1) {
        return `${names[0]} is typing…`;
    }

    return names.length === 2
        ? `${names[0]} and ${names[1]} are typing…`
        : `${names[0]} and ${names.length - 1} others are typing…`;
}
