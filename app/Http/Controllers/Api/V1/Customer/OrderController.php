<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\AddonItem;
use App\Models\CustomerAddress;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Services\ShopHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->paginate(15);

        $data = $orders->getCollection()->map(fn (Order $o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'status'       => $o->status,
                'order_type'   => $o->order_type,
                'size_name'    => $o->size?->name,
                'brand_name'   => $o->brand?->name,
                'total_amount' => $o->total_amount,
                'created_at'   => $o->created_at->toIso8601String(),
                'can_reorder'  => $o->canBeReorderedByCustomer(),
                'can_cancel'   => $o->canBeCancelledByCustomer(),
                'can_rate'     => $o->status === 'delivered' && ! $o->rating,
                'can_track'    => $o->isActive() && $o->rider_id,
            ])
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $order->load(['size:id,name', 'brand:id,name', 'addons.addonItem', 'statusHistory', 'rider:id,name,phone,avg_rating,photo_path,is_safety_certified']);

        return response()->json([
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'order_type'     => $order->order_type,
            'status'         => $order->status,
            'size_id'        => $order->size_id,
            'brand_id'       => $order->brand_id,
            'size_name'      => $order->size?->name,
            'brand_name'     => $order->brand?->name,
            'gas_price'           => $order->gas_price,
            'cylinder_price'      => $order->cylinder_price,
            'delivery_fee'        => $order->delivery_fee,
            'addons_total'        => $order->addons_total,
            'gaspoints_redeemed'  => $order->gaspoints_redeemed ?? 0,
            'gaspoints_discount'  => $order->gaspoints_discount ?? 0,
            'total_amount'        => $order->total_amount,
            'payment_method' => $order->payment_method,
            'delivery_lat'   => $order->delivery_lat,
            'delivery_lng'   => $order->delivery_lng,
            'delivery_notes' => $order->delivery_notes,
            'created_at'     => $order->created_at->toIso8601String(),
            'can_cancel'     => $order->canBeCancelledByCustomer(),
            'can_rate'       => $order->status === 'delivered' && ! $order->rating,
            'can_track'      => $order->isActive() && $order->rider_id,
            'addons'         => $order->addons->map(fn ($a) => [
                'addon_item_id' => $a->addon_item_id,
                'name'          => $a->addonItem?->name,
                'price'         => $a->price,
            ]),
            'delivery_photo_url'  => $order->delivery_photo_path
                ? Storage::url($order->delivery_photo_path)
                : null,
            'estimated_minutes'  => $this->computeEtaMinutes($order),
            'history'        => $order->statusHistory->map(fn ($h) => ['status' => $h->status, 'at' => $h->created_at?->toIso8601String()]),
            'rider'          => $order->rider ? [
                'name'                => $order->rider->name,
                'phone'               => $order->rider->phone,
                'avg_rating'          => $order->rider->avg_rating,
                'avatar_url'          => $order->rider->avatar_url,
                'lat'                 => $order->rider->current_latitude,
                'lng'                 => $order->rider->current_longitude,
                'is_safety_certified' => (bool) $order->rider->is_safety_certified,
            ] : null,
        ]);
    }

    public function store(Request $request, PlaceOrderAction $action, ShopHoursService $shopHours): JsonResponse
    {
        $input = $request->validate([
            'order_type'        => 'required|in:swap,new_cylinder',
            'size_id'           => 'required|integer|exists:cylinder_sizes,id',
            'brand_id'          => 'required|integer|exists:gas_brands,id',
            'address_id'        => 'required|integer|exists:customer_addresses,id',
            'addon_ids'         => 'array',
            'addon_ids.*'       => 'integer|exists:addon_items,id',
            'payment_method'    => 'required|in:cash,mpesa',
            'delivery_notes'    => 'nullable|string|max:255',
            'redemption_points' => 'nullable|integer|in:0,500,1000,2000,5000',
        ]);

        if (! $shopHours->isOpen()) {
            $shopStatus = $shopHours->status();

            return response()->json([
                'message' => 'Shop is closed right now.',
                'errors' => [
                    'shop_status' => [
                        sprintf(
                            'Orders can only be placed between %s and %s.',
                            $shopStatus['opens_at'],
                            $shopStatus['closes_at'],
                        ),
                    ],
                ],
                'shop_status' => $shopStatus,
            ], 422);
        }

        // Ensure the selected brand is available for the selected cylinder size.
        $size = CylinderSize::with('brands:id')->find($input['size_id']);
        if (! $size || ! $size->brands->contains('id', $input['brand_id'])) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => ['brand_id' => ['Selected brand is not available for this cylinder size.']],
            ], 422);
        }

        // Ensure every selected addon belongs to an active addon-group for this size.
        $addonIds = $input['addon_ids'] ?? [];
        if (! empty($addonIds)) {
            $validAddonCount = AddonItem::query()
                ->whereIn('id', $addonIds)
                ->where('is_active', true)
                ->whereHas('group', function ($q) use ($input) {
                    $q->where('size_id', $input['size_id'])
                        ->where('is_active', true);
                })
                ->count();

            if ($validAddonCount !== count(array_unique($addonIds))) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors'  => ['addon_ids' => ['One or more selected add-ons are invalid for this cylinder size.']],
                ], 422);
            }
        }

        $address = CustomerAddress::find($input['address_id']);

        if (! $address || $address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Address not found.'], 422);
        }

        $data = [
            'order_type'        => $input['order_type'],
            'size_id'           => $input['size_id'],
            'brand_id'          => $input['brand_id'],
            'addon_ids'         => $input['addon_ids'] ?? [],
            'payment_method'    => $input['payment_method'],
            'delivery_lat'      => $address->latitude,
            'delivery_lng'      => $address->longitude,
            'delivery_notes'    => $input['delivery_notes'] ?? null,
            'redemption_points' => (int) ($input['redemption_points'] ?? 0),
            'idempotency_key'   => $request->header('Idempotency-Key'),
        ];

        if (! empty($data['idempotency_key']) && strlen((string) $data['idempotency_key']) > 64) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => ['idempotency_key' => ['Idempotency key must not be longer than 64 characters.']],
            ], 422);
        }

        try {
            $order = $action->execute($request->user(), $data);
        } catch (OutOfStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['size_id' => [$e->getMessage()]]], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message'      => 'Order placed.',
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
        ], 201);
    }

    private function computeEtaMinutes(Order $order): ?int
    {
        if (! in_array($order->status, ['rider_assigned', 'picked_up', 'on_the_way'])) {
            return null;
        }

        $rider = $order->rider;
        if (! $rider || ! $rider->current_latitude || ! $rider->current_longitude) {
            return null;
        }

        // Haversine distance in km
        $lat1 = deg2rad((float) $rider->current_latitude);
        $lat2 = deg2rad((float) $order->delivery_lat);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $order->delivery_lng - (float) $rider->current_longitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $km = 6371 * 2 * asin(sqrt($a));

        $speedKmh = (float) config('shop.delivery_avg_speed_kmh', 30);
        if ($speedKmh <= 0) {
            return null;
        }

        return max(1, (int) ceil($km / $speedKmh * 60));
    }

    public function cancel(Request $request, Order $order, CancelOrderAction $cancelOrder): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $order->canBeCancelledByCustomer()) {
            return response()->json(['message' => 'This order can no longer be cancelled.'], 422);
        }

        $reason = $request->input('reason', 'Cancelled by customer');
        $cancelOrder->execute($order, $reason, 'customer', $request->user()->id);

        return response()->json(['message' => 'Order cancelled.']);
    }
}
