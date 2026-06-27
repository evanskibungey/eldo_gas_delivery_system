<?php

namespace App\Actions;

use App\Events\OrderCancelledEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderOrderRemovedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Admin\StockService;
use App\Services\GasPointsService;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly GasPointsService $gasPoints,
    ) {}

    public function execute(
        Order $order,
        string $reason,
        string $cancelledBy,
        int $actorId,
        ?bool $restoreInventory = null,
    ): void {
        if (in_array($order->status, OrderLifecycle::terminalStatuses(), true)) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot be cancelled.',
            ]);
        }

        $shouldRestoreInventory = $restoreInventory ?? OrderLifecycle::canRestoreInventoryOnCancel($order->status);
        $riderId = $order->rider_id;

        DB::transaction(function () use ($order, $reason, $cancelledBy, $actorId, $shouldRestoreInventory) {
            if ($shouldRestoreInventory) {
                $this->stock->restoreForOrder($order);
            }

            $order->update([
                'status' => OrderLifecycle::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $cancelledBy,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_CANCELLED,
                'note' => $shouldRestoreInventory
                    ? $reason
                    : $reason . ' (inventory not auto-restored)',
                'actor_type' => $cancelledBy,
                'actor_id' => $actorId,
                'created_at' => now(),
            ]);

            $this->gasPoints->refundRedemption($order->fresh('customer'));
        });

        $fresh = $order->fresh();
        if ($riderId) {
            event(new RiderOrderRemovedEvent($riderId, $fresh->id, 'cancelled'));
        }

        event(new OrderCancelledEvent($fresh, $reason));
        event(new OrderStatusUpdatedEvent($fresh));
    }
}
