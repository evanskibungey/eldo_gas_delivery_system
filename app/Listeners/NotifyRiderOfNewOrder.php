<?php

namespace App\Listeners;

use App\Events\RiderAssignedEvent;
use App\Jobs\SendRiderPushJob;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRiderOfNewOrder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(RiderAssignedEvent $event): void
    {
        $order = $event->order->load(['customer', 'size', 'brand']);
        $rider = $event->rider;

        // Push first: it deep-links straight into the acceptance screen and
        // wakes a backgrounded app, which the WebSocket cannot do. SMS stays
        // as the fallback for riders with no registered device or no data.
        SendRiderPushJob::dispatch(
            riderId: $rider->id,
            title: 'New delivery assigned',
            body: sprintf(
                '%s · %s. Tap to accept before the timer runs out.',
                $order->size?->name ?? 'Order',
                $order->order_number ?: ('#' . $order->id),
            ),
            data: [
                'type'                => 'order.assigned',
                'order_id'            => $order->id,
                'order_number'        => $order->order_number,
                'acceptance_deadline' => $order->rider_acceptance_deadline?->toIso8601String(),
                'deep_link'           => "/orders/{$order->id}",
            ],
            trigger: 'rider.order_assigned',
        );

        if (! $rider->phone) {
            return;
        }

        SendSmsJob::dispatch(
            $rider->phone,
            app(SmsTemplateService::class)->riderOrderDetails($order, $rider),
            'rider_assigned',
            'rider',
            $rider->id,
        );
    }
}
