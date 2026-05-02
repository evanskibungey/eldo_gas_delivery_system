<?php

namespace App\Http\Middleware;

use App\Models\StockLevel;
use App\Models\SystemSetting;
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

            'app_name'   => fn () => SystemSetting::get('app_name', config('app.name')),
            'shop_hours' => fn () => [
                'open'  => SystemSetting::get('shop_open_time',  '07:00'),
                'close' => SystemSetting::get('shop_close_time', '21:00'),
            ],

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
