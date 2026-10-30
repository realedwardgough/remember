<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\MessagingConnections;
use App\Services\Messaging\MessagingUpdates;

class MessagingUserObserver
{
    public function deleting(User $user): void
    {
        // Keep the stable participant ID in memory before the foreign key is nulled.
        $user->load('messagingParticipant');
    }

    public function deleted(User $user): void
    {
        app(MessagingConnections::class)->disconnect($user->id);
        $id = $user->messagingParticipant?->id;
        if ($id === null) {
            return;
        }

        $conversations = Conversation::query()->where('first_participant_id', $id)->orWhere('second_participant_id', $id)->pluck('id');
        foreach ($conversations as $conversationId) {
            app(MessagingUpdates::class)->dispatch($conversationId);
        }
    }
}
