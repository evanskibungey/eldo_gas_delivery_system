<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderAssignedEvent;
use App\Jobs\SendSmsJob;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\SystemSetting;
use App\Services\Sms\SmsTemplateService;
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
        $excludeRiderIds = $event->excludeRiderIds;
        $assignedRiderId = null;
        $noRiderFound = false;

        // Read radius outside the transaction — it's a cached lookup and does
        // not need to be part of the pessimistic-lock scope.
        $radiusKm = (float) SystemSetting::get('auto_assign_radius_km', 15);

        DB::transaction(function () use ($orderId, $excludeRiderIds, $radiusKm, &$assignedRiderId, &$noRiderFound): void {
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
            //   • not in the excluded list (declined this order before)
            //   • within the configured proximity radius (Haversine, default 15 km)
            //   • prefer riders with the fewest pending assignments, then highest
            //     total deliveries (experience-based tie-break)
            $candidateIds = Rider::where('is_active', true)
                ->where('is_available', true)
                ->where('location_updated_at', '>=', now()->subMinutes(30))
                ->whereNotNull('current_latitude')
                ->whereNotNull('current_longitude')
                ->whereDoesntHave('orders', fn ($q) => $q->whereIn('status', [
                    'rider_assigned', 'picked_up', 'on_the_way',
                ]))
                ->when(! empty($excludeRiderIds), fn ($q) => $q->whereNotIn('id', $excludeRiderIds))
                ->whereRaw(
                    '(6371 * acos(
                        cos(radians(?)) * cos(radians(current_latitude))
                        * cos(radians(current_longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(current_latitude))
                    )) <= ?',
                    [
                        $order->delivery_lat,
                        $order->delivery_lng,
                        $order->delivery_lat,
                        $radiusKm,
                    ]
                )
                ->withCount(['orders as pending_load' => fn ($q) => $q->where('status', 'rider_assigned')])
                ->orderBy('pending_load')
                ->orderByDesc('total_deliveries')
                ->pluck('id');

            if ($candidateIds->isEmpty()) {
                Log::info("[AutoAssign] No eligible rider within {$radiusKm} km for order #{$order->order_number} — will require manual assignment.");
                $noRiderFound = true;
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
                // Give them 60 seconds to accept before expiry re-queues.
                $order->update([
                    'rider_id'                  => $rider->id,
                    'status'                    => 'rider_assigned',
                    'rider_assigned_at'         => now(),
                    'rider_acceptance_deadline' => now()->addSeconds(60),
                    'rider_accepted_at'         => null,
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
            $noRiderFound = true;
        });

        // Fire events outside the transaction so they only run on successful commit.
        if ($assignedRiderId !== null) {
            $fresh = Order::with(['rider'])->find($orderId);

            if ($fresh && $fresh->rider) {
                event(new RiderAssignedEvent($fresh, $fresh->rider));
                event(new OrderStatusUpdatedEvent($fresh));
                Log::info("[AutoAssign] Order #{$fresh->order_number} assigned to rider {$fresh->rider->name}.");
            }
        } elseif ($noRiderFound) {
            $order = Order::with(['customer', 'size', 'brand'])->find($orderId);
            if ($order) {
                $this->alertAdminNoRider($order);
            }
        }
    }

    /**
     * Called when the queued job fails after all retries are exhausted.
     * Since $tries = 1, this fires on the first (and only) unhandled exception.
     */
    public function failed(OrderPlacedEvent $event, \Throwable $e): void
    {
        Log::error("[AutoAssign] Job failed for order #{$event->order->order_number}: {$e->getMessage()}");

        $order = Order::with(['customer', 'size', 'brand'])->find($event->order->id);
        if ($order && $order->status === 'pending') {
            $this->alertAdminNoRider($order);
        }
    }

    private function alertAdminNoRider(Order $order): void
    {
        $phones = $this->resolveManagerPhones();

        if (empty($phones)) {
            Log::warning("[AutoAssign] No admin phones configured — cannot send no-rider alert for order #{$order->order_number}");
            return;
        }

        $message = app(SmsTemplateService::class)->adminNoRiderAvailable($order);

        foreach ($phones as $phone) {
            SendSmsJob::dispatch($phone, $message, 'auto_assign_no_rider', 'admin', 0);
        }
    }

    private function resolveManagerPhones(): array
    {
        $raw = config('shop.manager_phones', '');

        if (empty($raw)) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(function (string $p): string {
                $p = trim($p);
                return str_starts_with($p, '0') ? '+254' . substr($p, 1) : $p;
            })
            ->filter()
            ->values()
            ->all();
    }
}
