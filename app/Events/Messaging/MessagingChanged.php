<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Models\Conversation;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class MessagingChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public int $tries = 3;
    public array $backoff = [1, 5, 15];

    public function __construct(public int $conversationId, public ?int $messageId = null, public ?int $onlyUserId = null)
    {
    }

    public function broadcastOn(): array
    {
        $conversation = Conversation::query()->with(['firstParticipant', 'secondParticipant'])->find($this->conversationId);
        if ($conversation === null) {
            return [];
        }

        $users = collect([$conversation->firstParticipant->user_id, $conversation->secondParticipant->user_id])->filter();
        $channels = $users->filter(fn (int $id): bool => $this->onlyUserId === null || $id === $this->onlyUserId)
            ->map(fn (int $id): PresenceChannel => new PresenceChannel('messaging.users.'.$id))->values()->all();

        if ($this->onlyUserId === null && $users->count() === 2) {
            $channels[] = new PresenceChannel('messaging.conversations.'.$conversation->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'messaging.changed';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'message_id' => $this->messageId];
    }
}
