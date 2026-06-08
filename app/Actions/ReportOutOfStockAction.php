<?php

namespace App\Actions;

use App\Events\OrderCancelledEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\OutOfStockExceptionEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Admin\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportOutOfStockAction
{
    public function __construct(private readonly StockService $stock) {}

    public function execute(Order $order, string $reason, int $actorId): void
    {
        if (! in_array($order->status, ['pending', 'rider_assigned'])) {
            throw ValidationException::withMessages([
                'order' => 'Out of stock can only be reported before the rider picks up.',
            ]);
        }

        DB::transaction(function () use ($order, $reason, $actorId) {
            // Stock was deducted at placement — restore it so the cylinder is available again
            $this->stock->restoreForOrder($order);

            $order->update([
                'status'            => 'cancelled',
                'has_issue'         => true,
                'issue_type'        => 'out_of_stock',
                'issue_description' => $reason,
                'cancelled_at'      => now(),
                'cancel_reason'     => $reason,
                'cancelled_by'      => 'admin',
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'cancelled',
                'note'       => "Out of stock: {$reason}",
                'actor_type' => 'admin',
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        event(new OutOfStockExceptionEvent($fresh));
        event(new OrderCancelledEvent($fresh, $reason));
        event(new OrderStatusUpdatedEvent($fresh));
    }
}
