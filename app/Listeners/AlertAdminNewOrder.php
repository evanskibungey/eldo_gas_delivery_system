<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Jobs\SendSmsJob;
use App\Models\SystemSetting;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AlertAdminNewOrder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlacedEvent $event): void
    {
        $order = $event->order->load(['customer', 'size', 'brand']);

        // Broadcast is handled by OrderPlacedEvent itself (ShouldBroadcast → admin.orders channel).
        Log::info("[ORDER] New order #{$order->order_number} placed by customer {$order->customer?->phone}");

        $adminPhone = SystemSetting::get('shop_manager_phone', config('shop.manager_phone', ''));

        if (! $adminPhone) {
            Log::warning("[ORDER] No admin phone configured — skipping admin SMS for order #{$order->order_number}");
            return;
        }

        SendSmsJob::dispatch(
            $adminPhone,
            app(SmsTemplateService::class)->adminNewOrder($order),
            'admin_new_order',
            'admin',
            0,
        );
    }
}
