<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Presence supplies Reverb with a verified user ID for server-side socket revocation.
// The UI does not display an online roster. No names or email addresses are included.
Broadcast::channel('messaging.users.{id}', function (User $user, string $id): array|bool {
    return (string) $user->id === $id ? ['id' => $user->id] : false;
});

Broadcast::channel('messaging.conversations.{id}', function (User $user, string $id): array|bool {
    $conversation = Conversation::query()->find($id);

    return $conversation?->participantFor($user) !== null ? ['id' => $user->id] : false;
});
