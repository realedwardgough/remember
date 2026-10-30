<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ConversationMembership;
use App\Models\Message;
use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\MessagePush;
use App\Services\Messaging\PushSettings;
use App\Services\Messaging\PushTransport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMessagePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId)
    {
    }

    public function handle(PushTransport $transport, PushSettings $settings): void
    {
        $retry = DB::transaction(function () use ($transport, $settings): bool {
            // Lock device before delivery consistently with logout/subscription changes.
            $subscriptionId = MessagePushDelivery::query()->whereKey($this->deliveryId)->value('push_subscription_id');
            $device = PushSubscription::query()->lockForUpdate()->find($subscriptionId);
            $delivery = MessagePushDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if ($delivery === null || $device === null || $delivery->state !== 'pending' || $delivery->next_attempt_at->isFuture()) {
                return false;
            }
            $message = $delivery->message_id === null ? null : Message::query()->find($delivery->message_id);
            if (! $settings->available() || $delivery->expires_at->lessThanOrEqualTo(now()) || $delivery->attempts >= 4
                || ($device->expires_at !== null && $device->expires_at->lessThanOrEqualTo(now()))
                || ! User::query()->whereKey($device->user_id)->exists()
                || ($delivery->message_id !== null && ! $this->unreadFor($message, $device))) {
                $delivery->update(['state' => 'skipped']);
                return false;
            }
            $delivery->increment('attempts');
            try {
                $status = $transport->send($device, [
                    'title' => 'Remember',
                    'body' => $message === null ? 'Notifications are working on this device.' : 'You have a new message in Remember.',
                    'url' => $message === null ? '/profile#notifications' : '/messages/'.$message->conversation_id,
                    'tag' => $message === null ? 'remember-test' : 'remember-conversation-'.$message->conversation_id,
                ]);
            } catch (Throwable) {
                $status = null;
            }
            if (in_array($status, [404, 410], true)) {
                $device->delete();
                return false;
            }
            if ($status !== null && $status >= 200 && $status < 300) {
                $delivery->update(['state' => 'sent']);
                return false;
            }
            if ($delivery->attempts < 4 && ($status === null || $status === 429 || $status >= 500)) {
                $delivery->update(['next_attempt_at' => now()->addSeconds([30, 120, 300][$delivery->attempts - 1])]);
                return true;
            }
            $delivery->update(['state' => 'failed']);
            Log::warning('Message notification delivery failed.', ['delivery_id' => $delivery->id, 'status' => $status]);
            return false;
        });
        if ($retry) {
            app(MessagePush::class)->enqueue($this->deliveryId);
        }
    }

    private function unreadFor(?Message $message, PushSubscription $device): bool
    {
        if ($message === null || ! NotificationPreference::query()->where('user_id', $device->user_id)->where('message_alerts', true)->exists()) {
            return false;
        }
        $member = ConversationMembership::query()->where('conversation_id', $message->conversation_id)
            ->where('participant_id', '!=', $message->sender_participant_id)
            ->whereHas('participant', fn ($query) => $query->where('user_id', $device->user_id))->first();
        return $member !== null && ($member->last_read_message_id === null || $member->last_read_message_id < $message->id);
    }
}
