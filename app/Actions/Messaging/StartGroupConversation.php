<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\MessagingParticipant;
use App\Models\User;
use App\Services\Messaging\MessagingUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartGroupConversation
{
    public function handle(User $actor, string $name, array $recipientIds, string $requestId): Conversation
    {
        $memberIds = collect($recipientIds)->map(intval(...))->push($actor->id)->unique()->sort()->values();

        if ($memberIds->count() < 3 || $memberIds->count() > 20) {
            throw ValidationException::withMessages(['recipient_ids' => 'Choose between two and nineteen other family members.']);
        }

        return DB::transaction(function () use ($actor, $name, $memberIds, $requestId): Conversation {
            $users = User::query()->whereIn('id', $memberIds)->orderBy('id')->lockForUpdate()->get();
            abort_unless($users->contains('id', $actor->id), 403);

            if ($users->count() !== $memberIds->count()) {
                throw ValidationException::withMessages(['recipient_ids' => 'A selected family member is no longer available.']);
            }

            $existing = Conversation::query()->where('created_by_user_id', $actor->id)->where('creation_request_id', strtolower($requestId))->first();

            if ($existing !== null) {
                $existingIds = $existing->participants()->pluck('user_id')->sort()->values();

                if ($existing->name !== trim($name) || $existingIds->all() !== $memberIds->all()) {
                    throw ValidationException::withMessages(['client_request_id' => 'This request ID was already used for a different group.']);
                }

                return $existing;
            }

            $conversation = Conversation::query()->create([
                'is_group' => true, 'name' => trim($name),
                'created_by_user_id' => $actor->id, 'creation_request_id' => strtolower($requestId),
            ]);
            foreach ($users as $user) {
                $participant = MessagingParticipant::query()->firstOrCreate(['user_id' => $user->id]);
                $conversation->memberships()->create(['participant_id' => $participant->id]);
            }
            app(MessagingUpdates::class)->dispatch($conversation->id);

            return $conversation;
        }, attempts: 3);
    }
}
