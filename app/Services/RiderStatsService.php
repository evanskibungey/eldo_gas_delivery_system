<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRating;
use App\Models\Rider;

class RiderStatsService
{
    public function sync(?int $riderId): void
    {
        if (! $riderId) {
            return;
        }

        $rider = Rider::find($riderId);

        if (! $rider) {
            return;
        }

        $avgRating = (float) (OrderRating::where('rider_id', $riderId)->avg('stars') ?? 0);
        $deliveries = Order::where('rider_id', $riderId)
            ->where('status', 'delivered')
            ->count();

        $rider->update([
            'avg_rating' => round($avgRating, 2),
            'total_deliveries' => $deliveries,
        ]);
    }
}
