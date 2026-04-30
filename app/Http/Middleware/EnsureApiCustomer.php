<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApiCustomer
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth('customer-api')->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bind the authenticated customer onto the request so $request->user() works in controllers.
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
