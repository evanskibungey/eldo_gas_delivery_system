<?php

namespace App\Actions;

use App\Events\OrderPlacedEvent;
use App\Exceptions\OutOfStockException;
use App\Models\AddonItem;
use App\Models\Customer;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\OrderAddon;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\StockLevel;
use App\Models\SystemSetting;
use App\Services\AccessoryPricing;
use App\Services\Admin\StockService;
use App\Services\GasPointsService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceOrderAction
{
    /** An order of accessories alone, with no cylinder attached. */
    public const TYPE_ACCESSORY = 'accessory';


    public function __construct(
        private readonly GasPointsService $gasPoints,
        private readonly StockService $stock,
        private readonly AccessoryPricing $accessoryPricing,
    ) {}

    /**
     * One delivery fee for the whole order, however many cylinders are on it.
     *
     * A basket is one journey, so it is charged once. Under a flat rate the
     * answer is the configured amount — zero included, since a shop that
     * names a flat rate has answered the question. Under per-size pricing a
     * basket has several candidate fees and takes the highest, so delivering
     * a 6kg alongside a 50kg is never cheaper than the 50kg on its own.
     *
     * @param  list<float>  $perSizeFees
     */
    private function orderDeliveryFee(array $perSizeFees): float
    {
        $mode = SystemSetting::get('delivery_fee_mode', 'per_size');

        if (in_array($mode, ['flat_rate', 'per_km'], true)) {
            $base = SystemSetting::get('delivery_base_fee');

            return $base !== null && $base !== '' ? (float) $base : 0.0;
        }

        return $perSizeFees ? max($perSizeFees) : 0.0;
    }

    public function execute(Customer $customer, array $data): Order
    {
        $redemptionPoints = (int) ($data['redemption_points'] ?? 0);
        $data['size_id'] ??= null;
        $data['brand_id'] ??= null;
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
            // An accessory order names no cylinder, so there is no stock to
            // reserve and no cylinder price row to read. Everything below
            // that depends on a size is skipped rather than defaulted.
            $isAccessoryOnly = $data['order_type'] === self::TYPE_ACCESSORY;

            // Canonical lines, already merged by the caller so the same
            // cylinder twice arrives as one line of quantity two.
            $lines = $data['items'] ?? [];
            $priced = [];
            $itemsSubtotal = 0;

            if ($isAccessoryOnly) {
                $deliveryFee = $this->accessoryPricing->deliveryFee();
            } else {
                $perSizeFees = [];

                foreach ($lines as $line) {
                    $sizeId = (int) $line['size_id'];
                    $quantity = max(1, (int) ($line['quantity'] ?? 1));

                    $stock = StockLevel::where('size_id', $sizeId)
                        ->lockForUpdate()
                        ->first();

                    // Enough for this line, not merely more than none. A
                    // customer asking for three when two are left is refused
                    // and told so, rather than accepted and short-delivered.
                    if (! $stock || $stock->filled_count < $quantity) {
                        throw OutOfStockException::forSize(
                            CylinderSize::find($sizeId)?->name,
                            $quantity,
                            (int) ($stock->filled_count ?? 0),
                        );
                    }

                    $price = CylinderPrice::where('size_id', $sizeId)->firstOrFail();
                    $isSwap = $line['order_type'] === 'swap';
                    $gas = (int) ($isSwap ? $price->gas_refill_price : $price->new_gas_fill_price);
                    $cylinder = (int) ($isSwap ? 0 : $price->new_cylinder_price);

                    $priced[] = [
                        'size_id' => $sizeId,
                        'brand_id' => $line['brand_id'] ?? null,
                        'order_type' => $line['order_type'],
                        'quantity' => $quantity,
                        'gas_price' => $gas,
                        'cylinder_price' => $cylinder,
                        'line_total' => ($gas + $cylinder) * $quantity,
                    ];

                    $itemsSubtotal += ($gas + $cylinder) * $quantity;
                    $perSizeFees[] = (float) $price->delivery_fee;
                }

                $deliveryFee = $this->orderDeliveryFee($perSizeFees);
            }

            // The legacy columns mirror the first line so every read path
            // that has not moved to items yet keeps working. On a basket they
            // describe one of the cylinders rather than all of them — which
            // is the gap phase three closes, file by file.
            $lead = $priced[0] ?? null;
            $gasPrice = $lead['gas_price'] ?? 0;
            $cylinderPrice = $lead['cylinder_price'] ?? 0;

            $addonItems = ! empty($data['addon_ids'])
                ? AddonItem::whereIn('id', $data['addon_ids'])->get()
                : collect();
            $addonsTotal = $addonItems->sum('price');
            // Every line, not just the lead one. Summing the legacy columns
            // here would charge for one cylinder and deliver several.
            $subtotal = $itemsSubtotal + $deliveryFee + $addonsTotal;

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
                'size_id' => $lead['size_id'] ?? null,
                'brand_id' => $lead['brand_id'] ?? null,
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
                'delivery_label' => $data['delivery_label'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
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

            // Every line, priced above. The legacy columns still mirror the
            // first one, so this sits alongside them rather than instead of
            // them until the read paths move across.
            //
            // An accessory order has none: it carries no cylinder, and its
            // contents are the addon rows below.
            foreach ($priced as $line) {
                OrderItem::create($line + ['order_id' => $order->id]);
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
