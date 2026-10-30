<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\ConversationMembership;
use App\Models\MessagingParticipant;
use App\Models\User;
use App\Services\Messaging\MessagingUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartConversation
{
    public function handle(User $actor, int $recipientId): Conversation
    {
        if ($actor->id === $recipientId) {
            throw ValidationException::withMessages(['recipient_id' => 'Choose another family member.']);
        }

        return DB::transaction(function () use ($actor, $recipientId): Conversation {
            // Consistent user lock ordering serializes opposite-direction starts and account deletion.
            $users = User::query()->whereIn('id', [$actor->id, $recipientId])->orderBy('id')->lockForUpdate()->get();
            abort_unless($users->contains('id', $actor->id), 403);

            if (! $users->contains('id', $recipientId)) {
                throw ValidationException::withMessages(['recipient_id' => 'This family member is no longer available.']);
            }

            $ids = $users->map(fn (User $user): int => MessagingParticipant::query()
                ->firstOrCreate(['user_id' => $user->id])->id)->sort()->values();

            $conversation = Conversation::query()->firstOrCreate([
                'first_participant_id' => $ids[0],
                'second_participant_id' => $ids[1],
            ]);

            foreach ($ids as $participantId) {
                ConversationMembership::query()->firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $participantId,
                ]);
            }

            if ($conversation->wasRecentlyCreated) {
                app(MessagingUpdates::class)->dispatch($conversation->id);
            }

            return $conversation;
        }, attempts: 3);
    }
}
