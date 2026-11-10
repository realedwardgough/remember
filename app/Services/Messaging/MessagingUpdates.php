<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Events\Messaging\MessagingChanged;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessagingUpdates
{
    public function dispatch(int $conversationId, ?int $messageId = null, ?int $onlyUserId = null): void
    {
        DB::afterCommit(function () use ($conversationId, $messageId, $onlyUserId): void {
            try {
                event(new MessagingChanged($conversationId, $messageId, $onlyUserId));
            } catch (Throwable $exception) {
                // Saving a message/read position must succeed even if its live update cannot be queued.
                report($exception);
            }
        });
    }
}
