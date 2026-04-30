<?php

namespace App\Actions;

use App\Events\OrderCancelledEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Admin\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(private readonly StockService $stock) {}

    public function execute(Order $order, string $reason, string $cancelledBy, int $actorId): void
    {
        if (in_array($order->status, ['delivered', 'cancelled'])) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot be cancelled.',
            ]);
        }

        DB::transaction(function () use ($order, $reason, $cancelledBy, $actorId) {
            // Stock was deducted at picked_up — restore it on cancellation
            if ($order->status === 'picked_up') {
                $this->stock->autoRestoreForOrder($order);
            }

            $order->update([
                'status'        => 'cancelled',
                'cancelled_at'  => now(),
                'cancel_reason' => $reason,
                'cancelled_by'  => $cancelledBy,
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'cancelled',
                'note'       => $reason,
                'actor_type' => $cancelledBy,
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);
        });

        event(new OrderCancelledEvent($order, $reason));
    }
}
