<?php

namespace App\Services\Admin;

use App\Events\OrderDeliveredEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderAssignedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly StockService $stock) {}

    public function paginated(array $filters): LengthAwarePaginator
    {
        return Order::with(['customer:id,name,phone', 'rider:id,name', 'size:id,name', 'brand:id,name'])
            ->when($filters['status'] ?? null, function ($q, $status) {
                if ($status === 'active') {
                    $q->whereIn('status', ['rider_assigned', 'picked_up', 'on_the_way', 'correction_in_progress']);
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($q) use ($v) {
                    $q->where('order_number', 'like', "%{$v}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$v}%")
                            ->orWhere('phone', 'like', "%{$v}%"));
                });
            })
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('created_at', $v))
            ->orderByRaw(
                DB::getDriverName() === 'mysql'
                    ? "FIELD(status, 'pending', 'rider_assigned', 'picked_up', 'on_the_way', 'correction_in_progress', 'delivered', 'cancelled')"
                    : "CASE status WHEN 'pending' THEN 1 WHEN 'rider_assigned' THEN 2 WHEN 'picked_up' THEN 3 WHEN 'on_the_way' THEN 4 WHEN 'correction_in_progress' THEN 5 WHEN 'delivered' THEN 6 ELSE 7 END"
            )
            ->orderBy('created_at', 'asc')
            ->paginate(25)
            ->withQueryString();
    }

    public function availableRiders(): Collection
    {
        return Rider::where('is_active', true)
            ->where('is_available', true)
            ->whereDoesntHave('orders', fn ($q) => $q->whereIn('status', ['rider_assigned', 'picked_up', 'on_the_way']))
            ->orderBy('name')
            ->get();
    }

    public function assign(Order $order, Rider $rider): void
    {
        if (! in_array($order->status, ['pending', 'rider_assigned'])) {
            throw ValidationException::withMessages([
                'rider_id' => 'This order cannot be assigned in its current state.',
            ]);
        }

        DB::transaction(function () use ($order, $rider): void {
            // Pessimistic lock prevents two admins assigning the same rider
            // to different orders in the same instant.
            $locked = Rider::lockForUpdate()->find($rider->id);

            if (! $locked || ! $locked->is_active || ! $locked->is_available) {
                throw ValidationException::withMessages([
                    'rider_id' => 'This rider is no longer available.',
                ]);
            }

            $hasActive = $locked->orders()
                ->whereIn('status', ['rider_assigned', 'picked_up', 'on_the_way'])
                ->exists();

            if ($hasActive) {
                throw ValidationException::withMessages([
                    'rider_id' => 'This rider already has an active order.',
                ]);
            }

            $order->update([
                'rider_id'          => $locked->id,
                'status'            => 'rider_assigned',
                'rider_assigned_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'rider_assigned',
                'note'       => "Assigned to {$locked->name}",
                'actor_type' => 'admin',
                'actor_id'   => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        event(new RiderAssignedEvent($fresh, $rider));
        event(new OrderStatusUpdatedEvent($fresh));
    }

    public function reassign(Order $order, Rider $rider, string $reason): void
    {
        if (! in_array($order->status, ['rider_assigned', 'picked_up', 'on_the_way', 'correction_in_progress'])) {
            throw ValidationException::withMessages([
                'rider_id' => 'This order cannot be reassigned in its current state.',
            ]);
        }

        $preserveStatus = in_array($order->status, ['picked_up', 'on_the_way', 'correction_in_progress']);

        DB::transaction(function () use ($order, $rider, $reason, $preserveStatus): void {
            // Lock the new rider to prevent concurrent double-assignment.
            $locked = Rider::lockForUpdate()->find($rider->id);

            if (! $locked || ! $locked->is_active) {
                throw ValidationException::withMessages([
                    'rider_id' => 'The selected rider is not active.',
                ]);
            }

            $updates = [
                'rider_id'          => $locked->id,
                'rider_assigned_at' => now(),
            ];

            if (! $preserveStatus) {
                $updates['status'] = 'rider_assigned';
            }

            $order->update($updates);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->fresh()->status,
                'note'       => "Reassigned to {$locked->name}. Reason: {$reason}",
                'actor_type' => 'admin',
                'actor_id'   => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        event(new RiderAssignedEvent($fresh, $rider));
        event(new OrderStatusUpdatedEvent($fresh));
    }

    public function advanceStatus(
        Order $order,
        string $newStatus,
        ?string $deliveryNote = null,
        bool $paymentCollected = false,
    ): void {
        $validTransitions = [
            'rider_assigned' => ['picked_up'],
            'picked_up'      => ['on_the_way', 'delivered'],
            'on_the_way'     => ['delivered'],
        ];

        $allowed = $validTransitions[$order->status] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$order->status} to {$newStatus}.",
            ]);
        }

        $updates = ['status' => $newStatus];
        $note = null;

        if ($newStatus === 'picked_up') {
            $updates['picked_up_at'] = now();
        }

        if ($newStatus === 'on_the_way') {
            $updates['on_the_way_at'] = now();
        }

        if ($newStatus === 'delivered') {
            $updates['delivered_at'] = now();
            $note = $deliveryNote ?? 'Delivery confirmed';

            if ($paymentCollected && $order->payment_method === 'cash') {
                $updates['payment_status'] = 'collected';
            }

            $this->updateRiderDeliveryCount($order);
        }

        DB::transaction(function () use ($order, $updates, $newStatus, $note): void {
            $order->update($updates);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $newStatus,
                'note'       => $note,
                'actor_type' => 'admin',
                'actor_id'   => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        event(new OrderStatusUpdatedEvent($fresh));
        if ($newStatus === 'delivered') {
            event(new OrderDeliveredEvent($fresh));
        }
    }

    public function resolveCorrection(Order $order): void
    {
        if ($order->status !== 'correction_in_progress') {
            throw ValidationException::withMessages([
                'status' => 'Order is not in correction_in_progress state.',
            ]);
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status'         => 'on_the_way',
                'has_issue'      => false,
                'issue_resolved' => true,
            ]);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'on_the_way',
                'note'       => 'Issue resolved - delivery resumed',
                'actor_type' => 'admin',
                'actor_id'   => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        event(new OrderStatusUpdatedEvent($order->fresh()));
    }

    public function collectPayment(Order $order): void
    {
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'payment' => 'Payment can only be collected for delivered orders.',
            ]);
        }

        if ($order->payment_status === 'collected') {
            throw ValidationException::withMessages([
                'payment' => 'Payment has already been collected.',
            ]);
        }

        DB::transaction(function () use ($order): void {
            $order->update(['payment_status' => 'collected']);

            $note = $order->payment_method === 'mpesa'
                ? 'M-Pesa payment confirmed by admin'
                : 'Cash payment collected';

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => $note,
                'actor_type' => 'admin',
                'actor_id'   => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        event(new OrderStatusUpdatedEvent($order->fresh()));
    }

    public function pendingCount(): int
    {
        return Order::where('status', 'pending')->count();
    }

    private function updateRiderDeliveryCount(Order $order): void
    {
        if (! $order->rider_id) {
            return;
        }

        $count = Order::where('rider_id', $order->rider_id)
            ->where('status', 'delivered')
            ->count();

        $order->rider()->update(['total_deliveries' => $count + 1]);
    }
}

