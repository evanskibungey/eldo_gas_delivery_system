<?php

namespace App\Listeners;

use App\Events\RiderOrderRemovedEvent;
use App\Jobs\SendRiderPushJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The rider app clears a removed order over the WebSocket, but a rider whose
 * socket dropped would keep showing it until the next foreground refetch.
 * A push closes that gap — and only for expiry, since a rider who declined
 * deliberately does not need telling.
 */
class NotifyRiderOrderRemoved implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(RiderOrderRemovedEvent $event): void
    {
        if ($event->reason !== 'acceptance_expired') {
            return;
        }

        SendRiderPushJob::dispatch(
            riderId: $event->riderId,
            title: 'Assignment expired',
            body: 'The delivery was reassigned because it was not accepted in time.',
            data: [
                'type'      => 'order.removed',
                'order_id'  => $event->orderId,
                'reason'    => $event->reason,
                'deep_link' => '/orders',
            ],
            trigger: 'rider.order_removed',
        );
    }
}
