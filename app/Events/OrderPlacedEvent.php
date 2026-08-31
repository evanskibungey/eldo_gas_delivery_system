<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, when a customer places an order.
 *
 * It carried an `excludeRiderIds` list while dispatch was automatic: a decline
 * or a lapsed acceptance window re-fired this event to trigger another
 * auto-assign hop, and the list stopped the order boomeranging back to the
 * rider who had just refused it. Assignment is manual now, so nothing re-fires
 * this — those paths announce an OrderStatusUpdatedEvent instead, which returns
 * the order to the admin board as pending.
 */
class OrderPlacedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing([
            'customer:id,name,phone',
            'size:id,name,image_path',
            'brand:id,name',
        ]);

        return [
            'id'             => $this->order->id,
            'order_number'   => $this->order->order_number,
            'status'         => $this->order->status,
            'order_type'     => $this->order->order_type,
            'total_amount'   => $this->order->total_amount,
            'payment_method' => $this->order->payment_method,
            'size_name'      => $this->order->size?->name,
            'brand_name'     => $this->order->brand?->name,
            'image_url'      => $this->productImageUrl(),
            'customer_name'  => $this->order->customer?->name,
            'customer_phone' => $this->order->customer?->phone,
            'address'        => $this->order->delivery_address ?: $this->order->delivery_label,
            'created_ago'    => $this->order->created_at->diffForHumans(),
        ];
    }

    /**
     * Picture of what was actually ordered.
     *
     * A brand carries its own photo per size (Lake Gas 6kg looks nothing like
     * Pro Gas 6kg), so that pivot image wins where it exists; the size's
     * generic image is the fallback. Mirrors what the customer saw when they
     * chose it.
     */
    private function productImageUrl(): ?string
    {
        $size = $this->order->size;

        if (! $size) {
            return null;
        }

        if ($this->order->brand_id) {
            $brandPath = $size->brands()
                ->where('gas_brands.id', $this->order->brand_id)
                ->value('brand_size_availability.image_path');

            if ($brandPath) {
                return asset('storage/'.$brandPath);
            }
        }

        return $size->image_path
            ? asset('storage/'.$size->image_path)
            : null;
    }
}
