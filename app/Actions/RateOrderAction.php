<?php

namespace App\Actions;

use App\Events\RatingSubmittedEvent;
use App\Models\Order;
use App\Models\OrderRating;
use App\Services\GasPointsService;
use App\Services\RiderStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RateOrderAction
{
    public function __construct(
        private readonly RiderStatsService $riderStats,
        private readonly GasPointsService $gasPoints,
    ) {}

    public function execute(
        Order $order,
        int $stars,
        array $tags = [],
        ?string $review = null,
        bool $flagged = false,
        ?string $flagReason = null,
    ): array {
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'You can only rate a delivered order.',
            ]);
        }

        if ($order->rating()->exists()) {
            throw ValidationException::withMessages([
                'order' => 'This order has already been rated.',
            ]);
        }

        if (! $order->rider_id) {
            throw ValidationException::withMessages([
                'order' => 'This order has no rider to rate.',
            ]);
        }

        return DB::transaction(function () use ($order, $stars, $tags, $review, $flagged, $flagReason) {
            $rating = OrderRating::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'rider_id' => $order->rider_id,
                'stars' => $stars,
                'tags' => $tags,
                'review' => $review,
                'flagged' => $flagged,
                'flag_reason' => $flagReason,
                'created_at' => now(),
            ]);

            $this->riderStats->sync($order->rider_id);
            $awardedPoints = $this->gasPoints->awardForRating($order->fresh('customer'));

            event(new RatingSubmittedEvent($order->fresh(), $rating));

            return [
                'rating' => $rating,
                'awarded_points' => $awardedPoints,
            ];
        });
    }
}
