<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Admin\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function active(Request $request): JsonResponse
    {
        $rider = $request->user();

        $orders = Order::where('rider_id', $rider->id)
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->with(['customer:id,name,phone', 'size:id,name'])
            ->latest()
            ->get()
            ->map(fn ($o) => $this->formatOrder($o));

        return response()->json(['data' => $orders]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->rider_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $order->load(['customer:id,name,phone', 'size:id,name', 'brand:id,name', 'addons.addonItem', 'statusHistory']);

        return response()->json($this->formatOrderDetail($order));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if ($order->rider_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'status'            => 'required|in:picked_up,on_the_way,delivered',
            'payment_collected' => 'boolean',
        ]);

        $allowed = [
            'rider_assigned'  => ['picked_up'],
            'picked_up'       => ['on_the_way'],
            'on_the_way'      => ['delivered'],
        ];

        if (! in_array($data['status'], $allowed[$order->status] ?? [])) {
            return response()->json(['message' => "Cannot transition from {$order->status} to {$data['status']}."], 422);
        }

        $updates = ['status' => $data['status']];

        if ($data['status'] === 'picked_up') {
            $updates['picked_up_at'] = now();
            $this->stock->autoDeductForOrder($order);
        } elseif ($data['status'] === 'on_the_way') {
            $updates['on_the_way_at'] = now();
        } elseif ($data['status'] === 'delivered') {
            $updates['delivered_at'] = now();

            if (! empty($data['payment_collected']) && $order->payment_method === 'cash') {
                $updates['payment_status'] = 'collected';
            }
        }

        $order->update($updates);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'status'     => $data['status'],
            'actor_type' => 'rider',
            'actor_id'   => $request->user()->id,
            'created_at' => now(),
        ]);

        if ($data['status'] === 'delivered') {
            event(new \App\Events\OrderDeliveredEvent($order->fresh()));
        }

        return response()->json(['message' => 'Status updated.', 'status' => $data['status']]);
    }

    private function formatOrder(Order $o): array
    {
        return [
            'id'             => $o->id,
            'order_number'   => $o->order_number,
            'status'         => $o->status,
            'delivery_lat'   => $o->delivery_lat,
            'delivery_lng'   => $o->delivery_lng,
            'delivery_notes' => $o->delivery_notes,
            'total_amount'   => $o->total_amount,
            'payment_method' => $o->payment_method,
            'customer_name'  => $o->customer?->name,
            'customer_phone' => $o->customer?->phone,
            'size_name'      => $o->size?->name,
            'created_at'     => $o->created_at->toIso8601String(),
        ];
    }

    private function formatOrderDetail(Order $o): array
    {
        return array_merge($this->formatOrder($o), [
            'brand_name'   => $o->brand?->name,
            'addons'       => $o->addons->map(fn ($a) => ['name' => $a->addonItem?->name, 'price' => $a->price]),
            'history'      => $o->statusHistory->map(fn ($h) => ['status' => $h->status, 'at' => $h->created_at?->toIso8601String()]),
        ]);
    }
}
