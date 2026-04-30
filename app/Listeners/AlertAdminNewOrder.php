<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AlertAdminNewOrder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlacedEvent $event): void
    {
        // Broadcasting is handled directly by OrderPlacedEvent (ShouldBroadcast).
        // This listener handles any additional admin-side side-effects.
        Log::info("[ORDER] New order #{$event->order->order_number} broadcast to admin.orders channel");
    }
}
