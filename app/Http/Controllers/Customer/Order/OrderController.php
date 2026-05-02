<?php

namespace App\Http\Controllers\Customer\Order;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Order\PlaceOrderRequest;
use App\Models\AddonGroup;
use App\Models\CustomerAddress;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    // ── Single-page order builder ────────────────────────────────────────────

    public function build(): Response
    {
        $customer = auth('customer')->user();

        $rawSizes = CylinderSize::active()->ordered()
            ->with(['stockLevel', 'price', 'brands' => fn ($q) => $q->where('gas_brands.is_active', true)])
            ->get();

        $feeMode    = SystemSetting::get('delivery_fee_mode', 'per_size');
        $globalFee  = (float) SystemSetting::get('delivery_base_fee', '0.00');

        $sizes = $rawSizes->map(fn (CylinderSize $s) => [
            'id'            => $s->id,
            'name'          => $s->name,
            'weight_kg'     => $s->weight_kg,
            'is_commercial' => $s->is_commercial,
            'in_stock'      => ($s->stockLevel?->filled_count ?? 0) > 0,
            'swap_price'    => $s->price?->gas_refill_price,
            'new_price'     => ($s->price?->new_cylinder_price ?? 0) + ($s->price?->new_gas_fill_price ?? 0),
            'delivery_fee'  => $feeMode === 'per_size' ? ($s->price?->delivery_fee ?? 0) : $globalFee,
        ]);

        $brandsBySize = $rawSizes->mapWithKeys(fn (CylinderSize $s) => [
            (string) $s->id => $s->brands->map(fn ($b) => [
                'id'       => $b->id,
                'name'     => $b->name,
                'logo_url' => $b->logo_url,
            ])->values(),
        ]);

        $addonsBySize = AddonGroup::active()->ordered()
            ->whereIn('size_id', $rawSizes->pluck('id'))
            ->with(['items' => fn ($q) => $q->active()->ordered()])
            ->get()
            ->groupBy('size_id')
            ->map(fn ($groups) => $groups->map(fn (AddonGroup $g) => [
                'id'             => $g->id,
                'name'           => $g->name,
                'selection_type' => $g->selection_type,
                'items'          => $g->items->map(fn ($i) => [
                    'id'          => $i->id,
                    'name'        => $i->name,
                    'description' => $i->description,
                    'price'       => $i->price,
                    'photo_url'   => $i->photo_url,
                ])->values(),
            ])->values());

        $addresses = $customer->addresses()->get()->map(fn ($a) => [
            'id'          => $a->id,
            'label'       => $a->label,
            'description' => $a->description,
            'is_default'  => $a->is_default,
        ]);

        $lastOrder = $customer->orders()->latest()->first();

        return Inertia::render('Customer/Order/OrderBuilder', [
            'sizes'           => $sizes->values(),
            'brands_by_size'  => $brandsBySize,
            'addons_by_size'  => $addonsBySize,
            'addresses'       => $addresses->values(),
            'default_address' => $customer->defaultAddress?->id,
            'mpesa_till'      => env('MPESA_TILL_NUMBER', ''),
            'prefill'         => $lastOrder ? [
                'order_type' => $lastOrder->order_type,
                'size_id'    => $lastOrder->size_id,
                'brand_id'   => $lastOrder->brand_id,
            ] : null,
        ]);
    }

    // ── Place order ──────────────────────────────────────────────────────────

    public function placeOrder(PlaceOrderRequest $request, PlaceOrderAction $action): RedirectResponse
    {
        $input   = $request->validated();
        $address = CustomerAddress::findOrFail($input['address_id']);

        abort_unless($address->customer_id === auth('customer')->id(), 403);

        $data = [
            'order_type'     => $input['order_type'],
            'size_id'        => $input['size_id'],
            'brand_id'       => $input['brand_id'],
            'addon_ids'      => $input['addon_ids'] ?? [],
            'payment_method' => $input['payment_method'],
            'delivery_lat'   => $address->latitude,
            'delivery_lng'   => $address->longitude,
            'delivery_notes' => $input['delivery_notes'] ?? null,
        ];

        try {
            $order = $action->execute(auth('customer')->user(), $data);
        } catch (OutOfStockException $e) {
            throw ValidationException::withMessages(['size_id' => $e->getMessage()]);
        }

        return redirect()->route('customer.order.confirmation', $order);
    }

    // ── Confirmation ─────────────────────────────────────────────────────────

    public function confirmation(Order $order): Response
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        return Inertia::render('Customer/Order/Confirmation', [
            'order' => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'total_amount'   => $order->total_amount,
                'payment_method' => $order->payment_method,
                'created_at'     => $order->created_at->format('D, d M Y · H:i'),
            ],
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
        ]);
    }

    // ── Order history ─────────────────────────────────────────────────────────

    public function index(): Response
    {
        $orders = auth('customer')->user()
            ->orders()->with(['size:id,name', 'brand:id,name'])->latest()->paginate(15)
            ->through(fn (Order $o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'status'       => $o->status,
                'order_type'   => $o->order_type,
                'size_name'    => $o->size?->name,
                'brand_name'   => $o->brand?->name,
                'total_amount' => $o->total_amount,
                'created_at'   => $o->created_at->format('d M Y'),
                'can_cancel'   => $o->canBeCancelledByCustomer(),
                'can_rate'     => $o->status === 'delivered' && ! $o->rating,
                'can_track'    => $o->isActive() && $o->rider_id,
            ]);

        return Inertia::render('Customer/Order/History', ['orders' => $orders]);
    }

    // ── Order detail ──────────────────────────────────────────────────────────

    public function show(Order $order): Response
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        $order->load(['size:id,name', 'brand:id,name', 'addons.addonItem', 'statusHistory', 'rider:id,name,phone,avg_rating,photo_path']);

        return Inertia::render('Customer/Order/Show', [
            'order' => [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'order_type'     => $order->order_type,
                'status'         => $order->status,
                'size_name'      => $order->size?->name,
                'brand_name'     => $order->brand?->name,
                'gas_price'      => $order->gas_price,
                'cylinder_price' => $order->cylinder_price,
                'delivery_fee'   => $order->delivery_fee,
                'addons_total'   => $order->addons_total,
                'total_amount'   => $order->total_amount,
                'payment_method' => $order->payment_method,
                'delivery_notes' => $order->delivery_notes,
                'created_at'     => $order->created_at->format('D, d M Y · H:i'),
                'can_cancel'     => $order->canBeCancelledByCustomer(),
                'can_rate'       => $order->status === 'delivered' && ! $order->rating,
                'can_track'      => $order->isActive() && $order->rider_id,
                'addons'         => $order->addons->map(fn ($a) => ['name' => $a->addonItem?->name, 'price' => $a->price]),
                'history'        => $order->statusHistory->map(fn ($h) => ['status' => $h->status, 'at' => $h->created_at?->format('d M H:i')]),
                'rider'          => $order->rider ? [
                    'name'       => $order->rider->name,
                    'phone'      => $order->rider->phone,
                    'avg_rating' => $order->rider->avg_rating,
                    'avatar_url' => $order->rider->avatar_url,
                ] : null,
            ],
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
        ]);
    }

    // ── Cancel (9.5) ──────────────────────────────────────────────────────────

    public function cancel(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('cancel', $order), 403);

        // 9.5 — picked_up or later: cannot cancel in-app
        if (in_array($order->status, ['picked_up', 'on_the_way', 'correction_in_progress'])) {
            return back()->with('error', 'Your order is already on the way. Please contact the shop directly.');
        }

        if (! $order->canBeCancelledByCustomer()) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $reason = $request->input('reason', 'Cancelled by customer');

        $cancelOrder->execute($order, $reason, 'customer', auth('customer')->id());

        return redirect()->route('customer.orders.index')->with('success', 'Order cancelled.');
    }
}
