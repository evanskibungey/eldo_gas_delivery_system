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
use App\Support\OrderLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoAssignRiderToOrder implements ShouldQueue
{
    public int $delay = 3;

    public int $tries = 1;

    public string $queue = 'default';

    public function handle(OrderPlacedEvent $event): void
    {
        $orderId = $event->order->id;
        $excludeRiderIds = $event->excludeRiderIds;
        $assignedRiderId = null;
        $noRiderFound = false;
        $radiusKm = (float) SystemSetting::get('auto_assign_radius_km', 15);

        DB::transaction(function () use ($orderId, $excludeRiderIds, $radiusKm, &$assignedRiderId, &$noRiderFound): void {
            $order = Order::lockForUpdate()->find($orderId);

            if (! $order || $order->status !== OrderLifecycle::STATUS_PENDING) {
                return;
            }

            $candidateIds = $this->candidateIdsForOrder($order, $excludeRiderIds, $radiusKm);

            if ($candidateIds->isEmpty()) {
                Log::info("[AutoAssign] No eligible rider within {$radiusKm} km for order #{$order->order_number} - will require manual assignment.");
                $noRiderFound = true;
                return;
            }

            foreach ($candidateIds as $riderId) {
                $rider = Rider::lockForUpdate()->find($riderId);

                if (! $rider || ! $rider->is_active || ! $rider->is_available) {
                    continue;
                }

                $hasActiveOrder = $rider->orders()
                    ->whereIn('status', OrderLifecycle::riderBusyStatuses())
                    ->exists();

                if ($hasActiveOrder) {
                    continue;
                }

                $order->update([
                    'rider_id' => $rider->id,
                    'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
                    'rider_assigned_at' => now(),
                    'rider_acceptance_deadline' => now()->addSeconds(60),
                    'rider_accepted_at' => null,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
                    'note' => "Auto-assigned to {$rider->name}",
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => now(),
                ]);

                $assignedRiderId = $rider->id;
                return;
            }

            Log::info("[AutoAssign] All candidate riders taken by concurrent jobs for order #{$order->order_number}.");
            $noRiderFound = true;
        });

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

    public function failed(OrderPlacedEvent $event, \Throwable $exception): void
    {
        Log::error("[AutoAssign] Job failed for order #{$event->order->order_number}: {$exception->getMessage()}");

        $order = Order::with(['customer', 'size', 'brand'])->find($event->order->id);
        if ($order && $order->status === OrderLifecycle::STATUS_PENDING) {
            $this->alertAdminNoRider($order);
        }
    }

    private function candidateIdsForOrder(Order $order, array $excludeRiderIds, float $radiusKm): Collection
    {
        $candidateQuery = Rider::where('is_active', true)
            ->where('is_available', true)
            ->where('location_updated_at', '>=', now()->subMinutes(30))
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereDoesntHave('orders', fn ($query) => $query->whereIn('status', OrderLifecycle::riderBusyStatuses()))
            ->when(! empty($excludeRiderIds), fn ($query) => $query->whereNotIn('id', $excludeRiderIds))
            ->withCount(['orders as pending_load' => fn ($query) => $query->where('status', OrderLifecycle::STATUS_RIDER_ASSIGNED)]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $candidateQuery
                ->get()
                ->filter(fn (Rider $rider) => $this->distanceKm(
                    (float) $order->delivery_lat,
                    (float) $order->delivery_lng,
                    (float) $rider->current_latitude,
                    (float) $rider->current_longitude,
                ) <= $radiusKm)
                ->sort(fn (Rider $left, Rider $right) => [$left->pending_load, -$left->total_deliveries] <=> [$right->pending_load, -$right->total_deliveries])
                ->pluck('id')
                ->values();
        }

        return $candidateQuery
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
            ->orderBy('pending_load')
            ->orderByDesc('total_deliveries')
            ->pluck('id');
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1, sqrt($a)));
    }

    private function alertAdminNoRider(Order $order): void
    {
        $phones = $this->resolveManagerPhones();

        if (empty($phones)) {
            Log::warning("[AutoAssign] No admin phones configured - cannot send no-rider alert for order #{$order->order_number}");
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
            ->map(function (string $phone): string {
                $phone = trim($phone);
                return str_starts_with($phone, '0') ? '+254' . substr($phone, 1) : $phone;
            })
            ->filter()
            ->values()
            ->all();
    }
}