<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;

class DisconnectMessagingUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public array $backoff = [1, 5, 15, 30];

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        if (config('broadcasting.default') === 'reverb') {
            Broadcast::connection('reverb')->getPusher()->terminateUserConnections((string) $this->userId);
        }
    }
}
