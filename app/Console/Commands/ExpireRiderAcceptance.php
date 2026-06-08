<?php

namespace App\Console\Commands;

use App\Events\OrderPlacedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireRiderAcceptance extends Command
{
    protected $signature   = 'orders:expire-acceptance';
    protected $description = 'Re-queue any rider_assigned orders whose acceptance deadline has passed.';

    public function handle(): void
    {
        $expired = Order::where('status', 'rider_assigned')
            ->whereNotNull('rider_acceptance_deadline')
            ->whereNull('rider_accepted_at')
            ->where('rider_acceptance_deadline', '<', now())
            ->get();

        foreach ($expired as $order) {
            $this->expireOne($order);
        }

        if ($expired->isNotEmpty()) {
            Log::info("[ExpireAcceptance] Re-queued {$expired->count()} timed-out order(s).");
        }
    }

    private function expireOne(Order $order): void
    {
        $declinedRiderId = null;

        DB::transaction(function () use ($order, &$declinedRiderId): void {
            // Re-fetch with lock to avoid race with the rider tapping Accept at this exact moment.
            $locked = Order::lockForUpdate()->find($order->id);

            if (
                ! $locked ||
                $locked->status !== 'rider_assigned' ||
                $locked->rider_accepted_at !== null
            ) {
                return; // Rider just accepted — nothing to do.
            }

            $declinedRiderId = $locked->rider_id;

            $locked->update([
                'rider_id'                  => null,
                'status'                    => 'pending',
                'rider_assigned_at'         => null,
                'rider_acceptance_deadline' => null,
                'rider_accepted_at'         => null,
            ]);

            OrderStatusHistory::create([
                'order_id'   => $locked->id,
                'status'     => 'pending',
                'note'       => 'Acceptance deadline expired — re-queued for assignment',
                'actor_type' => 'system',
                'actor_id'   => null,
                'created_at' => now(),
            ]);
        });

        if ($declinedRiderId !== null) {
            $fresh = Order::find($order->id);
            if ($fresh) {
                event(new OrderPlacedEvent($fresh, [$declinedRiderId]));
                Log::info("[ExpireAcceptance] Order #{$fresh->order_number} re-queued, rider #{$declinedRiderId} excluded.");
            }
        }
    }
}
