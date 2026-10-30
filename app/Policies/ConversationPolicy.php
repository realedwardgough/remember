<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participantFor($user) !== null;
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && $conversation->firstParticipant()->whereHas('user')->exists()
            && $conversation->secondParticipant()->whereHas('user')->exists();
    }
}
