<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\MessagePushDelivery;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostPush
{
    public function prepare(Post $post): void
    {
        if (! app(PushSettings::class)->available() || $post->author_id === null) {
            return;
        }
        User::query()->where('id', '!=', $post->author_id)
            ->whereIn('id', \App\Models\NotificationPreference::query()->select('user_id')->where('post_alerts', true))
            ->chunkById(100, function ($users) use ($post): void {
                foreach ($users as $user) {
                    if (! Gate::forUser($user)->allows('view', $post)) {
                        continue;
                    }
                    foreach (PushSubscription::query()->where('user_id', $user->id)->get() as $device) {
                        $delivery = MessagePushDelivery::query()->firstOrCreate([
                            'post_id' => $post->id, 'push_subscription_id' => $device->id,
                        ], ['kind' => 'post', 'next_attempt_at' => now()->addSeconds(5), 'expires_at' => now()->addMinutes(15)]);
                        DB::afterCommit(fn () => app(MessagePush::class)->enqueue($delivery->id));
                    }
                }
            });
    }
}
