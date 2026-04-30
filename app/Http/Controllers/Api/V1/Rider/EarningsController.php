<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'today');

        [$start, $label] = match ($period) {
            'week'  => [now()->startOfWeek(), 'This Week'],
            'month' => [now()->startOfMonth(), 'This Month'],
            default => [today(), 'Today'],
        };

        $orders = $request->user()
            ->orders()
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $start)
            ->orderByDesc('delivered_at')
            ->get(['id', 'order_number', 'total_amount', 'delivered_at', 'payment_method']);

        return response()->json([
            'period'         => $label,
            'total'          => $orders->sum('total_amount'),
            'delivery_count' => $orders->count(),
            'breakdown'      => $orders->map(fn ($o) => [
                'order_number'   => $o->order_number,
                'amount'         => $o->total_amount,
                'payment_method' => $o->payment_method,
                'delivered_at'   => $o->delivered_at?->toIso8601String(),
            ]),
        ]);
    }
}
