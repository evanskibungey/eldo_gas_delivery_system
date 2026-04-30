<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiRider
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
