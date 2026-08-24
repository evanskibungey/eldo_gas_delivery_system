<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;

/**
 * Implements the AuthenticatesRequests marker so Laravel's middleware
 * priority runs this ahead of ThrottleRequests — otherwise the limiter
 * keys on IP rather than the rider, and riders sharing a carrier NAT
 * address would eat each other's allowance.
 */
class EnsureApiRider implements AuthenticatesRequests
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('rider-api')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bind the authenticated rider onto the request so $request->user() works in controllers.
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
