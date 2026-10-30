<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;

class TypingState
{
    private const int LIFETIME_MS = 6000;

    private function key(int $conversationId, int $participantId): string
    {
        return 'messaging:typing:'.$conversationId.':'.$participantId;
    }

    public function update(int $conversationId, int $participantId, string $sourceId, bool $active): void
    {
        $key = $this->key($conversationId, $participantId);
        Cache::lock($key.':lock', 3)->get(function () use ($key, $sourceId, $active): void {
            $sources = array_filter(Cache::get($key, []), fn (int $expiry): bool => $expiry > now()->getTimestampMs());
            if ($active) {
                $sources[$sourceId] = now()->getTimestampMs() + self::LIFETIME_MS;
            } else {
                unset($sources[$sourceId]);
            }
            if ($sources === []) {
                Cache::forget($key);
            } else {
                Cache::put($key, $sources, 6);
            }
        });
    }

    public function forOtherParticipant(Conversation $conversation, int $participantId): array
    {
        $other = $conversation->first_participant_id === $participantId ? $conversation->second_participant_id : $conversation->first_participant_id;
        $events = [];
        foreach (Cache::get($this->key($conversation->id, $other), []) as $sourceId => $expiry) {
            $remaining = $expiry - now()->getTimestampMs();
            if ($remaining > 0) {
                $events[] = ['conversation_id' => $conversation->id, 'participant_id' => $other, 'source_id' => $sourceId, 'typing' => true, 'expires_in_ms' => min(self::LIFETIME_MS, $remaining)];
            }
        }
        return $events;
    }
}
