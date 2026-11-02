<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushTransport
{
    // Return only a status code; request URLs, secrets and response bodies never enter logs/jobs.
    public function send(PushSubscription $device, array $payload): ?int
    {
        $client = new \GuzzleHttp\Client(app(PushEndpoint::class)->clientOptions($device->endpoint));
        $factory = new \GuzzleHttp\Psr7\HttpFactory;
        $push = new WebPush(
            ['VAPID' => app(PushSettings::class)->credentials()],
            ['TTL' => 60, 'urgency' => 'normal'],
            $client,
            $factory,
            $factory
        );
        $report = $push->sendOneNotification(Subscription::create([
            'endpoint' => $device->endpoint,
            'publicKey' => $device->public_key,
            'authToken' => $device->auth_token,
            'contentEncoding' => 'aes128gcm',
        ]), json_encode($payload, JSON_THROW_ON_ERROR));
        return $report->getResponse()?->getStatusCode();
    }
}
