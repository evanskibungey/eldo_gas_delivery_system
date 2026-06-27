<?php

namespace App\Services\Admin;

use App\Events\OrderDeliveredEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Events\RiderAssignedEvent;
use App\Events\RiderOrderRemovedEvent;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Services\RiderStatsService;
use App\Support\OrderLifecycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly RiderStatsService $riderStats,
    ) {}

    public function paginated(array $filters): LengthAwarePaginator
    {
        $activeDispatchStatuses = array_values(array_filter(
            OrderLifecycle::activeStatuses(),
            fn (string $status) => $status !== OrderLifecycle::STATUS_PENDING,
        ));

        return Order::with(['customer:id,name,phone', 'rider:id,name', 'size:id,name', 'brand:id,name'])
            ->when($filters['status'] ?? null, function ($q, $status) use ($activeDispatchStatuses) {
                if ($status === 'active') {
                    $q->whereIn('status', $activeDispatchStatuses);
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($filters['search'] ?? null, function ($q, $value) {
                $q->where(function ($query) use ($value) {
                    $query->where('order_number', 'like', "%{$value}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%"));
                });
            })
            ->when($filters['date'] ?? null, fn ($q, $value) => $q->whereDate('created_at', $value))
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
            ->whereDoesntHave('orders', fn ($query) => $query->whereIn('status', OrderLifecycle::riderBusyStatuses()))
            ->orderBy('name')
            ->get();
    }

    public function assign(Order $order, Rider $rider): void
    {
        if (! in_array($order->status, [OrderLifecycle::STATUS_PENDING, OrderLifecycle::STATUS_RIDER_ASSIGNED], true)) {
            throw ValidationException::withMessages([
                'rider_id' => 'This order cannot be assigned in its current state.',
            ]);
        }

        DB::transaction(function () use ($order, $rider): void {
            $locked = Rider::lockForUpdate()->find($rider->id);

            if (! $locked || ! $locked->is_active || ! $locked->is_available) {
                throw ValidationException::withMessages([
                    'rider_id' => 'This rider is no longer available.',
                ]);
            }

            $hasActive = $locked->orders()
                ->whereIn('status', OrderLifecycle::riderBusyStatuses())
                ->where('id', '!=', $order->id)
                ->exists();

            if ($hasActive) {
                throw ValidationException::withMessages([
                    'rider_id' => 'This rider already has an active order.',
                ]);
            }

            $order->update([
                'rider_id' => $locked->id,
                'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
                'rider_assigned_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
                'note' => "Assigned to {$locked->name}",
                'actor_type' => 'admin',
                'actor_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        event(new RiderAssignedEvent($fresh, $rider));
        event(new OrderStatusUpdatedEvent($fresh));
    }

    public function reassign(Order $order, Rider $rider, string $reason): void
    {
        if (! in_array($order->status, OrderLifecycle::riderBusyStatuses(), true)) {
            throw ValidationException::withMessages([
                'rider_id' => 'This order cannot be reassigned in its current state.',
            ]);
        }

        $previousRiderId = $order->rider_id;
        $preserveStatus = in_array($order->status, [
            OrderLifecycle::STATUS_PICKED_UP,
            OrderLifecycle::STATUS_ON_THE_WAY,
            OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS,
        ], true);

        DB::transaction(function () use ($order, $rider, $reason, $preserveStatus): void {
            $locked = Rider::lockForUpdate()->find($rider->id);

            if (! $locked || ! $locked->is_active || ! $locked->is_available) {
                throw ValidationException::withMessages([
                    'rider_id' => 'The selected rider is not active and available.',
                ]);
            }

            $hasActive = $locked->orders()
                ->whereIn('status', OrderLifecycle::riderBusyStatuses())
                ->where('id', '!=', $order->id)
                ->exists();

            if ($hasActive) {
                throw ValidationException::withMessages([
                    'rider_id' => 'This rider already has an active order.',
                ]);
            }

            $updates = [
                'rider_id' => $locked->id,
                'rider_assigned_at' => now(),
            ];

            if (! $preserveStatus) {
                $updates['status'] = OrderLifecycle::STATUS_RIDER_ASSIGNED;
                $updates['rider_accepted_at'] = null;
            }

            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->fresh()->status,
                'note' => "Reassigned to {$locked->name}. Reason: {$reason}",
                'actor_type' => 'admin',
                'actor_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        $fresh = $order->fresh();
        if ($previousRiderId && $previousRiderId !== $fresh->rider_id) {
            event(new RiderOrderRemovedEvent($previousRiderId, $fresh->id, 'reassigned'));
        }
        event(new RiderAssignedEvent($fresh, $rider));
        event(new OrderStatusUpdatedEvent($fresh));
    }

    public function advanceStatus(
        Order $order,
        string $newStatus,
        ?string $deliveryNote = null,
        bool $paymentCollected = false,
    ): void {
        if (! OrderLifecycle::canTransition($order->status, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$order->status} to {$newStatus}.",
            ]);
        }

        $updates = ['status' => $newStatus];
        $note = null;

        if ($newStatus === OrderLifecycle::STATUS_PICKED_UP) {
            $updates['picked_up_at'] = now();
        }

        if ($newStatus === OrderLifecycle::STATUS_ON_THE_WAY) {
            $updates['on_the_way_at'] = now();
            if ($order->status === OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS) {
                $updates['has_issue'] = false;
                $updates['issue_resolved'] = true;
            }
        }

        if ($newStatus === OrderLifecycle::STATUS_DELIVERED) {
            $updates['delivered_at'] = now();
            $note = $deliveryNote ?? 'Delivery confirmed';

            if ($paymentCollected && $order->payment_method === 'cash') {
                $updates['payment_status'] = 'collected';
            }
        }

        DB::transaction(function () use ($order, $updates, $newStatus, $note): void {
            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'note' => $note,
                'actor_type' => 'admin',
                'actor_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        if ($newStatus === OrderLifecycle::STATUS_DELIVERED) {
            $this->riderStats->sync($order->rider_id);
        }

        $fresh = $order->fresh();
        event(new OrderStatusUpdatedEvent($fresh));
        if ($newStatus === OrderLifecycle::STATUS_DELIVERED) {
            event(new OrderDeliveredEvent($fresh));
        }
    }

    public function resolveCorrection(Order $order): void
    {
        if ($order->status !== OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'status' => 'Order is not in correction_in_progress state.',
            ]);
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => OrderLifecycle::STATUS_ON_THE_WAY,
                'has_issue' => false,
                'issue_resolved' => true,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => OrderLifecycle::STATUS_ON_THE_WAY,
                'note' => 'Issue resolved - delivery resumed',
                'actor_type' => 'admin',
                'actor_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        event(new OrderStatusUpdatedEvent($order->fresh()));
    }

    public function collectPayment(Order $order): void
    {
        if ($order->status !== OrderLifecycle::STATUS_DELIVERED) {
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
                'order_id' => $order->id,
                'status' => $order->status,
                'note' => $note,
                'actor_type' => 'admin',
                'actor_id' => auth('admin')->id(),
                'created_at' => now(),
            ]);
        });

        event(new OrderStatusUpdatedEvent($order->fresh()));
    }

    public function pendingCount(): int
    {
        return Order::where('status', OrderLifecycle::STATUS_PENDING)->count();
    }
}
