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
use App\Services\GasPointsService;
use App\Services\ShopHoursService;
use App\Support\OrderLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['size:id,name', 'brand:id,name,logo_path'])
            ->latest()
            ->paginate(15);

        $collection = $orders->getCollection();
        $brandIds = $collection->pluck('brand_id')->filter()->unique()->values()->all();
        $sizeIds = $collection->pluck('size_id')->filter()->unique()->values()->all();
        $pivotImages = [];

        if (! empty($brandIds) && ! empty($sizeIds)) {
            DB::table('brand_size_availability')
                ->whereIn('brand_id', $brandIds)
                ->whereIn('size_id', $sizeIds)
                ->select(['brand_id', 'size_id', 'image_path'])
                ->get()
                ->each(function ($row) use (&$pivotImages) {
                    $pivotImages[$row->brand_id . '_' . $row->size_id] = $row->image_path;
                });
        }

        $data = $collection->map(function (Order $order) use ($pivotImages) {
            $pivotPath = $pivotImages[$order->brand_id . '_' . $order->size_id] ?? null;
            $brandLogoUrl = $pivotPath ? asset('storage/' . $pivotPath) : $order->brand?->logo_url;

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'order_type' => $order->order_type,
                'size_name' => $order->size?->name,
                'brand_name' => $order->brand?->name,
                'brand_logo_url' => $brandLogoUrl,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at->toIso8601String(),
                'can_reorder' => $order->canBeReorderedByCustomer(),
                'can_cancel' => $order->canBeCancelledByCustomer(),
                'can_rate' => $order->status === OrderLifecycle::STATUS_DELIVERED && ! $order->rating,
                'can_track' => $order->isActive() && (bool) $order->rider_id,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $order->load(['size:id,name', 'brand:id,name,logo_path', 'addons.addonItem', 'statusHistory', 'rider:id,name,phone,avg_rating,photo_path,is_safety_certified,current_latitude,current_longitude']);

        $pivotPath = null;
        if ($order->brand_id && $order->size_id) {
            $pivotPath = DB::table('brand_size_availability')
                ->where('brand_id', $order->brand_id)
                ->where('size_id', $order->size_id)
                ->value('image_path');
        }
        $brandLogoUrl = $pivotPath ? asset('storage/' . $pivotPath) : $order->brand?->logo_url;

        return response()->json([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'size_id' => $order->size_id,
            'brand_id' => $order->brand_id,
            'size_name' => $order->size?->name,
            'brand_name' => $order->brand?->name,
            'brand_logo_url' => $brandLogoUrl,
            'gas_price' => $order->gas_price,
            'cylinder_price' => $order->cylinder_price,
            'delivery_fee' => $order->delivery_fee,
            'addons_total' => $order->addons_total,
            'gaspoints_redeemed' => $order->gaspoints_redeemed ?? 0,
            'gaspoints_discount' => $order->gaspoints_discount ?? 0,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'delivery_notes' => $order->delivery_notes,
            'created_at' => $order->created_at->toIso8601String(),
            'can_cancel' => $order->canBeCancelledByCustomer(),
            'can_rate' => $order->status === OrderLifecycle::STATUS_DELIVERED && ! $order->rating,
            'can_track' => $order->isActive() && (bool) $order->rider_id,
            'addons' => $order->addons->map(fn ($addon) => [
                'addon_item_id' => $addon->addon_item_id,
                'name' => $addon->addonItem?->name,
                'price' => $addon->price,
            ]),
            'delivery_photo_url' => $order->delivery_photo_path ? Storage::url($order->delivery_photo_path) : null,
            'estimated_minutes' => $this->computeEtaMinutes($order),
            'history' => $order->statusHistory->map(fn ($history) => [
                'status' => $history->status,
                'note' => $history->note,
                'at' => $history->created_at?->toIso8601String(),
            ]),
            'rider' => $order->rider ? [
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
                'lat' => $order->rider->current_latitude,
                'lng' => $order->rider->current_longitude,
                'is_safety_certified' => (bool) $order->rider->is_safety_certified,
            ] : null,
        ]);
    }

    public function store(Request $request, PlaceOrderAction $action, ShopHoursService $shopHours, GasPointsService $gasPoints): JsonResponse
    {
        $input = $request->validate([
            'order_type' => 'required|in:swap,new_cylinder',
            'size_id' => 'required|integer|exists:cylinder_sizes,id',
            'brand_id' => 'required|integer|exists:gas_brands,id',
            'address_id' => 'required|integer|exists:customer_addresses,id',
            'addon_ids' => 'array',
            'addon_ids.*' => 'integer|exists:addon_items,id',
            'payment_method' => 'required|in:cash,mpesa',
            'delivery_notes' => 'nullable|string|max:255',
            'redemption_points' => 'nullable|integer|min:0',
        ]);

        if (! $shopHours->isOpen()) {
            $shopStatus = $shopHours->status();

            return response()->json([
                'message' => 'Shop is closed right now.',
                'errors' => [
                    'shop_status' => [
                        sprintf('Orders can only be placed between %s and %s.', $shopStatus['opens_at'], $shopStatus['closes_at']),
                    ],
                ],
                'shop_status' => $shopStatus,
            ], 422);
        }

        $redemptionPoints = (int) ($input['redemption_points'] ?? 0);
        if ($redemptionPoints > 0 && ! array_key_exists($redemptionPoints, $gasPoints->redemptionTiersMap())) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['redemption_points' => ['Invalid redemption amount.']],
            ], 422);
        }

        $size = CylinderSize::with('brands:id')->find($input['size_id']);
        if (! $size || ! $size->brands->contains('id', $input['brand_id'])) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['brand_id' => ['Selected brand is not available for this cylinder size.']],
            ], 422);
        }

        $addonIds = $input['addon_ids'] ?? [];
        if (! empty($addonIds)) {
            $items = AddonItem::query()
                ->whereIn('id', $addonIds)
                ->where('is_active', true)
                ->with('group')
                ->get();

            if ($items->count() !== count(array_unique($addonIds))) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['addon_ids' => ['One or more selected add-ons are invalid for this cylinder size.']],
                ], 422);
            }

            foreach ($items as $item) {
                if (! $item->group || ! $item->group->is_active || (int) $item->group->size_id !== (int) $input['size_id']) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => ['addon_ids' => ['One or more selected add-ons are invalid for this cylinder size.']],
                    ], 422);
                }
            }

            foreach ($items->groupBy('group_id') as $groupItems) {
                $group = $groupItems->first()->group;
                if ($group && $group->selection_type === 'single' && $groupItems->count() > 1) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => ['addon_ids' => ["Only one item can be selected from {$group->name}."]],
                    ], 422);
                }
            }
        }

        $address = CustomerAddress::find($input['address_id']);
        if (! $address || $address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Address not found.'], 422);
        }

        $data = [
            'order_type' => $input['order_type'],
            'size_id' => $input['size_id'],
            'brand_id' => $input['brand_id'],
            'addon_ids' => $addonIds,
            'payment_method' => $input['payment_method'],
            'delivery_lat' => $address->latitude,
            'delivery_lng' => $address->longitude,
            'delivery_notes' => $input['delivery_notes'] ?? null,
            'redemption_points' => $redemptionPoints,
            'idempotency_key' => $request->header('Idempotency-Key'),
        ];

        if (! empty($data['idempotency_key']) && strlen((string) $data['idempotency_key']) > 64) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['idempotency_key' => ['Idempotency key must not be longer than 64 characters.']],
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
            'message' => 'Order placed.',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
            'payment_status' => $order->payment_status,
        ], 201);
    }

    private function computeEtaMinutes(Order $order): ?int
    {
        if (! in_array($order->status, OrderLifecycle::riderBusyStatuses(), true)) {
            return null;
        }

        $rider = $order->rider;
        if (! $rider || ! $rider->current_latitude || ! $rider->current_longitude) {
            return null;
        }

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
