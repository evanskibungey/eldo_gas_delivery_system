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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService    $orders,
        private readonly CancelOrderAction $cancel,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'search', 'date']);

        return Inertia::render('Admin/Orders/Index', [
            'orders'  => $this->orders->paginated($filters)->through(fn (Order $o) => $this->formatListRow($o)),
            'filters' => $filters,
            'counts'  => [
                'pending'   => Order::where('status', 'pending')->count(),
                'active'    => Order::whereIn('status', ['rider_assigned', 'picked_up', 'on_the_way'])->count(),
                'delivered' => Order::where('status', 'delivered')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
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
            'order'           => $this->formatDetail($order),
            'availableRiders' => $this->formatAvailableRiders(),
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
        $this->orders->advanceStatus($order, $request->validated('status'));

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} status updated.");
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->cancel->execute($order, $request->validated('reason'), 'admin', auth('admin')->id());

        return redirect()->route('admin.orders.show', $order)
            ->with('success', "Order #{$order->order_number} cancelled.");
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatListRow(Order $o): array
    {
        return [
            'id'             => $o->id,
            'order_number'   => $o->order_number,
            'status'         => $o->status,
            'order_type'     => $o->order_type,
            'size_name'      => $o->size?->name,
            'brand_name'     => $o->brand?->name,
            'customer_name'  => $o->customer?->name,
            'customer_phone' => $o->customer?->phone,
            'rider_name'     => $o->rider?->name,
            'total_amount'   => $o->total_amount,
            'payment_method' => $o->payment_method,
            'payment_status' => $o->payment_status,
            'has_issue'      => $o->has_issue,
            'issue_type'     => $o->issue_type,
            'created_at'     => $o->created_at->toIso8601String(),
            'created_ago'    => $o->created_at->diffForHumans(),
        ];
    }

    private function formatDetail(Order $order): array
    {
        return [
            'id'               => $order->id,
            'order_number'     => $order->order_number,
            'status'           => $order->status,
            'order_type'       => $order->order_type,
            'size_name'        => $order->size?->name,
            'brand_name'       => $order->brand?->name,
            'gas_price'        => $order->gas_price,
            'cylinder_price'   => $order->cylinder_price,
            'delivery_fee'     => $order->delivery_fee,
            'addons_total'     => $order->addons_total,
            'total_amount'     => $order->total_amount,
            'payment_method'   => $order->payment_method,
            'payment_status'   => $order->payment_status,
            'delivery_lat'     => $order->delivery_lat,
            'delivery_lng'     => $order->delivery_lng,
            'delivery_notes'   => $order->delivery_notes,
            'has_issue'         => $order->has_issue,
            'issue_type'        => $order->issue_type,
            'issue_description' => $order->issue_description,
            'issue_resolved'    => $order->issue_resolved,
            'cancel_reason'     => $order->cancel_reason,
            'cancelled_by'      => $order->cancelled_by,
            'rider_assigned_at'=> $order->rider_assigned_at?->format('d M H:i'),
            'picked_up_at'     => $order->picked_up_at?->format('d M H:i'),
            'delivered_at'     => $order->delivered_at?->format('d M H:i'),
            'cancelled_at'     => $order->cancelled_at?->format('d M H:i'),
            'created_at'       => $order->created_at->format('D, d M Y · H:i'),
            'customer' => $order->customer ? [
                'id'    => $order->customer->id,
                'name'  => $order->customer->name,
                'phone' => $order->customer->phone,
            ] : null,
            'rider' => $order->rider ? [
                'id'                  => $order->rider->id,
                'name'                => $order->rider->name,
                'phone'               => $order->rider->phone,
                'avg_rating'          => $order->rider->avg_rating,
                'avatar_url'          => $order->rider->avatar_url,
                'is_safety_certified' => $order->rider->is_safety_certified,
            ] : null,
            'addons' => $order->addons->map(fn ($a) => [
                'name'  => $a->addonItem?->name,
                'price' => $a->price,
            ]),
            'history' => $order->statusHistory->map(fn ($h) => [
                'status'     => $h->status,
                'note'       => $h->note,
                'actor_type' => $h->actor_type,
                'at'         => $h->created_at?->format('d M H:i'),
            ]),
            'can_assign'                  => in_array($order->status, ['pending']),
            'can_reassign'               => in_array($order->status, ['rider_assigned', 'picked_up', 'on_the_way']),
            'can_cancel'                 => ! in_array($order->status, ['delivered', 'cancelled']),
            'can_report_out_of_stock'    => in_array($order->status, ['pending', 'rider_assigned']) && ! $order->has_issue,
            'can_resolve_payment_dispute'=> $order->payment_status === 'disputed' && ! $order->issue_resolved,
            'next_status'                => $this->nextStatus($order->status),
        ];
    }

    private function formatAvailableRiders(): array
    {
        return $this->orders->availableRiders()->map(fn (Rider $r) => [
            'id'                  => $r->id,
            'name'                => $r->name,
            'phone'               => $r->phone,
            'avatar_url'          => $r->avatar_url,
            'avg_rating'          => $r->avg_rating,
            'total_deliveries'    => $r->total_deliveries,
            'is_safety_certified' => $r->is_safety_certified,
        ])->values()->all();
    }

    private function nextStatus(string $current): ?string
    {
        return match ($current) {
            'rider_assigned' => 'picked_up',
            'picked_up'      => 'on_the_way',
            'on_the_way'     => 'delivered',
            default          => null,
        };
    }
}
