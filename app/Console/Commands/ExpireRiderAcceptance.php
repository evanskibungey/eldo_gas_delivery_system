<?php

namespace App\Console\Commands;

use App\Events\OrderPlacedEvent;
use App\Events\RiderOrderRemovedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\OrderLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireRiderAcceptance extends Command
{
    protected $signature = 'orders:expire-acceptance';
    protected $description = 'Re-queue any rider_assigned orders whose acceptance deadline has passed.';

    public function handle(): void
    {
        $expired = Order::where('status', OrderLifecycle::STATUS_RIDER_ASSIGNED)
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
            $locked = Order::lockForUpdate()->find($order->id);

            if (! $locked || $locked->status !== OrderLifecycle::STATUS_RIDER_ASSIGNED || $locked->rider_accepted_at !== null) {
                return;
            }

            $declinedRiderId = $locked->rider_id;

            $locked->update([
                'rider_id' => null,
                'status' => OrderLifecycle::STATUS_PENDING,
                'rider_assigned_at' => null,
                'rider_acceptance_deadline' => null,
                'rider_accepted_at' => null,
            ]);

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'status' => OrderLifecycle::STATUS_PENDING,
                'note' => 'Acceptance deadline expired - re-queued for assignment',
                'actor_type' => 'system',
                'actor_id' => null,
                'created_at' => now(),
            ]);
        });

        if ($declinedRiderId !== null) {
            event(new RiderOrderRemovedEvent($declinedRiderId, $order->id, 'acceptance_expired'));
            $fresh = Order::find($order->id);
            if ($fresh) {
                event(new OrderPlacedEvent($fresh, [$declinedRiderId]));
                Log::info("[ExpireAcceptance] Order #{$fresh->order_number} re-queued, rider #{$declinedRiderId} excluded.");
            }
        }
    }
}
