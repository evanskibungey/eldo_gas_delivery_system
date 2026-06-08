<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderAssignedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoAssignRiderToOrder implements ShouldQueue
{
    /**
     * Short delay so the PlaceOrderAction transaction is guaranteed committed
     * before this job reads the order row.
     */
    public int $delay = 3;

    /**
     * Do not retry — if no rider is available now, retrying won't help.
     * The admin will assign manually if auto-assign fails.
     */
    public int $tries = 1;

    public string $queue = 'default';

    public function handle(OrderPlacedEvent $event): void
    {
        $orderId = $event->order->id;
        $assignedRiderId = null;

        DB::transaction(function () use ($orderId, &$assignedRiderId): void {
            // Lock the order row so concurrent jobs cannot double-assign.
            $order = Order::lockForUpdate()->find($orderId);

            if (! $order || $order->status !== 'pending') {
                // Admin already assigned manually during the delay — nothing to do.
                return;
            }

            // Find the best available rider:
            //   • active and marked available
            //   • reported location within the last 30 minutes (stale location
            //     means the rider may be offline or out of area)
            //   • no current active orders (rider_assigned / picked_up / on_the_way)
            //   • prefer riders with the fewest pending assignments, then highest
            //     total deliveries (experience-based tie-break)
            $candidateIds = Rider::where('is_active', true)
                ->where('is_available', true)
                ->where('location_updated_at', '>=', now()->subMinutes(30))
                ->whereDoesntHave('orders', fn ($q) => $q->whereIn('status', [
                    'rider_assigned', 'picked_up', 'on_the_way',
                ]))
                ->withCount(['orders as pending_load' => fn ($q) => $q->where('status', 'rider_assigned')])
                ->orderBy('pending_load')
                ->orderByDesc('total_deliveries')
                ->pluck('id');

            if ($candidateIds->isEmpty()) {
                Log::info("[AutoAssign] No eligible rider for order #{$order->order_number} — will require manual assignment.");
                return;
            }

            // Lock each candidate row in order to prevent the same rider being
            // assigned to two concurrent orders simultaneously.
            foreach ($candidateIds as $riderId) {
                $rider = Rider::lockForUpdate()->find($riderId);

                if (! $rider || ! $rider->is_active || ! $rider->is_available) {
                    continue;
                }

                $hasActiveOrder = $rider->orders()
                    ->whereIn('status', ['rider_assigned', 'picked_up', 'on_the_way'])
                    ->exists();

                if ($hasActiveOrder) {
                    continue;
                }

                // Rider is confirmed available — assign.
                $order->update([
                    'rider_id'          => $rider->id,
                    'status'            => 'rider_assigned',
                    'rider_assigned_at' => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id'   => $order->id,
                    'status'     => 'rider_assigned',
                    'note'       => "Auto-assigned to {$rider->name}",
                    'actor_type' => 'system',
                    'actor_id'   => null,
                    'created_at' => now(),
                ]);

                $assignedRiderId = $rider->id;
                return;
            }

            Log::info("[AutoAssign] All candidate riders taken by concurrent jobs for order #{$order->order_number}.");
        });

        // Fire events outside the transaction so they only run on successful commit.
        if ($assignedRiderId !== null) {
            $fresh = Order::with(['rider'])->find($orderId);

            if ($fresh && $fresh->rider) {
                event(new RiderAssignedEvent($fresh, $fresh->rider));
                event(new OrderStatusUpdatedEvent($fresh));
                Log::info("[AutoAssign] Order #{$fresh->order_number} assigned to rider {$fresh->rider->name}.");
            }
        }
    }
}
