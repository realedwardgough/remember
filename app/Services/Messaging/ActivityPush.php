<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\ActivityNotification;
use App\Models\MessagePushDelivery;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\DB;

class ActivityPush
{
    public function prepare(ActivityNotification $activity): void
    {
        if (! app(PushSettings::class)->available() || ! $activity->user?->wantsActivityAlerts()) {
            return;
        }

        foreach (PushSubscription::query()->where('user_id', $activity->user_id)->get() as $device) {
            $delivery = MessagePushDelivery::query()->firstOrCreate([
                'activity_notification_id' => $activity->id,
                'push_subscription_id' => $device->id,
            ], [
                'kind' => 'activity',
                'next_attempt_at' => now()->addSeconds(5),
                'expires_at' => now()->addMinutes(15),
            ]);
            DB::afterCommit(fn () => app(MessagePush::class)->enqueue($delivery->id));
        }
    }
}
