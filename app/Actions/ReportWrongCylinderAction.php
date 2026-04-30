<?php

namespace App\Actions;

use App\Events\WrongCylinderReportedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportWrongCylinderAction
{
    public function execute(Order $order, string $description, string $actorType, int $actorId): void
    {
        if (! in_array($order->status, ['rider_assigned', 'picked_up', 'on_the_way'])) {
            throw ValidationException::withMessages([
                'order' => 'Wrong cylinder can only be reported on an active delivery.',
            ]);
        }

        DB::transaction(function () use ($order, $description, $actorType, $actorId) {
            $order->update([
                'has_issue'         => true,
                'issue_type'        => 'wrong_cylinder',
                'issue_description' => $description,
                'status'            => 'correction_in_progress',
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'correction_in_progress',
                'note'       => "Wrong cylinder reported: {$description}",
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);

            // Flag rider with quality incident
            if ($order->rider_id) {
                $order->rider()->increment('incident_count');
            }
        });

        event(new WrongCylinderReportedEvent($order));
    }
}
