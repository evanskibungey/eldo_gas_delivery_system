<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

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
            'items.size:id,name,image_path',
            'items.brand:id,name',
        ]);

        $items = $this->itemsPayload();

        return [
            'id'             => $this->order->id,
            'order_number'   => $this->order->order_number,
            'status'         => $this->order->status,
            'order_type'     => $this->order->order_type,
            'total_amount'   => $this->order->total_amount,
            'payment_method' => $this->order->payment_method,
            'size_name'      => $this->order->size?->name,
            'brand_name'     => $this->order->brand?->name,
            // The whole basket, not just the cylinder that happened to be
            // first. The alert is what a dispatcher reads before choosing a
            // rider, and "13kg · Total" for a load of three sends the wrong
            // one.
            'items_summary'  => $this->order->itemsSummary(),
            'cylinder_count' => $this->order->cylinderCount(),
            // Every line with its own photo. A mixed basket — a 6kg Total and a
            // 13kg K-Gas — is two different cylinders to pull off the shelf,
            // and one thumbnail cannot show that.
            'items'          => $items,
            // The first line's photo, for the compact single-item layout.
            // Falls back to the order's own size/brand when there are no item
            // rows, which is how the chips below it already behave — an order
            // predating order_items should not lose its picture.
            'image_url'      => $items[0]['image_url'] ?? $this->orderLevelImageUrl(),
            'customer_name'  => $this->order->customer?->name,
            'customer_phone' => $this->order->customer?->phone,
            'address'        => $this->order->delivery_address ?: $this->order->delivery_label,
            'created_ago'    => $this->order->created_at->diffForHumans(),
        ];
    }

    /**
     * Every line on the order, in the order the customer built it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function itemsPayload(): array
    {
        $brandImages = $this->brandImagePaths();

        return $this->order->items->map(fn (OrderItem $item) => [
            'id' => $item->id,
            'size_name' => $item->size?->name,
            'brand_name' => $item->brand?->name,
            'order_type' => $item->order_type,
            'quantity' => $item->quantity,
            'label' => $item->label(),
            'image_url' => $this->resolveImageUrl(
                $item->size?->image_path,
                $brandImages["{$item->size_id}:{$item->brand_id}"] ?? null,
            ),
        ])->values()->all();
    }

    /**
     * Brand-specific cylinder photos for every (size, brand) pair on this
     * order, as one query.
     *
     * Resolved in bulk rather than per line: this runs inside a queued job for
     * every order placed, and a four-line basket should not cost four extra
     * round trips.
     *
     * @return array<string, string> keyed "sizeId:brandId"
     */
    private function brandImagePaths(): array
    {
        $sizeIds = $this->order->items->pluck('size_id')->filter()->unique();
        $brandIds = $this->order->items->pluck('brand_id')->filter()->unique();

        if ($sizeIds->isEmpty() || $brandIds->isEmpty()) {
            return [];
        }

        return DB::table('brand_size_availability')
            ->whereIn('size_id', $sizeIds)
            ->whereIn('brand_id', $brandIds)
            ->whereNotNull('image_path')
            ->get(['size_id', 'brand_id', 'image_path'])
            ->mapWithKeys(fn ($row) => ["{$row->size_id}:{$row->brand_id}" => $row->image_path])
            ->all();
    }

    /**
     * A brand carries its own photo per size (Lake Gas 6kg looks nothing like
     * Pro Gas 6kg), so that wins where it exists; the size's generic image is
     * the fallback. Mirrors what the customer saw when they chose it.
     */
    private function resolveImageUrl(?string $sizePath, ?string $brandPath): ?string
    {
        $path = $brandPath ?: $sizePath;

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * The photo from the order's own size/brand columns.
     *
     * Only reached when the order has no item rows: an accessory order, which
     * has no cylinder and correctly gets nothing, or an order predating the
     * order_items backfill.
     */
    private function orderLevelImageUrl(): ?string
    {
        $size = $this->order->size;

        if (! $size) {
            return null;
        }

        $brandPath = $this->order->brand_id
            ? $size->brands()
                ->where('gas_brands.id', $this->order->brand_id)
                ->value('brand_size_availability.image_path')
            : null;

        return $this->resolveImageUrl($size->image_path, $brandPath);
    }

}
