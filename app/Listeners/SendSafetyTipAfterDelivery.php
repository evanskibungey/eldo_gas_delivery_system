<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Jobs\SafetyTipJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSafetyTipAfterDelivery implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(OrderDeliveredEvent $event): void
    {
        SafetyTipJob::dispatch($event->order->id)
            ->delay(now()->addMinutes(10))
            ->onQueue('default');
    }
}
