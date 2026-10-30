<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessagingParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SendMessage
{
    public function handle(User $actor, Conversation $conversation, string $body, string $clientRequestId): Message
    {
        Gate::forUser($actor)->authorize('view', $conversation);

        $body = trim($body);
        $clientRequestId = strtolower($clientRequestId);

        if ($body === '' || mb_strlen($body) > 10000) {
            throw ValidationException::withMessages(['body' => 'Enter a message of up to 10,000 characters.']);
        }

        return DB::transaction(function () use ($actor, $conversation, $body, $clientRequestId): Message {
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
                if ($existing->conversation_id !== $locked->id || $existing->body !== $body) {
                    throw ValidationException::withMessages(['client_request_id' => 'This request ID was already used for a different message.']);
                }

                return $existing;
            }

            Gate::forUser($actor)->authorize('send', $locked);

            $message = Message::query()->create([
                'conversation_id' => $locked->id,
                'sender_participant_id' => $participant->id,
                'body' => $body,
                'client_request_id' => $clientRequestId,
            ]);

            $locked->touch();

            return $message;
        }, attempts: 3);
    }
}
