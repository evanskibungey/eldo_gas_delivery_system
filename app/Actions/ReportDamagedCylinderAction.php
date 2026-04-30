<?php

namespace App\Actions;

use App\Events\DamagedCylinderReportedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportDamagedCylinderAction
{
    public function execute(Order $order, string $description, string $actorType, int $actorId): void
    {
        // P0 — reportable on active orders and completed deliveries
        if (in_array($order->status, ['cancelled'])) {
            throw ValidationException::withMessages([
                'order' => 'Cannot report a damaged cylinder on a cancelled order.',
            ]);
        }

        DB::transaction(function () use ($order, $description, $actorType, $actorId) {
            $order->update([
                'has_issue'         => true,
                'issue_type'        => 'damaged_cylinder',
                'issue_description' => $description,
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => "Damaged/unsafe cylinder reported: {$description}",
                'actor_type' => $actorType,
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);

            // Flag rider with quality incident
            if ($order->rider_id) {
                $order->rider()->increment('incident_count');
            }
        });

        // Fires P0 — HandleDamagedCylinder writes StockAuditLog + logs critical
        event(new DamagedCylinderReportedEvent($order));
    }
}
