<?php

namespace App\Jobs;

use App\Events\RiderDelayAlertEvent;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckRiderDelaysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // 9.3a — Rider has not picked up after 15 minutes of assignment
        Order::where('status', 'rider_assigned')
            ->whereNotNull('rider_assigned_at')
            ->where('rider_assigned_at', '<', now()->subMinutes(15))
            ->whereDoesntHave('statusHistory', function ($q) {
                $q->where('note', 'like', '%pickup_delay alerted%');
            })
            ->with('rider:id,name')
            ->each(function (Order $order) {
                event(new RiderDelayAlertEvent($order, 'pickup_delay'));
            });

        // 9.3b — Rider picked up but hasn't delivered within 45 minutes
        Order::where('status', 'on_the_way')
            ->whereNotNull('picked_up_at')
            ->where('picked_up_at', '<', now()->subMinutes(45))
            ->with('rider:id,name')
            ->each(function (Order $order) {
                event(new RiderDelayAlertEvent($order, 'delivery_delay'));
            });
    }
}
