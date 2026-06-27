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

        $this->logToInbox(
            customer: $order->customer,
            order: $order,
            title: $title,
            body: $body,
        );

        if ($devices->isEmpty()) {
            return;
        }

        foreach ($devices as $device) {
            try {
                $this->sendToDevice(
                    deviceToken: $device->token,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'order.status',
                        'order_id' => (string) $order->id,
                        'status' => $order->status,
                        'deep_link' => "/orders/{$order->id}/tracking",
                    ],
                );
            } catch (\Throwable $exception) {
                Log::warning('[push] FCM send failed', [
                    'device_id' => $device->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function copyFor(Order $order): array
    {
        $name = $order->customer?->name ?? 'there';
        $reference = $order->order_number ?: ('Order #' . $order->id);

        return match ($order->status) {
            'rider_assigned' => [
                'A rider is on the way',
                "Hi {$name}, a rider has accepted {$reference}.",
            ],
            'picked_up' => [
                'Your gas has been picked up',
                "The rider has left the shop with {$reference}.",
            ],
            'on_the_way' => [
                'Almost there',
                'Your rider is now on the way to your address.',
            ],
            'correction_in_progress' => [
                'We are correcting an issue',
                "A correction is in progress for {$reference}. We will keep you updated.",
            ],
            'delivered' => [
                'Delivered',
                "{$reference} was delivered. Tap to rate the experience.",
            ],
            'cancelled' => [
                'Order cancelled',
                "{$reference} was cancelled. No payment was taken.",
            ],
            default => [
                'Order update',
                "{$reference} status: {$order->status}",
            ],
        };
    }

    private function logToInbox(Customer $customer, Order $order, string $title, string $body): void
    {
        NotificationLog::create([
            'recipient_type' => 'customer',
            'recipient_id' => $customer->id,
            'channel' => 'push',
            'trigger' => 'order.status_updated',
            'title' => $title,
            'message' => $body,
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'deep_link' => "/orders/{$order->id}/tracking",
            ],
            'sent_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function sendToDevice(string $deviceToken, string $title, string $body, array $data): void
    {
        $serverKey = (string) config('services.firebase.server_key', '');
        if ($serverKey !== '') {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $deviceToken,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                ]);

            if ($response->successful()) {
                return;
            }

            throw new \RuntimeException(
                'FCM request failed: HTTP ' . $response->status() . ' ' . $response->body()
            );
        }

        Log::info('[push] would send', [
            'token' => substr($deviceToken, 0, 12) . '...',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}