<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SafetyTipJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private readonly int $orderId) {}

    public function handle(SmsServiceInterface $sms): void
    {
        $order = Order::with('customer')->find($this->orderId);

        if (! $order || ! $order->customer || $order->status !== 'delivered') {
            return;
        }

        $phone   = $order->customer->phone;
        $message = 'Safety Tip from EldoGas: Smell gas? Do NOT switch on lights or appliances. '
            . 'Open all windows, leave the building, and call 999 or 0800 723 723. '
            . 'Stay safe — EldoGas Team.';

        $sent = $sms->send($phone, $message);

        Log::info('[SafetyTip] Post-delivery safety tip', [
            'order_id'    => $this->orderId,
            'customer_id' => $order->customer_id,
            'phone'       => $phone,
            'sent'        => $sent,
        ]);
    }
}
