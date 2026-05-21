<?php

namespace App\Actions;

use App\Events\RatingSubmittedEvent;
use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer-side rating creator. Validates the order is rateable, writes
 * the OrderRating row, recomputes the rider's average rating, and fires
 * RatingSubmittedEvent so AwardGasPointsOnRating can award the +25 pts.
 */
class RateOrderAction
{
    public function execute(
        Order $order,
        int $stars,
        array $tags = [],
        ?string $review = null,
        bool $flagged = false,
        ?string $flagReason = null,
    ): OrderRating {
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

        return DB::transaction(function () use (
            $order, $stars, $tags, $review, $flagged, $flagReason
        ) {
            $rating = OrderRating::create([
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
                'rider_id'    => $order->rider_id,
                'stars'       => $stars,
                'tags'        => $tags,
                'review'      => $review,
                'flagged'     => $flagged,
                'flag_reason' => $flagReason,
                'created_at'  => now(),
            ]);

            // Recompute the rider's running average over all their ratings.
            $rider = $order->rider;
            if ($rider) {
                $avg = OrderRating::where('rider_id', $rider->id)->avg('stars');
                $rider->update([
                    'avg_rating' => round((float) $avg, 2),
                ]);
            }

            event(new RatingSubmittedEvent($order->fresh(), $rating));

            return $rating;
        });
    }
}
