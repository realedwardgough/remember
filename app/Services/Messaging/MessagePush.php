<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Jobs\SendMessagePush;
use App\Models\Message;
use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessagePush
{
    public function prepare(Message $message): void
    {
        if (! app(PushSettings::class)->available()) {
            return;
        }
        $recipient = $message->conversation->memberships()->where('participant_id', '!=', $message->sender_participant_id)
            ->with('participant')->first()?->participant;
        if ($recipient?->user_id === null || ! NotificationPreference::query()->where('user_id', $recipient->user_id)->where('message_alerts', true)->exists()) {
            return;
        }
        foreach (PushSubscription::query()->where('user_id', $recipient->user_id)->get() as $device) {
            $delivery = MessagePushDelivery::query()->firstOrCreate([
                'message_id' => $message->id, 'push_subscription_id' => $device->id,
            ], ['next_attempt_at' => now()->addSeconds(5), 'expires_at' => now()->addMinutes(15)]);
            DB::afterCommit(fn () => $this->enqueue($delivery->id));
        }
    }

    public function enqueue(int $deliveryId): void
    {
        try {
            $delivery = MessagePushDelivery::query()->find($deliveryId);
            if ($delivery !== null && $delivery->state === 'pending') {
                SendMessagePush::dispatch($deliveryId)->delay($delivery->next_attempt_at)->afterCommit();
            }
        } catch (Throwable) {
            // Durable outbox is recovered by messages:retry-push. No endpoint/keys in logs.
            Log::warning('Message notification could not be queued.', ['delivery_id' => $deliveryId]);
        }
    }
}
