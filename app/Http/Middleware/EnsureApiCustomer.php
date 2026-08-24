<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\Request;

/**
 * Implements the AuthenticatesRequests marker so Laravel's middleware
 * priority runs this ahead of ThrottleRequests. Without it the sorter
 * hoists the throttle first, $request->user() is still null when the
 * limiter builds its key, and every customer behind one carrier NAT
 * address ends up sharing a single bucket.
 */
class EnsureApiCustomer implements AuthenticatesRequests
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('customer-api')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
