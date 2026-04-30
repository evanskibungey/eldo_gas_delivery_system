<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\Rider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$query, $filters] = $this->buildQuery($request);

        $totalOrders   = $query->clone()->count();
        $withIssues    = $query->clone()->where('has_issue', true)->count();
        $exceptionRate = $totalOrders > 0 ? round(($withIssues / $totalOrders) * 100, 1) : 0;

        $orders = $query->paginate(25)->through(fn ($o) => [
            'id'             => $o->id,
            'order_number'   => $o->order_number,
            'status'         => $o->status,
            'order_type'     => $o->order_type,
            'customer_name'  => $o->customer?->name,
            'customer_phone' => $o->customer?->phone,
            'size_name'      => $o->size?->name,
            'brand_name'     => $o->brand?->name,
            'rider_name'     => $o->rider?->name,
            'total_amount'   => $o->total_amount,
            'payment_method' => $o->payment_method,
            'payment_status' => $o->payment_status,
            'has_issue'      => $o->has_issue,
            'issue_type'     => $o->issue_type,
            'created_at'     => $o->created_at->format('d M Y, g:i A'),
        ]);

        $sizes  = CylinderSize::active()->orderBy('sort_order')->get(['id', 'name']);
        $riders = Rider::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Reports/Orders', [
            'orders'  => $orders,
            'sizes'   => $sizes,
            'riders'  => $riders,
            'filters' => $filters,
            'summary' => [
                'total_orders'   => $totalOrders,
                'with_issues'    => $withIssues,
                'exception_rate' => $exceptionRate,
            ],
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        [$query] = $this->buildQuery($request);

        $rows = $query->limit(5000)->get();

        $csv = "Order Number,Date,Customer,Phone,Size,Brand,Order Type,Status,Payment Method,Amount (KES),Rider,Has Issue,Issue Type\n";
        foreach ($rows as $o) {
            $csv .= implode(',', [
                $o->order_number,
                $o->created_at->format('Y-m-d H:i'),
                '"' . str_replace('"', '""', $o->customer?->name ?? '') . '"',
                $o->customer?->phone ?? '',
                $o->size?->name ?? '',
                $o->brand?->name ?? '',
                $o->order_type,
                $o->status,
                $o->payment_method,
                $o->total_amount,
                '"' . str_replace('"', '""', $o->rider?->name ?? '') . '"',
                $o->has_issue ? 'Yes' : 'No',
                $o->issue_type ?? '',
            ]) . "\n";
        }

        $from     = $request->input('from', now()->subDays(29)->format('Y-m-d'));
        $to       = $request->input('to', now()->format('Y-m-d'));
        $filename = 'orders_' . Carbon::parse($from)->format('Ymd') . '_' . Carbon::parse($to)->format('Ymd') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildQuery(Request $request): array
    {
        $from      = $request->input('from', now()->subDays(29)->format('Y-m-d'));
        $to        = $request->input('to',   now()->format('Y-m-d'));
        $status    = $request->input('status');
        $orderType = $request->input('order_type');
        $riderId   = $request->input('rider_id');
        $sizeId    = $request->input('size_id');

        $toEnd = Carbon::parse($to)->endOfDay();

        $query = Order::with(['customer:id,name,phone', 'size:id,name', 'brand:id,name', 'rider:id,name'])
            ->whereBetween('created_at', [Carbon::parse($from)->startOfDay(), $toEnd])
            ->when($status,    fn ($q) => $q->where('status', $status))
            ->when($orderType, fn ($q) => $q->where('order_type', $orderType))
            ->when($riderId,   fn ($q) => $q->where('rider_id', $riderId))
            ->when($sizeId,    fn ($q) => $q->where('size_id', $sizeId))
            ->orderByDesc('created_at');

        $filters = compact('from', 'to', 'status', 'orderType', 'riderId', 'sizeId');

        return [$query, $filters];
    }
}
