<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\AssignRiderRequest;
use App\Http\Requests\Admin\Orders\CancelOrderRequest;
use App\Http\Requests\Admin\Orders\ReassignRiderRequest;
use App\Http\Requests\Admin\Orders\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\Rider;
use App\Services\Admin\OrderService;
use App\Support\OrderLifecycle;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly CancelOrderAction $cancel,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search', 'date']);
        $activeDispatchStatuses = array_values(array_filter(
            OrderLifecycle::activeStatuses(),
            fn (string $status) => $status !== OrderLifecycle::STATUS_PENDING,
        ));

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $this->orders->paginated($filters)->through(fn (Order $order) => $this->sanitize($this->formatListRow($order))),
            'filters' => $this->sanitize($filters),
            'counts' => [
                'pending' => Order::where('status', OrderLifecycle::STATUS_PENDING)->count(),
                'active' => Order::whereIn('status', $activeDispatchStatuses)->count(),
                'rider_assigned' => Order::where('status', OrderLifecycle::STATUS_RIDER_ASSIGNED)->count(),
                'picked_up' => Order::where('status', OrderLifecycle::STATUS_PICKED_UP)->count(),
                'on_the_way' => Order::whereIn('status', [OrderLifecycle::STATUS_ON_THE_WAY, OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS])->count(),
                'delivered' => Order::where('status', OrderLifecycle::STATUS_DELIVERED)->count(),
                'cancelled' => Order::where('status', OrderLifecycle::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer:id,name,phone',
            'rider:id,name,phone,avg_rating,photo_path,is_safety_certified,is_available',
            'size:id,name',
            'brand:id,name',
            'addons.addonItem:id,name',
            'statusHistory',
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $this->sanitize($this->formatDetail($order)),
            'availableRiders' => $this->sanitize($this->formatAvailableRiders()),
        ]);
    }

    public function assign(AssignRiderRequest $request, Order $order): RedirectResponse
    {
        $rider = Rider::findOrFail($request->validated('rider_id'));
        $this->orders->assign($order, $rider);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} assigned to {$rider->name}.");
    }

    public function reassign(ReassignRiderRequest $request, Order $order): RedirectResponse
    {
        $rider = Rider::findOrFail($request->validated('rider_id'));
        $this->orders->reassign($order, $rider, $request->validated('reason'));

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} reassigned to {$rider->name}.");
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        $this->orders->advanceStatus(
            $order,
            $data['status'],
            $data['delivery_note'] ?? null,
            (bool) ($data['payment_collected'] ?? false),
        );

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} status updated.");
    }

    public function collectPayment(Order $order): RedirectResponse
    {
        $this->orders->collectPayment($order);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Payment collected for order #{$order->order_number}.");
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();
        $restoreInventory = array_key_exists('inventory_returned', $data)
            ? (bool) $data['inventory_returned']
            : null;

        $this->cancel->execute($order, $data['reason'], 'admin', auth('admin')->id(), $restoreInventory);

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} cancelled.");
    }

    private function formatListRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_type' => $order->order_type,
            'size_name' => $order->size?->name,
            'brand_name' => $order->brand?->name,
            'customer_name' => $order->customer?->name,
            'customer_phone' => $order->customer?->phone,
            'rider_name' => $order->rider?->name,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'has_issue' => $order->has_issue,
            'issue_type' => $order->issue_type,
            'created_at' => $order->created_at->toIso8601String(),
            'created_ago' => $order->created_at->diffForHumans(),
        ];
    }

    private function formatDetail(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_type' => $order->order_type,
            'size_name' => $order->size?->name,
            'brand_name' => $order->brand?->name,
            'gas_price' => $order->gas_price,
            'cylinder_price' => $order->cylinder_price,
            'delivery_fee' => $order->delivery_fee,
            'addons_total' => $order->addons_total,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'delivery_notes' => $order->delivery_notes,
            'has_issue' => $order->has_issue,
            'issue_type' => $order->issue_type,
            'issue_description' => $order->issue_description,
            'issue_resolved' => $order->issue_resolved,
            'cancel_reason' => $order->cancel_reason,
            'cancelled_by' => $order->cancelled_by,
            'rider_assigned_at' => $order->rider_assigned_at?->format('d M H:i'),
            'picked_up_at' => $order->picked_up_at?->format('d M H:i'),
            'on_the_way_at' => $order->on_the_way_at?->format('d M H:i'),
            'delivered_at' => $order->delivered_at?->format('d M H:i'),
            'cancelled_at' => $order->cancelled_at?->format('d M H:i'),
            'created_at' => $order->created_at->format('D, d M Y · H:i'),
            'customer' => $order->customer ? [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'phone' => $order->customer->phone,
            ] : null,
            'rider' => $order->rider ? [
                'id' => $order->rider->id,
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
                'is_safety_certified' => $order->rider->is_safety_certified,
            ] : null,
            'addons' => $order->addons->map(fn ($addon) => [
                'name' => $addon->addonItem?->name,
                'price' => $addon->price,
            ]),
            'history' => $order->statusHistory->map(fn ($history) => [
                'status' => $history->status,
                'note' => $history->note,
                'actor_type' => $history->actor_type,
                'at' => $history->created_at?->format('d M H:i'),
            ]),
            'can_assign' => $order->status === OrderLifecycle::STATUS_PENDING,
            'can_reassign' => in_array($order->status, OrderLifecycle::riderBusyStatuses(), true),
            'can_cancel' => ! in_array($order->status, OrderLifecycle::terminalStatuses(), true),
            'inventory_restore_required' => ! OrderLifecycle::canRestoreInventoryOnCancel($order->status),
            'can_report_out_of_stock' => in_array($order->status, [OrderLifecycle::STATUS_PENDING, OrderLifecycle::STATUS_RIDER_ASSIGNED], true) && ! $order->has_issue,
            'can_resolve_payment_dispute' => $order->payment_status === 'disputed' && ! $order->issue_resolved,
            'can_resume_delivery' => $order->status === OrderLifecycle::STATUS_CORRECTION_IN_PROGRESS,
            'can_collect_payment' => $order->status === OrderLifecycle::STATUS_DELIVERED && $order->payment_status === 'pending',
            'next_status' => OrderLifecycle::nextStatus($order->status),
        ];
    }

    private function formatAvailableRiders(): array
    {
        return $this->orders->availableRiders()->map(fn (Rider $rider) => [
            'id' => $rider->id,
            'name' => $rider->name,
            'phone' => $rider->phone,
            'avatar_url' => $rider->avatar_url,
            'avg_rating' => $rider->avg_rating,
            'total_deliveries' => $rider->total_deliveries,
            'is_safety_certified' => $rider->is_safety_certified,
        ])->values()->all();
    }

    private function sanitize(mixed $value): mixed
    {
        return Utf8Sanitizer::clean($value);
    }
}