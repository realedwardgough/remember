<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        $publicForm = $request->isMethod('POST') && $request->is('login', 'register', 'setup');
        if ($publicForm && ! RateLimiter::attempt('public-auth|'.$request->ip(), 30, static fn () => true)) {
            abort(429, 'Too many attempts. Please try again shortly.');
        }
        if (! config('security.turnstile.enabled') || ! $publicForm) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response');
        $valid = false;
        if (is_string($token) && $token !== '' && strlen($token) <= 2048
            && filled(config('security.turnstile.secret_key')) && filled(config('security.turnstile.hostname'))) {
            try {
                if (! Cache::add('turnstile:'.hash('sha256', $token), true, now()->addMinutes(5))) {
                    throw new \RuntimeException('Challenge token already claimed.');
                }
                $response = Http::asForm()->connectTimeout(3)->timeout(8)->post(
                    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                    ['secret' => config('security.turnstile.secret_key'), 'response' => $token, 'remoteip' => $request->ip()],
                );
                $valid = $response->successful() && $response->json('success') === true
                    && $response->json('hostname') === config('security.turnstile.hostname')
                    && $response->json('action') === $request->path();
            } catch (Throwable) {
                // Fail closed when enabled, including during verification outages.
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Please complete the security check and try again.']);
        }

        return $next($request);
    }
}
