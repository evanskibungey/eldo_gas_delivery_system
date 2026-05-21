<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\GasPointsService;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Sms\SmsTemplateService;
use App\Services\Sms\TalkSasaSmsService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SMS service — TalkSasa is the active provider
        $this->app->singleton(SmsServiceInterface::class, TalkSasaSmsService::class);

        // SMS templates — singleton so SystemSetting is read once per request
        $this->app->singleton(SmsTemplateService::class);

        // GasPoints service — singleton so state is consistent within a request
        $this->app->singleton(GasPointsService::class);
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        // Multi-guard broadcasting auth: admin and customer guards both need
        // to be resolvable via $request->user() for Broadcast::auth() to work.
        // This app has no default-guard users — everyone is admin or customer.
        Broadcast::routes(['middleware' => ['web']]);

        if (file_exists(base_path('routes/channels.php'))) {
            require base_path('routes/channels.php');
        }

        // Multi-guard broadcasting auth: resolve admin and customer session guards
        // when $request->user() is called (e.g. from Broadcast::auth).
        // Only handle session-based guards here — Sanctum API guards resolve
        // themselves via bearer token and must NOT be proxied through this resolver
        // (doing so creates infinite recursion).
        $this->app->resolving('request', function ($request) {
            $request->setUserResolver(function ($guard = null) {
                $sessionGuards = ['admin', 'customer'];

                if ($guard !== null) {
                    if (in_array($guard, $sessionGuards, true)) {
                        return $this->app['auth']->guard($guard)->user();
                    }
                    return null;
                }

                return $this->app['auth']->guard('admin')->user()
                    ?? $this->app['auth']->guard('customer')->user();
            });
        });

        // Event listeners are auto-discovered from app/Listeners via handle() type-hints.
        // Do not register them manually here — that would cause each listener to fire twice.
    }
}
