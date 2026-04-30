<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\Rider;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today     = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();

        // ── Metric cards ──────────────────────────────────────────────────────
        $todayOrders   = Order::whereDate('created_at', $today)->count();
        $todayRevenue  = Order::whereDate('created_at', $today)
            ->where('status', 'delivered')
            ->sum('total_amount');
        $pendingOrders = Order::where('status', 'pending')->count();
        $activeRiders  = Rider::where('is_active', true)->where('is_available', true)->count();
        $lowStockCount = StockLevel::where('filled_count', '<=', DB::raw('low_stock_threshold'))->count();

        // ── Last 7 days revenue (cash vs M-Pesa) ──────────────────────────────
        $dailyRevenue = Order::where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash"),
                DB::raw("SUM(CASE WHEN payment_method = 'mpesa' THEN total_amount ELSE 0 END) as mpesa"),
                DB::raw('COUNT(*) as orders'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date'   => $r->date,
                'cash'   => (float) $r->cash,
                'mpesa'  => (float) $r->mpesa,
                'orders' => (int) $r->orders,
            ]);

        // ── Orders by size (donut data) ───────────────────────────────────────
        $bySize = Order::where('status', 'delivered')
            ->whereDate('created_at', '>=', now()->subDays(29))
            ->select('size_id', DB::raw('COUNT(*) as total'))
            ->with('size:id,name')
            ->groupBy('size_id')
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->size?->name ?? 'Unknown',
                'value' => (int) $r->total,
            ]);

        // ── Recent 10 orders ──────────────────────────────────────────────────
        $recentOrders = Order::with(['customer:id,name,phone', 'rider:id,name', 'size:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($o) => [
                'id'            => $o->id,
                'order_number'  => $o->order_number,
                'status'        => $o->status,
                'total_amount'  => $o->total_amount,
                'payment_method'=> $o->payment_method,
                'customer_name' => $o->customer?->name,
                'rider_name'    => $o->rider?->name,
                'size_name'     => $o->size?->name,
                'created_ago'   => $o->created_at->diffForHumans(),
            ]);

        // ── Top 5 riders this week ────────────────────────────────────────────
        $topRiders = Order::where('status', 'delivered')
            ->where('created_at', '>=', $weekStart)
            ->whereNotNull('rider_id')
            ->select('rider_id', DB::raw('COUNT(*) as deliveries'), DB::raw('SUM(total_amount) as revenue'))
            ->with('rider:id,name,avg_rating,photo_path')
            ->groupBy('rider_id')
            ->orderByDesc('deliveries')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->rider_id,
                'name'        => $r->rider?->name ?? '—',
                'avg_rating'  => $r->rider?->avg_rating,
                'avatar_url'  => $r->rider?->avatar_url,
                'deliveries'  => (int) $r->deliveries,
                'revenue'     => (float) $r->revenue,
            ]);

        // ── Stock status (mini table) ─────────────────────────────────────────
        $stock = StockLevel::with('size:id,name')
            ->orderBy('filled_count')
            ->get()
            ->map(fn ($s) => [
                'size_name'     => $s->size?->name ?? '—',
                'quantity'      => $s->filled_count,
                'reorder_point' => $s->low_stock_threshold,
                'status'        => match (true) {
                    $s->filled_count <= 0                          => 'depleted',
                    $s->filled_count <= $s->low_stock_threshold    => 'low',
                    default                                        => 'ok',
                },
            ]);

        return Inertia::render('Admin/Dashboard/Index', [
            'metrics' => [
                'today_orders'   => $todayOrders,
                'today_revenue'  => (float) $todayRevenue,
                'pending_orders' => $pendingOrders,
                'active_riders'  => $activeRiders,
                'low_stock'      => $lowStockCount,
            ],
            'dailyRevenue' => $dailyRevenue,
            'bySize'       => $bySize,
            'recentOrders' => $recentOrders,
            'topRiders'    => $topRiders,
            'stock'        => $stock,
        ]);
    }
}
