<?php

namespace App\Actions;

use App\Events\RiderDelayAlertEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderNoShowAction
{
    public function execute(Order $order, string $actorType, int $actorId): void
    {
        if ($order->status !== 'rider_assigned') {
            throw ValidationException::withMessages([
                'order' => 'Rider no-show can only be reported when a rider is assigned.',
            ]);
        }

        if ($order->has_issue && $order->issue_type === 'rider_no_show') {
            throw ValidationException::withMessages([
                'order' => 'A rider no-show has already been reported for this order.',
            ]);
        }

        DB::transaction(function () use ($order, $actorType, $actorId) {
            $order->update([
                'has_issue'  => true,
                'issue_type' => 'rider_no_show',
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => 'Rider no-show reported — admin will reassign.',
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);

            // Flag rider
            if ($order->rider_id) {
                $order->rider()->increment('incident_count');
            }
        });

        event(new RiderDelayAlertEvent($order, 'no_show'));
    }
}
