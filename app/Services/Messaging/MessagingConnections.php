<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Jobs\DisconnectMessagingUser;
use Illuminate\Support\Facades\DB;
use Throwable;

class MessagingConnections
{
    public function disconnect(int $userId): void
    {
        DB::afterCommit(function () use ($userId): void {
            try {
                // Revoke active sockets promptly; the queue supplies bounded retry on temporary failure.
                (new DisconnectMessagingUser($userId))->handle();
            } catch (Throwable $exception) {
                report($exception);
                try {
                    DisconnectMessagingUser::dispatch($userId)->delay(now()->addSecond());
                } catch (Throwable $queueException) {
                    report($queueException);
                }
            }
        });
    }
}
