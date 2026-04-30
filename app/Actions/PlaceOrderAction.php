<?php

namespace App\Actions;

use App\Events\OrderPlacedEvent;
use App\Exceptions\OutOfStockException;
use App\Models\AddonItem;
use App\Models\Customer;
use App\Models\CylinderPrice;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\OrderStatusHistory;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class PlaceOrderAction
{
    public function execute(Customer $customer, array $data): Order
    {
        $order = DB::transaction(function () use ($customer, $data) {
            // Lock stock row and validate availability
            $stock = StockLevel::where('size_id', $data['size_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock || $stock->filled_count <= 0) {
                throw new OutOfStockException();
            }

            // Price snapshot at time of order
            $price         = CylinderPrice::where('size_id', $data['size_id'])->firstOrFail();
            $isSwap        = $data['order_type'] === 'swap';
            $gasPrice      = $isSwap ? $price->gas_refill_price : $price->new_gas_fill_price;
            $cylinderPrice = $isSwap ? 0 : $price->new_cylinder_price;
            $deliveryFee   = $price->delivery_fee;

            // Addons — available for both swap and new_cylinder orders
            $addonItems  = ! empty($data['addon_ids'])
                ? AddonItem::whereIn('id', $data['addon_ids'])->get()
                : collect();
            $addonsTotal = $addonItems->sum('price');

            $total = $gasPrice + $cylinderPrice + $deliveryFee + $addonsTotal;

            $order = Order::create([
                'order_number'   => 'TEMP',
                'customer_id'    => $customer->id,
                'size_id'        => $data['size_id'],
                'brand_id'       => $data['brand_id'],
                'order_type'     => $data['order_type'],
                'status'         => 'pending',
                'gas_price'      => $gasPrice,
                'cylinder_price' => $cylinderPrice,
                'delivery_fee'   => $deliveryFee,
                'addons_total'   => $addonsTotal,
                'total_amount'   => $total,
                'payment_method' => $data['payment_method'],
                'delivery_lat'   => $data['delivery_lat'],
                'delivery_lng'   => $data['delivery_lng'],
                'delivery_notes' => $data['delivery_notes'] ?? null,
            ]);

            // Stable, human-readable order number using auto-increment ID
            $order->update([
                'order_number' => 'EG-' . now()->format('Ymd') . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
            ]);

            foreach ($addonItems as $item) {
                OrderAddon::create([
                    'order_id'      => $order->id,
                    'addon_item_id' => $item->id,
                    'price'         => $item->price,
                ]);
            }

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'actor_type' => 'customer',
                'actor_id'   => $customer->id,
                'created_at' => now(),
            ]);

            return $order;
        });

        event(new OrderPlacedEvent($order));

        return $order;
    }
}
