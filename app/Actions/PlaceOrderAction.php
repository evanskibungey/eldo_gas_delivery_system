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
use App\Models\SystemSetting;
use App\Services\Admin\StockService;
use App\Services\GasPointsService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    public function __construct(
        private readonly GasPointsService $gasPoints,
        private readonly StockService $stock,
    ) {}

    public function execute(Customer $customer, array $data): Order
    {
        $redemptionPoints = (int) ($data['redemption_points'] ?? 0);
        $idempotencyKey = isset($data['idempotency_key'])
            ? trim((string) $data['idempotency_key'])
            : null;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Order::query()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($redemptionPoints > 0) {
            if (! $this->gasPoints->isEnabled()) {
                throw ValidationException::withMessages([
                    'redemption_points' => ['GasPoints redemption is currently unavailable.'],
                ]);
            }

            if (! array_key_exists($redemptionPoints, $this->gasPoints->redemptionTiersMap())) {
                throw ValidationException::withMessages([
                    'redemption_points' => ['Invalid redemption amount.'],
                ]);
            }

            if ($this->gasPoints->getBalance($customer) < $redemptionPoints) {
                throw ValidationException::withMessages([
                    'redemption_points' => ['Insufficient GasPoints balance.'],
                ]);
            }
        }

        $redemptionRewardKey = $idempotencyKey
            ? "checkout:redemption:customer:{$customer->id}:{$idempotencyKey}"
            : null;

        $order = DB::transaction(function () use ($customer, $data, $redemptionPoints, $idempotencyKey, $redemptionRewardKey) {
            $stock = StockLevel::where('size_id', $data['size_id'])
                ->lockForUpdate()
                ->first();

            if (! $stock || $stock->filled_count <= 0) {
                throw new OutOfStockException();
            }

            $price = CylinderPrice::where('size_id', $data['size_id'])->firstOrFail();
            $isSwap = $data['order_type'] === 'swap';
            $gasPrice = $isSwap ? $price->gas_refill_price : $price->new_gas_fill_price;
            $cylinderPrice = $isSwap ? 0 : $price->new_cylinder_price;
            $feeMode = SystemSetting::get('delivery_fee_mode', 'per_size');
            $deliveryFee = match ($feeMode) {
                'flat_rate', 'per_km' => (float) SystemSetting::get('delivery_base_fee', '0.00'),
                default => $price->delivery_fee,
            };

            $addonItems = ! empty($data['addon_ids'])
                ? AddonItem::whereIn('id', $data['addon_ids'])->get()
                : collect();
            $addonsTotal = $addonItems->sum('price');
            $subtotal = $gasPrice + $cylinderPrice + $deliveryFee + $addonsTotal;

            $gaspointsDiscount = 0;
            if ($redemptionPoints > 0) {
                $lockedCustomer = Customer::lockForUpdate()->find($customer->id);
                if (! $lockedCustomer || $lockedCustomer->gaspoints_balance < $redemptionPoints) {
                    throw ValidationException::withMessages([
                        'redemption_points' => ['Insufficient GasPoints balance.'],
                    ]);
                }

                $gaspointsDiscount = $this->gasPoints->redemptionTiersMap()[$redemptionPoints];
                $redeemed = $this->gasPoints->redeem(
                    $lockedCustomer,
                    $redemptionPoints,
                    "Redeemed {$redemptionPoints} pts at checkout",
                    null,
                    $redemptionRewardKey,
                    'checkout_redemption',
                );

                if (! $redeemed) {
                    throw ValidationException::withMessages([
                        'redemption_points' => ['Insufficient GasPoints balance.'],
                    ]);
                }
            }

            $total = max(0, $subtotal - $gaspointsDiscount);

            $order = Order::create([
                'order_number' => 'TMP-' . Str::upper(Str::random(16)),
                'customer_id' => $customer->id,
                'size_id' => $data['size_id'],
                'brand_id' => $data['brand_id'],
                'order_type' => $data['order_type'],
                'status' => OrderLifecycle::STATUS_PENDING,
                'gas_price' => $gasPrice,
                'cylinder_price' => $cylinderPrice,
                'delivery_fee' => $deliveryFee,
                'addons_total' => $addonsTotal,
                'gaspoints_redeemed' => $redemptionPoints,
                'gaspoints_discount' => $gaspointsDiscount,
                'total_amount' => $total,
                'payment_method' => $data['payment_method'],
                'delivery_lat' => $data['delivery_lat'],
                'delivery_lng' => $data['delivery_lng'],
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ]);

            $order->update([
                'order_number' => 'EG-' . now()->format('Ymd') . '-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
            ]);

            if ($redemptionPoints > 0 && $redemptionRewardKey) {
                $order->customer->gasPointsTransactions()
                    ->where('reward_key', $redemptionRewardKey)
                    ->update(['order_id' => $order->id]);
            } elseif ($redemptionPoints > 0) {
                $order->customer->gasPointsTransactions()
                    ->where('type', 'redeemed')
                    ->whereNull('order_id')
                    ->latest('id')
                    ->limit(1)
                    ->update(['order_id' => $order->id]);
            }

            foreach ($addonItems as $item) {
                OrderAddon::create([
                    'order_id' => $order->id,
                    'addon_item_id' => $item->id,
                    'price' => $item->price,
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_PENDING,
                'actor_type' => 'customer',
                'actor_id' => $customer->id,
                'created_at' => now(),
            ]);

            $this->stock->deductForOrder($order);

            return $order;
        });

        event(new OrderPlacedEvent($order));

        return $order;
    }
}
