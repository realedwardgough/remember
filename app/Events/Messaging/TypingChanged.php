<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Models\Conversation;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class TypingChanged implements ShouldBroadcastNow
{
    public function __construct(public int $conversationId, public int $participantId, public bool $typing, public string $sourceId)
    {
    }

    public function broadcastOn(): array
    {
        $conversation = Conversation::query()->with(['firstParticipant', 'secondParticipant'])->find($this->conversationId);
        if ($conversation === null || $conversation->firstParticipant->user_id === null || $conversation->secondParticipant->user_id === null) {
            return [];
        }

        return [new PresenceChannel('messaging.conversations.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'messaging.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'participant_id' => $this->participantId,
            'typing' => $this->typing,
            'source_id' => $this->sourceId,
        ];
    }
}
