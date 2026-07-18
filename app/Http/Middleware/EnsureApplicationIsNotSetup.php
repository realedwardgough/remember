<?php

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

        return $next($request);
    }
}
