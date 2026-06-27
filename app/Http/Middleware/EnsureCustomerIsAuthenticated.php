<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('customer')->check()) {
            return redirect()->route('customer.login');
        }

        $customer = auth('customer')->user();
        if (! $customer?->is_active) {
            auth('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('customer.login')->withErrors([
                'phone' => 'Your account is currently inactive. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
