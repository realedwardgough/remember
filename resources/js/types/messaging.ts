export type MessageAttachment = {
    id: number;
    width: number;
    height: number;
    status: 'available' | 'expired' | 'removed';
    expires_at: string;
    retention_hours: number;
    placeholder: string;
    url: string | null;
    thumbnail_url: string | null;
};
export type PendingPhoto = {
    file: File;
    requestId: string;
    uploaded?: MessageAttachment;
};

export type MessagingParticipant = {
    id: number;
    name: string;
    username: string | null;
    deleted: boolean;
    is_self: boolean;
};

export type DirectMessage = {
    id: number;
    conversation_id: number;
    sender: MessagingParticipant;
    body: string;
    attachments: MessageAttachment[];
    client_request_id: string | null;
    created_at: string;
};

export type DirectConversation = {
    id: number;
    participants: MessagingParticipant[];
    can_send: boolean;
    latest_message?: DirectMessage | null;
    unread_count?: number;
    updated_at: string;
};

export type MessageHistory = {
    conversation: DirectConversation;
    data: DirectMessage[];
    has_more: boolean;
    next_before_id: number | null;
    next_after_id: number | null;
    read_token: string | null;
};

export type MessagingContact = { id: number; name: string; username: string };
export type CursorPage<T> = { data: T[]; next_cursor: string | null };
export type PendingMessage = {
    requestId: string;
    body: string;
    photos: PendingPhoto[];
    status: 'sending' | 'failed';
    error?: string;
};
export type ConversationDraft = {
    body: string;
    photos: File[];
    pending: PendingMessage[];
};
