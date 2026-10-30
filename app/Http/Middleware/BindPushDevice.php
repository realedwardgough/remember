<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PushSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindPushDevice
{
    public const string COOKIE = 'remember_push_device';

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->cookie(self::COOKIE);
        if (! is_string($device) || ! preg_match('/^[a-f0-9]{64}$/D', $device)) {
            $device = bin2hex(random_bytes(32));
        }
        $hash = hash('sha256', $device);
        $request->attributes->set('push_device_hash', $hash);
        if ($request->hasCookie(self::COOKIE)) {
            $query = PushSubscription::query()->where('device_hash', $hash);
            if ($request->user()) {
                $query->where('user_id', '!=', $request->user()->id);
            }
            $query->delete();
        }
        $response = $next($request);
        if ($request->user() && ! $request->hasCookie(self::COOKIE)) {
            $response->headers->setCookie(cookie(self::COOKIE, $device, 525600, '/', null, $request->isSecure(), true, false, 'lax'));
        }
        return $response;
    }
}
