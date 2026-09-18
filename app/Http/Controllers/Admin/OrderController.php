<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelOrderAction;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\AssignRiderRequest;
use App\Http\Requests\Admin\Orders\CancelOrderRequest;
use App\Http\Requests\Admin\Orders\ReassignRiderRequest;
use App\Http\Requests\Admin\Orders\StoreAdminOrderRequest;
use App\Http\Requests\Admin\Orders\UpdateOrderStatusRequest;
use App\Models\AddonGroup;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SystemSetting;
use App\Services\Admin\AdminOrderCreator;
use App\Services\Admin\OrderService;
use App\Support\OrderLifecycle;
use App\Support\ShopLocation;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $this->orders->paginated($filters)->through(fn (Order $order) => $this->sanitize($this->formatListRow($order))),
            'filters' => $this->sanitize($filters),
            'counts' => $this->orders->statusCounts(),
            'stale_pending' => $this->orders->stalePendingCount(),
        ]);
    }

    /**
     * The composer for an order the customer did not place themselves — taken
     * over the telephone, or rung up at the counter.
     *
     * The catalogue ships with the page rather than being fetched afterwards:
     * it is small, it never changes mid-order, and a counter sale is a queue
     * of one person waiting.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Orders/Create', [
            'catalogue' => $this->sanitize($this->catalogueData()),
            'shop_label' => ShopLocation::label(),
        ]);
    }

    /** The same catalogue as JSON, for a composer left open across a price change. */
    public function catalogue(): JsonResponse
    {
        return response()->json($this->sanitize($this->catalogueData()));
    }

    public function store(StoreAdminOrderRequest $request, AdminOrderCreator $creator): RedirectResponse
    {
        try {
            $order = $creator->create($request->validated(), auth('admin')->id());
        } catch (OutOfStockException $exception) {
            // The same guard the customer app hits. A counter sale must not be
            // able to sell a cylinder that is not on the shelf.
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with(
            'success',
            $order->isWalkIn()
                ? "Counter sale #{$order->order_number} recorded."
                : "Order #{$order->order_number} created. Assign a rider to send it out.",
        );
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer:id,name,phone',
            'rider:id,name,phone,avg_rating,photo_path,is_safety_certified,is_available',
            'size:id,name',
            'items.size:id,name',
            'items.brand:id,name',
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

    /**
     * Everything the composer needs to price an order without a round trip.
     *
     * Assembled the same way Customer\Order\OrderController@build does, and
     * deliberately so: the admin must see the prices, the brand availability
     * and the stock the customer sees, or the counter and the app quietly
     * disagree about what things cost.
     *
     * @return array<string, mixed>
     */
    private function catalogueData(): array
    {
        $sizes = CylinderSize::active()->ordered()
            ->with(['stockLevel', 'price', 'brands' => fn ($query) => $query->where('gas_brands.is_active', true)])
            ->get();

        $feeMode = SystemSetting::get('delivery_fee_mode', 'per_size');
        $globalFee = (float) SystemSetting::get('delivery_base_fee', '0.00');

        $addonsBySize = AddonGroup::active()->ordered()
            ->whereIn('size_id', $sizes->pluck('id'))
            ->with(['items' => fn ($query) => $query->active()->ordered()])
            ->get()
            ->groupBy('size_id')
            ->map(fn ($groups) => $groups->map(fn (AddonGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'selection_type' => $group->selection_type,
                'items' => $group->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                ])->values(),
            ])->values());

        return [
            'sizes' => $sizes->map(fn (CylinderSize $size) => [
                'id' => $size->id,
                'name' => $size->name,
                'weight_kg' => $size->weight_kg,
                'is_commercial' => $size->is_commercial,
                // The count, not a boolean. Someone at the counter asking for
                // four needs to be told there are two before the order is
                // built, not after PlaceOrderAction refuses it.
                'filled_count' => (int) ($size->stockLevel?->filled_count ?? 0),
                'swap_price' => (int) ($size->price?->gas_refill_price ?? 0),
                'new_price' => (int) (($size->price?->new_cylinder_price ?? 0) + ($size->price?->new_gas_fill_price ?? 0)),
                'delivery_fee' => (float) ($feeMode === 'per_size' ? ($size->price?->delivery_fee ?? 0) : $globalFee),
                'has_price' => $size->price !== null,
            ])->values()->all(),
            'brands_by_size' => $sizes->mapWithKeys(fn (CylinderSize $size) => [
                (string) $size->id => $size->brands->map(fn ($brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ])->values()->all(),
            ])->all(),
            'addons_by_size' => $addonsBySize->all(),
            // A basket is one journey, so it is charged one fee — the highest
            // of its sizes under per-size pricing. The composer needs the rule
            // to show a total that matches what the server will charge.
            'delivery_fee_mode' => $feeMode,
            'delivery_base_fee' => $globalFee,
        ];
    }

    private function formatListRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_type' => $order->order_type,
            // How it reached us: app, phone or walk_in. A counter sale on the
            // board is history, not a job — without this it reads as a
            // delivery nobody was ever sent on.
            'channel' => $order->channel ?? 'app',
            'size_name' => $order->size?->name,
            'brand_name' => $order->brand?->name,
            // What is actually on the order. The two above name its first
            // cylinder, which was all it could hold when this screen was
            // built — and this is the screen the van is packed from, so it
            // is the last place a wrong count becomes a wrong delivery.
            'items_summary' => $order->itemsSummary(),
            'cylinder_count' => $order->cylinderCount(),
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
            'channel' => $order->channel ?? 'app',
            'size_name' => $order->size?->name,
            'brand_name' => $order->brand?->name,
            // Line by line here rather than a summary string: this is the
            // packing list, so it carries its own prices and quantities.
            'items' => $order->items->map(fn ($item) => [
                'size_name' => $item->size?->name,
                'brand_name' => $item->brand?->name,
                'order_type' => $item->order_type,
                'quantity' => $item->quantity,
                'gas_price' => $item->gas_price,
                'cylinder_price' => $item->cylinder_price,
                'line_total' => $item->line_total,
            ])->values(),
            'cylinder_count' => $order->cylinderCount(),
            'gas_price' => $order->gas_price,
            'cylinder_price' => $order->cylinder_price,
            'delivery_fee' => $order->delivery_fee,
            'addons_total' => $order->addons_total,
            // Without these two the breakdown silently fails to add up to the
            // total on any order that redeemed points.
            'gaspoints_redeemed' => $order->gaspoints_redeemed,
            'gaspoints_discount' => $order->gaspoints_discount,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'delivery_label' => $order->delivery_label,
            'delivery_address' => $order->delivery_address,
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