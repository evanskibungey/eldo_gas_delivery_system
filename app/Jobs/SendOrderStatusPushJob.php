<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Device;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fan-out FCM push for an order-status change. One job per order; the
 * job iterates the customer's active device tokens and dispatches a
 * separate FCM HTTP v1 request per token (or batches via topic, see
 * the TODO at the bottom).
 *
 * The current implementation writes to `notifications_log` and logs
 * the payload at INFO level. To enable real push delivery in
 * production, install `kreait/laravel-firebase` and replace the
 * `// TODO: dispatch via Firebase` block with the actual SDK call.
 * The contract (payload shape, recipient lookup, write to the log)
 * stays the same — the only change is the wire send.
 */
class SendOrderStatusPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('customer:id,name')->find($this->orderId);
        if (! $order || ! $order->customer) {
            return;
        }

        [$title, $body] = $this->copyFor($order);

        $devices = Device::where('customer_id', $order->customer->id)
            ->orderByDesc('last_seen_at')
            ->get();

        // Always log to the inbox (channel=push) — even when the
        // customer has no registered devices the in-app inbox can still
        // surface the message on next refresh.
        $this->logToInbox(
            customer: $order->customer,
            order:    $order,
            title:    $title,
            body:     $body,
        );

        if ($devices->isEmpty()) {
            return;
        }

        foreach ($devices as $device) {
            try {
                $this->sendToDevice(
                    deviceToken: $device->token,
                    title:       $title,
                    body:        $body,
                    data:        [
                        'type'      => 'order.status',
                        'order_id'  => (string) $order->id,
                        'status'    => $order->status,
                        'deep_link' => "/order/tracking/{$order->id}",
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('[push] FCM send failed', [
                    'device_id' => $device->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Compose human copy for each status. Mirrors §7 of the concept doc
     * notification table.
     */
    private function copyFor(Order $order): array
    {
        $name = $order->customer?->name ?? 'there';
        return match ($order->status) {
            'rider_assigned' => [
                'A rider is on the way',
                "Hi $name, a rider has accepted order #{$order->id}.",
            ],
            'picked_up' => [
                'Your gas has been picked up',
                'The rider just left the shop with your cylinder.',
            ],
            'on_the_way' => [
                'Almost there',
                'Your rider is now on the way to your address.',
            ],
            'delivered' => [
                'Delivered',
                'Order #' . $order->id . ' delivered. Tap to rate the experience.',
            ],
            'cancelled' => [
                'Order cancelled',
                'Order #' . $order->id . ' was cancelled. No payment was taken.',
            ],
            default => [
                'Order update',
                "Order #{$order->id} status: {$order->status}",
            ],
        };
    }

    private function logToInbox(
        Customer $customer,
        Order $order,
        string $title,
        string $body,
    ): void {
        NotificationLog::create([
            'recipient_type' => 'customer',
            'recipient_id'   => $customer->id,
            'channel'        => 'push',
            'trigger'        => 'order.status_updated',
            'title'          => $title,
            'message'        => $body,
            'data'           => [
                'order_id'  => $order->id,
                'status'    => $order->status,
                'deep_link' => "/order/tracking/{$order->id}",
            ],
            'sent_at'        => now(),
            'created_at'     => now(),
        ]);
    }

    /**
     * The actual wire send. Currently logs the payload — drop in the
     * Firebase SDK call here for production:
     *
     *   use Kreait\Firebase\Messaging\CloudMessage;
     *   use Kreait\Firebase\Messaging\Notification;
     *   use Kreait\Laravel\Firebase\Facades\Firebase;
     *
     *   Firebase::messaging()->send(
     *       CloudMessage::withTarget('token', $deviceToken)
     *           ->withNotification(Notification::create($title, $body))
     *           ->withData($data),
     *   );
     */
    private function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array  $data,
    ): void {
        Log::info('[push] would send', [
            'token' => substr($deviceToken, 0, 12) . '…',
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ]);

        // TODO: dispatch via Firebase (see method docblock).
    }
}
