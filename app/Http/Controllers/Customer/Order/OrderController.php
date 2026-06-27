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
use App\Models\SystemSetting;
use App\Services\GasPointsService;
use App\Support\OrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function build(): Response
    {
        $customer = auth('customer')->user();

        $rawSizes = CylinderSize::active()->ordered()
            ->with(['stockLevel', 'price', 'brands' => fn ($query) => $query->where('gas_brands.is_active', true)])
            ->get();

        $feeMode = SystemSetting::get('delivery_fee_mode', 'per_size');
        $globalFee = (float) SystemSetting::get('delivery_base_fee', '0.00');

        $sizes = $rawSizes->map(fn (CylinderSize $size) => [
            'id' => $size->id,
            'name' => $size->name,
            'weight_kg' => $size->weight_kg,
            'is_commercial' => $size->is_commercial,
            'in_stock' => ($size->stockLevel?->filled_count ?? 0) > 0,
            'swap_price' => $size->price?->gas_refill_price,
            'new_price' => ($size->price?->new_cylinder_price ?? 0) + ($size->price?->new_gas_fill_price ?? 0),
            'delivery_fee' => $feeMode === 'per_size' ? ($size->price?->delivery_fee ?? 0) : $globalFee,
        ]);

        $brandsBySize = $rawSizes->mapWithKeys(fn (CylinderSize $size) => [
            (string) $size->id => $size->brands->map(fn ($brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'logo_url' => $brand->logo_url,
            ])->values(),
        ]);

        $addonsBySize = AddonGroup::active()->ordered()
            ->whereIn('size_id', $rawSizes->pluck('id'))
            ->with(['items' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->groupBy('size_id')
            ->map(fn ($groups) => $groups->map(fn (AddonGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'selection_type' => $group->selection_type,
                'items' => $group->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'photo_url' => $item->photo_url,
                ])->values(),
            ])->values());

        $addresses = $customer->addresses()->get()->map(fn ($address) => [
            'id' => $address->id,
            'label' => $address->label,
            'description' => $address->description,
            'is_default' => $address->is_default,
        ]);

        $lastOrder = $customer->orders()->latest()->first();
        $pointsConfig = $this->gasPoints->config();

        return Inertia::render('Customer/Order/OrderBuilder', [
            'sizes' => $sizes->values(),
            'brands_by_size' => $brandsBySize,
            'addons_by_size' => $addonsBySize,
            'addresses' => $addresses->values(),
            'default_address' => $customer->defaultAddress?->id,
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
            'gaspoints_balance' => (int) $customer->gaspoints_balance,
            'gaspoints_enabled' => $pointsConfig['enabled'],
            'gaspoints_redemption_tiers' => array_map(fn ($tier) => [
                'points' => $tier['points'],
                'kes' => $tier['kes'],
            ], $pointsConfig['redemption_tiers']),
            'gaspoints_rules' => $pointsConfig['rules'],
            'prefill' => $lastOrder ? [
                'order_type' => $lastOrder->order_type,
                'size_id' => $lastOrder->size_id,
                'brand_id' => $lastOrder->brand_id,
            ] : null,
        ]);
    }

    public function placeOrder(PlaceOrderRequest $request, PlaceOrderAction $action): RedirectResponse
    {
        $input = $request->validated();
        $address = CustomerAddress::findOrFail($input['address_id']);

        abort_unless($address->customer_id === auth('customer')->id(), 403);

        $data = [
            'order_type' => $input['order_type'],
            'size_id' => $input['size_id'],
            'brand_id' => $input['brand_id'],
            'addon_ids' => $input['addon_ids'] ?? [],
            'payment_method' => $input['payment_method'],
            'delivery_lat' => $address->latitude,
            'delivery_lng' => $address->longitude,
            'delivery_notes' => $input['delivery_notes'] ?? null,
            'redemption_points' => (int) ($input['redemption_points'] ?? 0),
        ];

        try {
            $order = $action->execute(auth('customer')->user(), $data);
        } catch (OutOfStockException $exception) {
            throw ValidationException::withMessages(['size_id' => $exception->getMessage()]);
        }

        return redirect()->route('customer.order.confirmation', $order);
    }

    public function confirmation(Order $order): Response
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        return Inertia::render('Customer/Order/Confirmation', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at->format('D, d M Y H:i'),
            ],
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
        ]);
    }

    public function index(): Response
    {
        $orders = auth('customer')->user()
            ->orders()
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->paginate(15)
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'order_type' => $order->order_type,
                'size_name' => $order->size?->name,
                'brand_name' => $order->brand?->name,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at->format('d M Y'),
                'can_cancel' => $order->canBeCancelledByCustomer(),
                'can_rate' => $order->status === OrderLifecycle::STATUS_DELIVERED && ! $order->rating,
                'can_track' => $order->isActive() && (bool) $order->rider_id,
            ]);

        return Inertia::render('Customer/Order/History', ['orders' => $orders]);
    }

    public function show(Order $order): Response
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        $order->load([
            'size:id,name',
            'brand:id,name',
            'addons.addonItem',
            'statusHistory',
            'rider:id,name,phone,avg_rating,photo_path,is_safety_certified',
        ]);

        return Inertia::render('Customer/Order/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'size_name' => $order->size?->name,
                'brand_name' => $order->brand?->name,
                'gas_price' => $order->gas_price,
                'cylinder_price' => $order->cylinder_price,
                'delivery_fee' => $order->delivery_fee,
                'addons_total' => $order->addons_total,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'delivery_notes' => $order->delivery_notes,
                'created_at' => $order->created_at->format('D, d M Y H:i'),
                'can_cancel' => $order->canBeCancelledByCustomer(),
                'can_rate' => $order->status === OrderLifecycle::STATUS_DELIVERED && ! $order->rating,
                'can_track' => $order->isActive() && (bool) $order->rider_id,
                'addons' => $order->addons->map(fn ($addon) => [
                    'name' => $addon->addonItem?->name,
                    'price' => $addon->price,
                ]),
                'history' => $order->statusHistory->map(fn ($history) => [
                    'status' => $history->status,
                    'note' => $history->note,
                    'at' => $history->created_at?->format('d M H:i'),
                ]),
                'rider' => $order->rider ? [
                    'name' => $order->rider->name,
                    'phone' => $order->rider->phone,
                    'avg_rating' => $order->rider->avg_rating,
                    'avatar_url' => $order->rider->avatar_url,
                ] : null,
            ],
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
        ]);
    }

    public function cancel(Request $request, Order $order, CancelOrderAction $cancelOrder): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('cancel', $order), 403);

        if (in_array($order->status, [
            OrderLifecycle::STATUS_PICKED_UP,
            OrderLifecycle::STATUS_ON_THE_WAY,
            OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS,
        ], true)) {
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