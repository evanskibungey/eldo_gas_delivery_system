<?php

namespace App\Services\Admin;

use App\Actions\PlaceOrderAction;
use App\Events\OrderDeliveredEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Customer\CustomerRegistrar;
use App\Support\OrderLifecycle;
use App\Support\ShopLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orders an admin creates for someone who did not use the app.
 *
 * Two shapes, one path:
 *
 *   phone    taken over the telephone, delivered as normal — identical to an
 *            app order once it exists, so it lands `pending` and waits for a
 *            rider like any other.
 *
 *   walk_in  a counter sale. The cylinder is already in the customer's hands
 *            and the money is already in the till, so recording it as `pending`
 *            would put a fictional job on the dispatch board. It is created and
 *            closed in one action.
 *
 * Pricing, stock and the out-of-stock guard all come from PlaceOrderAction
 * untouched — a counter sale must not be able to sell a cylinder that is not on
 * the shelf, and the till must not disagree with the app about the price.
 */
class AdminOrderCreator
{
    public function __construct(
        private readonly PlaceOrderAction $placeOrder,
        private readonly CustomerRegistrar $registrar,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated by StoreAdminOrderRequest.
     */
    public function create(array $data, ?int $adminId): Order
    {
        $channel = $data['channel'];
        $customer = $this->resolveCustomer($data);

        $order = $this->placeOrder->execute($customer, $this->orderData($data, $customer, $channel, $adminId));

        return $channel === 'walk_in'
            ? $this->closeCounterSale($order, $adminId)
            : $this->announcePhoneOrder($order);
    }

    /**
     * The caller, whether or not they already exist.
     *
     * A number already on file resolves to that record, so a regular walk-in
     * accumulates history under one customer rather than colliding with the
     * UNIQUE phone column on their second visit.
     */
    private function resolveCustomer(array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        return $this->registrar->findOrCreateByPhone(
            $data['customer_phone'],
            $data['customer_name'] ?? null,
            'admin',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(array $data, Customer $customer, string $channel, ?int $adminId): array
    {
        $lines = $this->mergeLines($data['items'] ?? []);

        $base = [
            // orders.order_type is a single column describing a basket that can
            // hold both kinds, so it mirrors the first line exactly as the
            // customer-facing callers do. The per-line truth is on order_items.
            'order_type' => $lines[0]['order_type'] ?? 'swap',
            'items' => $lines,
            'addon_ids' => $data['addon_ids'] ?? [],
            'payment_method' => $data['payment_method'],
            'delivery_notes' => $data['delivery_notes'] ?? null,
            'channel' => $channel,
            // The history is the record of who did what, and an admin taking a
            // call is not the customer placing an order.
            'actor_type' => 'admin',
            'actor_id' => $adminId,
        ];

        if ($channel === 'walk_in') {
            return $base + [
                // Nothing travelled, so nothing is charged for travel.
                'delivery_fee_override' => 0,
                // delivery_lat/lng are NOT NULL. The shop is where the goods
                // actually changed hands, so these are accurate rather than a
                // placeholder — see App\Support\ShopLocation.
                'delivery_lat' => ShopLocation::latitude(),
                'delivery_lng' => ShopLocation::longitude(),
                'delivery_label' => 'Counter sale',
                'delivery_address' => ShopLocation::label(),
            ];
        }

        $address = CustomerAddress::find($data['address_id'] ?? null);

        if (! $address) {
            throw ValidationException::withMessages([
                'address_id' => 'Choose a delivery address for a phone order.',
            ]);
        }

        // The id is validated as existing, not as theirs. Without this a typo
        // or a stale form sends the rider to somebody else's house.
        if ($address->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'address_id' => 'That address belongs to a different customer.',
            ]);
        }

        return $base + [
            'delivery_lat' => $address->latitude,
            'delivery_lng' => $address->longitude,
            'delivery_label' => $address->label,
            'delivery_address' => $address->description,
        ];
    }

    /**
     * The same size, brand and type twice is one line of quantity two.
     *
     * order_items carries unique(order_id, size_id, brand_id, order_type), so
     * two rows for the same cylinder is a constraint violation rather than a
     * bigger order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function mergeLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $key = ($line['size_id'] ?? '').':'.($line['brand_id'] ?? '').':'.($line['order_type'] ?? 'swap');
            $quantity = max(1, (int) ($line['quantity'] ?? 1));

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] += $quantity;

                continue;
            }

            $merged[$key] = [
                'size_id' => (int) $line['size_id'],
                'brand_id' => $line['brand_id'] ?? null,
                'order_type' => $line['order_type'] ?? 'swap',
                'quantity' => $quantity,
            ];
        }

        return array_values($merged);
    }

    /**
     * A counter sale, closed on the spot.
     *
     * Fires OrderDeliveredEvent because everything downstream of it genuinely
     * applies: the GasPoints award (the whole reason for recording walk-ins)
     * and the safety tip for somebody carrying a cylinder home. The delivery
     * thank-you is suppressed for this channel in favour of the receipt below,
     * which also names the items and the total.
     */
    private function closeCounterSale(Order $order, ?int $adminId): Order
    {
        DB::transaction(function () use ($order, $adminId): void {
            $order->update([
                'status' => OrderLifecycle::STATUS_DELIVERED,
                'payment_status' => 'collected',
                'delivered_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_DELIVERED,
                'note' => 'Counter sale - collected and paid at the shop',
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh(['customer', 'items.size', 'items.brand']);

        // SendWalkInReceipt hangs off this and sends the one message the
        // customer gets, after a delay so the points award has landed.
        event(new OrderDeliveredEvent($fresh));
        event(new OrderStatusUpdatedEvent($fresh));

        return $fresh;
    }

    private function announcePhoneOrder(Order $order): Order
    {
        $fresh = $order->fresh();

        // OrderPlacedEvent already refreshed the board, but the status channel
        // is what the customer's tracking page and any open order view listen
        // on.
        event(new OrderStatusUpdatedEvent($fresh));

        return $fresh;
    }

}
