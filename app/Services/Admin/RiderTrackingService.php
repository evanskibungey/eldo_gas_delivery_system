<?php

namespace App\Services\Admin;

use App\Events\RiderLocationUpdated;
use App\Models\Rider;
use Illuminate\Support\Collection;

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
        $rider->update([
            'current_latitude'    => $data['lat'],
            'current_longitude'   => $data['lng'],
            'heading'             => $data['heading'] ?? null,
            'location_updated_at' => now(),
        ]);

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
}
