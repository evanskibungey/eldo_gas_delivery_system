<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Support\RiderEarnings;
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

        // Flat fee per delivery. `total` used to be sum(total_amount) — the
        // value of the gas sold, which is the shop's revenue, not the rider's
        // pay. A rider seeing "KES 6,400 today" for two cylinders was reading
        // the shop's takings.
        $perDelivery = RiderEarnings::perDelivery();

        return response()->json([
            'period'          => $label,
            'total'           => RiderEarnings::forDeliveries($orders->count()),
            'delivery_count'  => $orders->count(),
            'rate_per_order'  => $perDelivery,
            'breakdown'       => $orders->map(fn ($o) => [
                'order_number'   => $o->order_number,
                // What the rider earned on this trip.
                'amount'         => $perDelivery,
                // What the customer paid, kept separate and clearly named so
                // the app can show it as context without confusing the two.
                'order_total'    => $o->total_amount,
                'payment_method' => $o->payment_method,
                'delivered_at'   => $o->delivered_at?->toIso8601String(),
            ]),
        ]);
    }
}
