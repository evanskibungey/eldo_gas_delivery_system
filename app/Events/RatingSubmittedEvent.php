<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingSubmittedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order       $order,
        public readonly OrderRating $rating,
    ) {}
}
