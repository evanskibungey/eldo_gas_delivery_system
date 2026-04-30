<?php

namespace App\Listeners;

use App\Events\RatingSubmittedEvent;
use App\Services\GasPointsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardGasPointsOnRating implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function handle(RatingSubmittedEvent $event): void
    {
        $order = $event->order->load('customer');

        if ($order->customer) {
            $this->gasPoints->awardForRating($order);
        }
    }
}
