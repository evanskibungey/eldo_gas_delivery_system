<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolvePaymentDisputeAction
{
    // resolution: 'paid' | 'refund'
    public function execute(Order $order, string $resolution, int $actorId): void
    {
        if ($order->payment_status !== 'disputed') {
            throw ValidationException::withMessages([
                'order' => 'This order does not have an active payment dispute.',
            ]);
        }

        if (! in_array($resolution, ['paid', 'refund'])) {
            throw ValidationException::withMessages([
                'resolution' => 'Resolution must be "paid" or "refund".',
            ]);
        }

        DB::transaction(function () use ($order, $resolution, $actorId) {
            $newPaymentStatus = $resolution === 'paid' ? 'collected' : 'refunded';

            $order->update([
                'payment_status' => $newPaymentStatus,
                'issue_resolved' => true,
            ]);

            $label = $resolution === 'paid' ? 'Marked as paid' : 'Refund initiated';

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => "Payment dispute resolved — {$label}",
                'actor_type' => 'admin',
                'actor_id'   => $actorId,
                'created_at' => now(),
            ]);
        });
    }
}
