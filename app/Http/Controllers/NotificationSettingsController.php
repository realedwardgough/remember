<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Services\Messaging\PushEndpoint;
use App\Services\Messaging\PushSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class NotificationSettingsController extends Controller
{
    public function show(Request $request, PushSettings $settings): JsonResponse
    {
        $device = $this->device($request);
        return response()->json([
            ...$settings->publicSettings(),
            'message_alerts' => NotificationPreference::query()->where('user_id', $request->user()->id)->value('message_alerts') === true,
            'post_alerts' => NotificationPreference::query()->where('user_id', $request->user()->id)->value('post_alerts') === true,
            'device_enabled' => $device !== null && ($device->expires_at === null || $device->expires_at->isFuture()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message_alerts' => ['required_without:post_alerts', 'boolean'],
            'post_alerts' => ['required_without:message_alerts', 'boolean'],
        ]);
        NotificationPreference::query()->updateOrCreate(['user_id' => $request->user()->id], $data);
        return $this->show($request, app(PushSettings::class));
    }

    public function subscribe(Request $request, PushEndpoint $endpoints, PushSettings $settings): JsonResponse
    {
        abort_unless($settings->available(), 503, 'Notifications are not configured yet.');
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:100'],
            'keys.auth' => ['required', 'string', 'max:30'],
            'expirationTime' => ['nullable', 'numeric', 'min:'.(time() * 1000 + 1000), 'max:253402300799000'],
        ]);
        try {
            $endpoints->host($data['endpoint']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['endpoint' => 'This browser’s push service is not supported. Try Safari, Chrome or Firefox.']);
        }
        $publicKey = $this->decodeKey($data['keys']['p256dh'], 65);
        $this->decodeKey($data['keys']['auth'], 16);
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$publicKey), 64, "\n")."-----END PUBLIC KEY-----";
        if (ord($publicKey[0]) !== 4 || openssl_pkey_get_public($pem) === false) {
            throw ValidationException::withMessages(['keys' => 'The browser subscription is invalid. Disable notifications on this device and enable them again.']);
        }
        DB::transaction(function () use ($request, $data): void {
            // Serialize a user's device changes and reject claims from a different browser cookie.
            \App\Models\User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $endpointHash = hash('sha256', $data['endpoint']);
            $deviceHash = $request->attributes->get('push_device_hash');
            $existing = PushSubscription::query()->where('endpoint_hash', $endpointHash)->lockForUpdate()->first();
            if ($existing !== null && $existing->device_hash !== $deviceHash) {
                throw ValidationException::withMessages(['endpoint' => 'Please disable notifications in this browser and enable them again.']);
            }
            $same = $existing !== null && $existing->user_id === $request->user()->id
                && hash_equals($existing->public_key, $data['keys']['p256dh']) && hash_equals($existing->auth_token, $data['keys']['auth']);
            if ($same) {
                $existing->update(['expires_at' => isset($data['expirationTime']) ? \Carbon\CarbonImmutable::createFromTimestampMs($data['expirationTime']) : null]);
            } else {
                PushSubscription::query()->where('device_hash', $deviceHash)->delete();
                PushSubscription::query()->create([
                    'user_id' => $request->user()->id, 'device_hash' => $deviceHash,
                    'endpoint_hash' => $endpointHash, 'endpoint' => $data['endpoint'],
                    'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
                    'expires_at' => isset($data['expirationTime']) ? \Carbon\CarbonImmutable::createFromTimestampMs($data['expirationTime']) : null,
                ]);
            }
        });
        return $this->show($request, $settings);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $this->device($request)?->delete();
        return $this->show($request, app(PushSettings::class));
    }

    public function test(Request $request, PushSettings $settings): JsonResponse
    {
        abort_unless($settings->available(), 503);
        $device = $this->device($request);
        abort_unless($device !== null && ($device->expires_at === null || $device->expires_at->isFuture()), 422);
        // Explicit test works even when account-wide message alerts are paused.
        $delivery = MessagePushDelivery::query()->create([
            'push_subscription_id' => $device->id, 'kind' => 'test',
            'next_attempt_at' => now(), 'expires_at' => now()->addMinutes(5),
        ]);
        app(\App\Services\Messaging\MessagePush::class)->enqueue($delivery->id);
        return response()->json(['queued' => true], 202);
    }

    private function device(Request $request): ?PushSubscription
    {
        return PushSubscription::query()->where('user_id', $request->user()->id)
            ->where('device_hash', $request->attributes->get('push_device_hash'))->first();
    }

    private function decodeKey(string $key, int $length): string
    {
        $decoded = preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $key) ? base64_decode(strtr($key, '-_', '+/'), true) : false;
        if ($decoded === false || strlen($decoded) !== $length) {
            throw ValidationException::withMessages(['keys' => 'The browser subscription is invalid. Please try enabling notifications again.']);
        }
        return $decoded;
    }
}
