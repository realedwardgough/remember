<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class ReadReceiptToken
{
    public function issue(User $user, Conversation $conversation, int $messageId): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'expires_at' => now()->addDay()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function resolve(User $user, Conversation $conversation, string $token): int
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException | JsonException) {
            throw ValidationException::withMessages(['read_token' => 'Reload the conversation before marking messages as read.']);
        }

        if (! is_array($data)
            || ($data['user_id'] ?? null) !== $user->id
            || ($data['conversation_id'] ?? null) !== $conversation->id
            || ! is_int($data['message_id'] ?? null)
            || ($data['expires_at'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['read_token' => 'Reload the conversation before marking messages as read.']);
        }

        return $data['message_id'];
    }
}
