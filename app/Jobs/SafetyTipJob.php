<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SafetyTipJob implements ShouldQueue
{
    use Queueable;

    public int $tries  = 3;
    public int $backoff = 60;

    public function __construct(private readonly int $orderId) {}

    public function handle(SmsServiceInterface $sms, SmsTemplateService $templates): void
    {
        $order = Order::with('customer')->find($this->orderId);

        if (! $order || ! $order->customer || $order->status !== 'delivered') {
            return;
        }

        $phone = $order->customer->phone;
        $sent  = $sms->send($phone, $templates->safetyTip());

        Log::info('[SafetyTip] Post-delivery safety tip', [
            'order_id'    => $this->orderId,
            'customer_id' => $order->customer_id,
            'phone'       => $phone,
            'sent'        => $sent,
        ]);
    }
}
