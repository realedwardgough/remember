<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SharePostMessage
{
    public function __construct(private readonly StartConversation $start, private readonly SendMessage $send)
    {
    }

    public function handle(User $sender, int $recipientId, int $postId, string $body, string $requestId): Message
    {
        return DB::transaction(function () use ($sender, $recipientId, $postId, $body, $requestId): Message {
            $conversation = $this->start->handle($sender, $recipientId);

            // Invalid/private posts roll back a newly-created empty conversation too.
            return $this->send->handle($sender, $conversation, $body, $requestId, postId: $postId);
        }, attempts: 3);
    }
}
