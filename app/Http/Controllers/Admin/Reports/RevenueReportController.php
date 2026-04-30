<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RevenueReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from', 'Y-m-d') ?? now()->subDays(29)->startOfDay();
        $to   = $request->date('to',   'Y-m-d') ?? now()->endOfDay();

        // Ensure $to covers full day
        $toEnd = \Carbon\Carbon::parse($to)->endOfDay();

        $base = Order::whereBetween('created_at', [$from, $toEnd])
            ->where('status', 'delivered');

        // Top-level summary
        $totalRevenue    = (int) $base->clone()->sum('total_amount');
        $totalOrders     = $base->clone()->count();
        $cashRevenue     = (int) $base->clone()->where('payment_method', 'cash')->sum('total_amount');
        $mpesaRevenue    = (int) $base->clone()->where('payment_method', 'mpesa')->sum('total_amount');
        $avgOrderValue   = $totalOrders > 0 ? (int) round($totalRevenue / $totalOrders) : 0;

        // Revenue by day (last N days in range)
        $dailyRevenue = Order::selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->whereBetween('created_at', [$from, $toEnd])
            ->where('status', 'delivered')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date'    => \Carbon\Carbon::parse($row->date)->format('d M'),
                'revenue' => (int) $row->revenue,
                'orders'  => (int) $row->orders,
            ]);

        // Revenue by cylinder size
        $bySize = Order::select('size_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->with('size:id,name')
            ->whereBetween('created_at', [$from, $toEnd])
            ->where('status', 'delivered')
            ->groupBy('size_id')
            ->get()
            ->map(fn ($row) => [
                'size_name' => $row->size?->name ?? 'Unknown',
                'revenue'   => (int) $row->revenue,
                'orders'    => (int) $row->orders,
            ]);

        // Revenue by order type
        $byType = Order::selectRaw('order_type, SUM(total_amount) as revenue, COUNT(*) as orders')
            ->whereBetween('created_at', [$from, $toEnd])
            ->where('status', 'delivered')
            ->groupBy('order_type')
            ->get()
            ->map(fn ($row) => [
                'type'    => $row->order_type,
                'revenue' => (int) $row->revenue,
                'orders'  => (int) $row->orders,
            ]);

        return Inertia::render('Admin/Reports/Revenue', [
            'filters' => [
                'from' => \Carbon\Carbon::parse($from)->format('Y-m-d'),
                'to'   => \Carbon\Carbon::parse($to)->format('Y-m-d'),
            ],
            'summary' => [
                'total_revenue'   => $totalRevenue,
                'total_orders'    => $totalOrders,
                'cash_revenue'    => $cashRevenue,
                'mpesa_revenue'   => $mpesaRevenue,
                'avg_order_value' => $avgOrderValue,
            ],
            'dailyRevenue' => $dailyRevenue,
            'bySize'       => $bySize,
            'byType'       => $byType,
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $from  = $request->date('from', 'Y-m-d') ?? now()->subDays(29)->startOfDay();
        $to    = Carbon::parse($request->date('to', 'Y-m-d') ?? now())->endOfDay();

        $rows = Order::with(['customer:id,name,phone', 'size:id,name', 'rider:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'delivered')
            ->orderBy('created_at')
            ->get();

        $csv  = "Order Number,Date,Customer,Phone,Size,Payment Method,Amount (KES),Rider\n";
        foreach ($rows as $o) {
            $csv .= implode(',', [
                $o->order_number,
                $o->created_at->format('Y-m-d H:i'),
                '"' . str_replace('"', '""', $o->customer?->name ?? '') . '"',
                $o->customer?->phone ?? '',
                $o->size?->name ?? '',
                $o->payment_method,
                $o->total_amount,
                '"' . str_replace('"', '""', $o->rider?->name ?? '') . '"',
            ]) . "\n";
        }

        $filename = 'revenue_' . Carbon::parse($from)->format('Ymd') . '_' . Carbon::parse($to)->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
