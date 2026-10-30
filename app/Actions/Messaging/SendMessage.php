<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessagePostShare;
use App\Models\Post;
use App\Services\Messaging\MessageImageSettings;
use App\Models\MessagingParticipant;
use App\Models\User;
use App\Services\Messaging\MessagingUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SendMessage
{
    public function handle(User $actor, Conversation $conversation, string $body, string $clientRequestId, array $attachmentIds = [], ?int $postId = null): Message
    {
        Gate::forUser($actor)->authorize('view', $conversation);

        $body = trim($body);
        $clientRequestId = strtolower($clientRequestId);

        if (($body === '' && $attachmentIds === [] && $postId === null) || mb_strlen($body) > 10000) {
            throw ValidationException::withMessages(['body' => 'Enter a message of up to 10,000 characters.']);
        }

        $attachmentIds = array_map(intval(...), $attachmentIds);
        sort($attachmentIds);
        if (count($attachmentIds) > MessageImageSettings::MAX_ATTACHMENTS || count(array_unique($attachmentIds)) !== count($attachmentIds)) {
            throw ValidationException::withMessages(['attachment_ids' => 'Choose up to four different photos.']);
        }

        return DB::transaction(function () use ($actor, $conversation, $body, $clientRequestId, $attachmentIds, $postId): Message {
            $userIds = MessagingParticipant::query()
                ->whereIn('id', [$conversation->first_participant_id, $conversation->second_participant_id])
                ->whereNotNull('user_id')->pluck('user_id');

            // Match StartConversation's lock ordering before locking a conversation.
            User::query()->whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get();
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            Gate::forUser($actor)->authorize('view', $locked);
            $participant = $locked->participantFor($actor);

            $existing = Message::query()->where('sender_participant_id', $participant->id)
                ->where('client_request_id', $clientRequestId)->first();

            if ($existing !== null) {
                if ($existing->conversation_id !== $locked->id || $existing->body !== $body || $existing->attachments()->pluck('id')->all() !== $attachmentIds || $existing->sharedPost?->original_post_id !== $postId) {
                    throw ValidationException::withMessages(['client_request_id' => 'This request ID was already used for a different message.']);
                }

                return $existing;
            }

            Gate::forUser($actor)->authorize('send', $locked);

            $attachments = MessageAttachment::query()->whereIn('id', $attachmentIds)->orderBy('id')->lockForUpdate()->get();
            if ($attachments->count() !== count($attachmentIds) || $attachments->contains(fn (MessageAttachment $image): bool => $image->conversation_id !== $locked->id || $image->uploaded_by_participant_id !== $participant->id || $image->message_id !== null || ! $image->available())) {
                throw ValidationException::withMessages(['attachment_ids' => 'One of these photos is unavailable. Please choose it again.']);
            }

            $post = null;
            if ($postId !== null) {
                $post = Post::query()->lockForUpdate()->findOrFail($postId);
                Gate::forUser($actor)->authorize('share', $post);
                $recipientId = $userIds->first(fn (int $id): bool => $id !== $actor->id);
                Gate::forUser(User::query()->findOrFail($recipientId))->authorize('view', $post);
            }

            $message = Message::query()->create([
                'conversation_id' => $locked->id,
                'sender_participant_id' => $participant->id,
                'body' => $body,
                'client_request_id' => $clientRequestId,
            ]);

            if ($post !== null) {
                MessagePostShare::query()->create(['message_id' => $message->id, 'post_id' => $post->id, 'original_post_id' => $post->id]);
            }

            foreach ($attachments as $attachment) {
                $attachment->update(['message_id' => $message->id]);
            }

            app(\App\Services\Messaging\MessagePush::class)->prepare($message);
            $locked->touch();
            app(MessagingUpdates::class)->dispatch($locked->id, $message->id);

            return $message;
        }, attempts: 3);
    }
}
