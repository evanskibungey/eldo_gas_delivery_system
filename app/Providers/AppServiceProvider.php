<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Services\GasPointsService;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Sms\SmsTemplateService;
use App\Services\Sms\TalkSasaSmsService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
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

        // ── Order lifecycle events ──────────────────────────────────────────
        Event::listen(\App\Events\OrderPlacedEvent::class, \App\Listeners\SendOrderConfirmationNotification::class);
        Event::listen(\App\Events\OrderPlacedEvent::class, \App\Listeners\AlertAdminNewOrder::class);

        Event::listen(\App\Events\RiderAssignedEvent::class, \App\Listeners\SendRiderAssignedNotification::class);
        Event::listen(\App\Events\RiderAssignedEvent::class, \App\Listeners\NotifyRiderOfNewOrder::class);

        Event::listen(\App\Events\OrderCancelledEvent::class, \App\Listeners\SendOrderCancelledNotification::class);

        Event::listen(\App\Events\OrderDeliveredEvent::class, \App\Listeners\AwardGasPointsOnDelivery::class);
        Event::listen(\App\Events\OrderDeliveredEvent::class, \App\Listeners\SendDeliveryThankYou::class);
        Event::listen(\App\Events\OrderDeliveredEvent::class, \App\Listeners\SendSafetyTipAfterDelivery::class);

        // ── Push notifications (Sprint 9) ───────────────────────────────────
        Event::listen(
            \App\Events\OrderStatusUpdatedEvent::class,
            \App\Listeners\SendCustomerOrderPush::class,
        );

        // ── Rating events ───────────────────────────────────────────────────
        Event::listen(
            \App\Events\RatingSubmittedEvent::class,
            \App\Listeners\AwardGasPointsOnRating::class,
        );

        // ── Stock events ────────────────────────────────────────────────────
        Event::listen(
            \App\Events\LowStockAlertEvent::class,
            \App\Listeners\HandleLowStockAlert::class,
        );

        Event::listen(
            \App\Events\CriticalStockAlertEvent::class,
            \App\Listeners\HandleCriticalStockAlert::class,
        );

        Event::listen(
            \App\Events\StockDepletedEvent::class,
            \App\Listeners\HandleStockDepleted::class,
        );

        Event::listen(
            \App\Events\StockRestoredEvent::class,
            \App\Listeners\HandleStockRestored::class,
        );

        // ── Exception / edge-case events ────────────────────────────────────
        Event::listen(
            \App\Events\OutOfStockExceptionEvent::class,
            \App\Listeners\HandleOutOfStockException::class,
        );

        Event::listen(
            \App\Events\WrongCylinderReportedEvent::class,
            \App\Listeners\HandleWrongCylinder::class,
        );

        Event::listen(
            \App\Events\DamagedCylinderReportedEvent::class,
            \App\Listeners\HandleDamagedCylinder::class,
        );

        Event::listen(
            \App\Events\RiderDelayAlertEvent::class,
            \App\Listeners\HandleRiderDelayAlert::class,
        );

        Event::listen(
            \App\Events\PaymentDisputeEvent::class,
            \App\Listeners\HandlePaymentDispute::class,
        );
    }
}
