<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCustomerAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            if (! $customer?->is_active) {
                auth('customer')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } else {
                return redirect()->route('customer.home');
            }
        }

        return $next($request);
    }
}
