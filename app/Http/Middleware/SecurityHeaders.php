<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->user() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if (app()->isProduction()) {
            $nonce = Vite::cspNonce();
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'", "base-uri 'self'", "object-src 'none'", "frame-ancestors 'none'",
                "form-action 'self'", "script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com",
                "style-src 'self' 'unsafe-inline'", "img-src 'self' data: blob: https:",
                "font-src 'self' data:", "media-src 'self' blob:", "worker-src 'self' blob:",
                "connect-src 'self' https://challenges.cloudflare.com wss:",
                'frame-src https://challenges.cloudflare.com',
            ]));
            if ($request->isSecure() && config('security.hsts')) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            }
        }

        return $response;
    }
}
