<?php

namespace App\Http\Middleware;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'admin'    => auth('admin')->user()?->only('id', 'name', 'email', 'is_active'),
                'customer' => auth('customer')->user()?->only('id', 'name', 'phone', 'gaspoints_balance', 'referral_code'),
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],

            'low_stock_count'     => fn () => auth('admin')->check()
                ? StockLevel::whereRaw('filled_count <= low_stock_threshold')->count()
                : 0,

            'pending_orders_count' => fn () => auth('admin')->check()
                ? \App\Models\Order::where('status', 'pending')->count()
                : 0,
        ];
    }
}
