<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Timeline;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationIsNotSetup
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Timeline::query()->exists() || User::query()->exists(), 404);

        if (app()->isProduction() || filled(config('security.setup_token'))) {
            $token = $request->input('setup_token', $request->session()->get('setup_token'));
            abort_unless(is_string($token) && filled(config('security.setup_token'))
                && hash_equals((string) config('security.setup_token'), $token), 403);
            $request->session()->put('setup_token', $token);
        }

        return $next($request);
    }
}
