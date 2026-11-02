<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\PushSubscription;
use Illuminate\Auth\Events\Logout;

class RevokePushOnLogout
{
    public function handle(Logout $event): void
    {
        if ($event->user !== null && request()->attributes->has('push_device_hash')) {
            PushSubscription::query()->where('user_id', $event->user->getAuthIdentifier())
                ->where('device_hash', request()->attributes->get('push_device_hash'))->delete();
        }
    }
}
