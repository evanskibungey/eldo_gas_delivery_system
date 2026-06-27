<?php

namespace App\Http\Middleware;

use App\Models\StockLevel;
use App\Models\SystemSetting;
use App\Support\Utf8Sanitizer;
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

            'app_name' => fn () => Utf8Sanitizer::clean(SystemSetting::get('app_name', config('app.name'))),
            'shop_hours' => fn () => [
                'open' => Utf8Sanitizer::clean(SystemSetting::get('shop_open_time', '07:00')),
                'close' => Utf8Sanitizer::clean(SystemSetting::get('shop_close_time', '21:00')),
                'always_open' => (bool) SystemSetting::get('shop_always_open', '1'),
            ],

            'auth' => Utf8Sanitizer::clean([
                'admin' => auth('admin')->user()?->only('id', 'name', 'email', 'is_active'),
                'customer' => auth('customer')->user()?->only('id', 'name', 'phone', 'gaspoints_balance', 'referral_code'),
            ]),

            'flash' => [
                'success' => fn () => Utf8Sanitizer::clean($request->session()->get('success')),
                'error' => fn () => Utf8Sanitizer::clean($request->session()->get('error')),
                'warning' => fn () => Utf8Sanitizer::clean($request->session()->get('warning')),
            ],

            'low_stock_count' => fn () => auth('admin')->check()
                ? StockLevel::whereRaw('filled_count <= low_stock_threshold')->count()
                : 0,

            'pending_orders_count' => fn () => auth('admin')->check()
                ? \App\Models\Order::where('status', 'pending')->count()
                : 0,
        ];
    }
}