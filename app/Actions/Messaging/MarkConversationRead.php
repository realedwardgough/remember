<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\ReadReceiptToken;
use App\Services\Messaging\MessagingUpdates;
use Illuminate\Support\Facades\Gate;

class MarkConversationRead
{
    public function __construct(private readonly ReadReceiptToken $tokens)
    {
    }

    public function handle(User $user, Conversation $conversation, string $token): void
    {
        Gate::forUser($user)->authorize('view', $conversation);
        $messageId = $this->tokens->resolve($user, $conversation, $token);
        abort_unless($conversation->messages()->whereKey($messageId)->exists(), 422);
        $participant = $conversation->participantFor($user);

        // A conditional update keeps out-of-order receipts from moving the cursor backwards.
        $updated = $conversation->memberships()->where('participant_id', $participant->id)
            ->where(fn ($query) => $query->whereNull('last_read_message_id')->orWhere('last_read_message_id', '<', $messageId))
            ->update(['last_read_message_id' => $messageId, 'updated_at' => now()]);
        if ($updated) {
            app(MessagingUpdates::class)->dispatch($conversation->id, onlyUserId: $user->id);
        }
    }
}
