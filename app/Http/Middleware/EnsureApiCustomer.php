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

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
