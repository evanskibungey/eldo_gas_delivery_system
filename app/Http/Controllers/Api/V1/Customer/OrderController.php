<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\CancelOrderAction;
use App\Actions\PlaceOrderAction;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->paginate(15)
            ->through(fn (Order $o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'status'       => $o->status,
                'order_type'   => $o->order_type,
                'size_name'    => $o->size?->name,
                'brand_name'   => $o->brand?->name,
                'total_amount' => $o->total_amount,
                'created_at'   => $o->created_at->toIso8601String(),
                'can_cancel'   => $o->canBeCancelledByCustomer(),
                'can_rate'     => $o->status === 'delivered' && ! $o->rating,
                'can_track'    => $o->isActive() && $o->rider_id,
            ]);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $order->load(['size:id,name', 'brand:id,name', 'addons.addonItem', 'statusHistory', 'rider:id,name,phone,avg_rating,photo_path']);

        return response()->json([
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
            'delivery_lat'   => $order->delivery_lat,
            'delivery_lng'   => $order->delivery_lng,
            'delivery_notes' => $order->delivery_notes,
            'created_at'     => $order->created_at->toIso8601String(),
            'can_cancel'     => $order->canBeCancelledByCustomer(),
            'can_rate'       => $order->status === 'delivered' && ! $order->rating,
            'can_track'      => $order->isActive() && $order->rider_id,
            'addons'         => $order->addons->map(fn ($a) => ['name' => $a->addonItem?->name, 'price' => $a->price]),
            'history'        => $order->statusHistory->map(fn ($h) => ['status' => $h->status, 'at' => $h->created_at?->toIso8601String()]),
            'rider'          => $order->rider ? [
                'name'       => $order->rider->name,
                'phone'      => $order->rider->phone,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
                'lat'        => $order->rider->current_latitude,
                'lng'        => $order->rider->current_longitude,
            ] : null,
        ]);
    }

    public function store(Request $request, PlaceOrderAction $action): JsonResponse
    {
        $input = $request->validate([
            'order_type'     => 'required|in:swap,new_cylinder',
            'size_id'        => 'required|integer|exists:cylinder_sizes,id',
            'brand_id'       => 'required|integer|exists:gas_brands,id',
            'address_id'     => 'required|integer|exists:customer_addresses,id',
            'addon_ids'      => 'array',
            'addon_ids.*'    => 'integer|exists:addon_items,id',
            'payment_method' => 'required|in:cash,mpesa',
            'delivery_notes' => 'nullable|string|max:255',
        ]);

        $address = CustomerAddress::find($input['address_id']);

        if (! $address || $address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Address not found.'], 422);
        }

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
