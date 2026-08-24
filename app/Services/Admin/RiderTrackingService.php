<?php

namespace App\Services\Admin;

use App\Events\RiderLocationUpdated;
use App\Models\Rider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RiderTrackingService
{
    public function getActivePositions(): Collection
    {
        return Rider::where('is_active', true)
            ->get(['id', 'name', 'is_available', 'current_latitude', 'current_longitude', 'heading', 'location_updated_at'])
            ->map(fn (Rider $r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'status'     => $this->deriveStatus($r),
                'lat'        => $r->current_latitude,
                'lng'        => $r->current_longitude,
                'heading'    => $r->heading,
                'updated_at' => $r->location_updated_at?->diffForHumans(),
            ]);
    }

    public function updateLocation(Rider $rider, array $data): void
    {
        // Always persist: auto-assignment filters candidates on
        // location_updated_at being recent, so a skipped write would make
        // an active rider look stale and drop them out of the running.
        $rider->update([
            'current_latitude'    => $data['lat'],
            'current_longitude'   => $data['lng'],
            'heading'             => $data['heading'] ?? null,
            'location_updated_at' => now(),
        ]);

        // Broadcasting, unlike the write, is expensive — it fans out to the
        // admin map plus one channel per active order. Only put a ping on
        // the wire when it actually tells subscribers something new.
        if (! $this->shouldBroadcast($rider, (float) $data['lat'], (float) $data['lng'])) {
            return;
        }

        // Pull every still-active order assigned to this rider so the
        // event can fan out a copy to each `private-orders.{id}` channel
        // — that's how the customer's Flutter tracking page gets live
        // rider position updates.
        $activeOrderIds = $rider->orders()
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->pluck('id')
            ->all();

        $primaryOrderId = $activeOrderIds[0] ?? null;

        event(new RiderLocationUpdated(
            riderId:           $rider->id,
            riderName:         $rider->name,
            lat:               (float) $data['lat'],
            lng:               (float) $data['lng'],
            status:            $this->deriveStatus($rider),
            heading:           $data['heading'] ?? null,
            orderId:           $primaryOrderId !== null ? '#' . $primaryOrderId : null,
            location:          $data['location'] ?? null,
            broadcastOrderIds: array_values($activeOrderIds),
        ));
    }

    public function deriveStatus(Rider $rider): string
    {
        if (! $rider->is_active)  return 'offline';
        if ($rider->is_available) return 'available';
        return 'on_delivery';
    }

    /**
     * Decide whether this ping earns a broadcast: rate-capped, movement
     * driven, with a heartbeat so a parked rider's marker stays fresh.
     */
    private function shouldBroadcast(Rider $rider, float $lat, float $lng): bool
    {
        $cacheKey = "rider:{$rider->id}:last_broadcast";
        $last = Cache::get($cacheKey);

        $minInterval = (int) config('tracking.min_broadcast_interval_seconds', 5);
        $minDistance = (int) config('tracking.min_broadcast_distance_meters', 25);
        $heartbeat   = (int) config('tracking.broadcast_heartbeat_seconds', 30);

        $remember = function () use ($cacheKey, $lat, $lng, $heartbeat): bool {
            // TTL comfortably past the heartbeat so an expiring entry never
            // masquerades as "first ping".
            Cache::put($cacheKey, ['lat' => $lat, 'lng' => $lng, 'at' => now()->timestamp], $heartbeat * 4);

            return true;
        };

        if (! is_array($last)) {
            return $remember();
        }

        $elapsed = now()->timestamp - (int) ($last['at'] ?? 0);

        if ($elapsed < $minInterval) {
            return false;
        }

        if ($elapsed >= $heartbeat) {
            return $remember();
        }

        $moved = $this->distanceMeters((float) $last['lat'], (float) $last['lng'], $lat, $lng);

        return $moved >= $minDistance ? $remember() : false;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusMeters = 6_371_000;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return $earthRadiusMeters * 2 * asin(min(1, sqrt($a)));
    }
}
