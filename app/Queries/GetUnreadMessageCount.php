<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Message;
use App\Models\MessagingParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GetUnreadMessageCount
{
    public function handle(User $user): int
    {
        $participantId = MessagingParticipant::query()->where('user_id', $user->id)->value('id');

        if ($participantId === null) {
            return 0;
        }

        return Message::query()->where('sender_participant_id', '!=', $participantId)
            ->whereHas('conversation.memberships', function (Builder $memberships) use ($participantId): void {
                $memberships->where('participant_id', $participantId)
                    ->where(fn (Builder $read) => $read->whereNull('last_read_message_id')
                        ->orWhereColumn('last_read_message_id', '<', 'messages.id'));
            })->count();
    }
}
