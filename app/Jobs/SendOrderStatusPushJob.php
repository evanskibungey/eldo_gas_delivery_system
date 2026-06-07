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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fan-out push for an order-status change.
 *
 * Delivery strategy:
 * - Always write to notifications_log for in-app inbox continuity.
 * - Attempt real push via FCM legacy endpoint when FIREBASE_SERVER_KEY exists.
 * - Fall back to structured logging when no key is configured.
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

    private function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array  $data,
    ): void {
        $serverKey = (string) config('services.firebase.server_key', '');
        if ($serverKey !== '') {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to'           => $deviceToken,
                    'priority'     => 'high',
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data'         => $data,
                ]);

            if ($response->successful()) {
                return;
            }

            throw new \RuntimeException(
                'FCM request failed: HTTP ' . $response->status() . ' ' . $response->body()
            );
        }

        Log::info('[push] would send', [
            'token' => substr($deviceToken, 0, 12) . '…',
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ]);
    }
}
