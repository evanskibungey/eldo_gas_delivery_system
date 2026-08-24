<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use App\Support\ManagerContacts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AlertAdminNewOrder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlacedEvent $event): void
    {
        $order = $event->order->load(['customer', 'size', 'brand']);

        // Broadcast handled by OrderPlacedEvent itself (admin.orders WebSocket channel).
        Log::info("[ORDER] New order #{$order->order_number} placed by customer {$order->customer?->phone}");

        $phones = $this->resolveManagerPhones();

        if (empty($phones)) {
            Log::warning("[ORDER] No admin phones configured — skipping admin SMS for order #{$order->order_number}");
            return;
        }

        $message = app(SmsTemplateService::class)->adminNewOrder($order);

        foreach ($phones as $phone) {
            SendSmsJob::dispatch(
                $phone,
                $message,
                'admin_new_order',
                'admin',
                0,
            );
        }
    }

    /**
     * @return string[]
     */
    private function resolveManagerPhones(): array
    {
        return ManagerContacts::phones();
    }
}
