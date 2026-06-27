<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Events\OrderDeliveredEvent;
use App\Events\OrderPlacedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderOrderRemovedEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\RiderStatsService;
use App\Support\OrderLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private readonly RiderStatsService $riderStats) {}

    public function active(Request $request): JsonResponse
    {
        $rider = $request->user();

        $orders = Order::where('rider_id', $rider->id)
            ->whereIn('status', OrderLifecycle::activeStatuses())
            ->with(['customer:id,name,phone', 'size:id,name'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => $this->formatOrder($order));

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

    public function accept(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();

        if ($order->rider_id !== $rider->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($order->status !== OrderLifecycle::STATUS_RIDER_ASSIGNED) {
            return response()->json(['message' => 'Order is not awaiting acceptance.'], 422);
        }

        $order->update([
            'rider_accepted_at' => now(),
            'rider_acceptance_deadline' => null,
        ]);

        event(new OrderStatusUpdatedEvent($order->fresh()));

        return response()->json(['message' => 'Order accepted.']);
    }

    public function decline(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();

        if ($order->rider_id !== $rider->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($order->status !== OrderLifecycle::STATUS_RIDER_ASSIGNED) {
            return response()->json(['message' => 'Order is not awaiting acceptance.'], 422);
        }

        $declinedRiderId = $rider->id;

        DB::transaction(function () use ($order, $declinedRiderId): void {
            $order->update([
                'rider_id' => null,
                'status' => OrderLifecycle::STATUS_PENDING,
                'rider_assigned_at' => null,
                'rider_acceptance_deadline' => null,
                'rider_accepted_at' => null,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_PENDING,
                'note' => 'Rider declined - re-queued for assignment',
                'actor_type' => 'rider',
                'actor_id' => $declinedRiderId,
                'created_at' => now(),
            ]);
        });

        event(new RiderOrderRemovedEvent($declinedRiderId, $order->id, 'declined'));
        event(new OrderPlacedEvent($order->fresh(), [$declinedRiderId]));

        return response()->json(['message' => 'Order declined.']);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        if ($order->rider_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'status' => 'required|in:picked_up,on_the_way,delivered',
            'payment_collected' => 'boolean',
            'delivery_photo' => 'nullable|image|max:5120',
        ]);

        if (! OrderLifecycle::canTransition($order->status, $data['status'])) {
            return response()->json(['message' => "Cannot transition from {$order->status} to {$data['status']}."], 422);
        }

        DB::transaction(function () use ($data, $order, $request): void {
            $updates = ['status' => $data['status']];

            if ($data['status'] === OrderLifecycle::STATUS_PICKED_UP) {
                $updates['picked_up_at'] = now();
            } elseif ($data['status'] === OrderLifecycle::STATUS_ON_THE_WAY) {
                $updates['on_the_way_at'] = now();
                if ($order->status === OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS) {
                    $updates['has_issue'] = false;
                    $updates['issue_resolved'] = true;
                }
            } elseif ($data['status'] === OrderLifecycle::STATUS_DELIVERED) {
                $updates['delivered_at'] = now();

                if (! empty($data['payment_collected']) && $order->payment_method === 'cash') {
                    $updates['payment_status'] = 'collected';
                }

                if ($request->hasFile('delivery_photo')) {
                    $updates['delivery_photo_path'] = $request->file('delivery_photo')
                        ->store("delivery_photos/{$order->id}", 'public');
                }
            }

            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $data['status'],
                'actor_type' => 'rider',
                'actor_id' => $request->user()->id,
                'created_at' => now(),
            ]);
        });

        if ($data['status'] === OrderLifecycle::STATUS_DELIVERED) {
            $this->riderStats->sync($order->rider_id);
        }

        $fresh = $order->fresh();
        event(new OrderStatusUpdatedEvent($fresh));
        if ($data['status'] === OrderLifecycle::STATUS_DELIVERED) {
            event(new OrderDeliveredEvent($fresh));
        }

        return response()->json([
            'message' => 'Status updated.',
            'status' => $data['status'],
            'payment_status' => $fresh->payment_status,
        ]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'delivery_notes' => $order->delivery_notes,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->phone,
            'size_name' => $order->size?->name,
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }

    private function formatOrderDetail(Order $order): array
    {
        return array_merge($this->formatOrder($order), [
            'brand_name' => $order->brand?->name,
            'addons' => $order->addons->map(fn ($addon) => ['name' => $addon->addonItem?->name, 'price' => $addon->price]),
            'history' => $order->statusHistory->map(fn ($history) => ['status' => $history->status, 'at' => $history->created_at?->toIso8601String()]),
            'delivery_photo_url' => $order->delivery_photo_path ? Storage::url($order->delivery_photo_path) : null,
        ]);
    }
}
