<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Messaging\MessagingConnections;
use Illuminate\Auth\Events\Logout;

class DisconnectMessagingOnLogout
{
    public function __construct(private readonly MessagingConnections $connections)
    {
    }

    public function handle(Logout $event): void
    {
        if ($event->user !== null) {
            $this->connections->disconnect((int) $event->user->getAuthIdentifier());
        }
    }
}
