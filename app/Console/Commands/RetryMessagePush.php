<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessagePushDelivery;
use App\Models\PushSubscription;
use App\Services\Messaging\MessagePush;
use Illuminate\Console\Command;

class RetryMessagePush extends Command
{
    protected $signature = 'messages:retry-push';
    protected $description = 'Recover pending message notifications and prune old delivery metadata';

    public function handle(MessagePush $push): int
    {
        MessagePushDelivery::query()->where('state', 'pending')->where('expires_at', '<=', now())->update(['state' => 'skipped']);
        MessagePushDelivery::query()->where('state', '!=', 'pending')->where('updated_at', '<', now()->subDays(30))->delete();
        PushSubscription::query()->where('expires_at', '<=', now())->delete();
        MessagePushDelivery::query()->where('state', 'pending')->where('next_attempt_at', '<=', now())
            ->where('updated_at', '<=', now()->subMinute())->orderBy('id')->limit(100)->pluck('id')
            ->each(fn (int $id) => $push->enqueue($id));
        return self::SUCCESS;
    }
}
