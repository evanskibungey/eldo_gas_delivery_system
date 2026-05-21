<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aggregator endpoint consumed by the Flutter customer Home screen.
 * Composes shop status, the customer's GasPoints balance and most recent
 * order summary, and a delivery ETA hint into a single round-trip.
 */
class HomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $openTime  = SystemSetting::get('shop_open_time',  '07:00');
        $closeTime = SystemSetting::get('shop_close_time', '21:00');
        $till      = SystemSetting::get('mpesa_till_number') ?? config('services.mpesa.till_number');

        $now = Carbon::now(config('app.timezone'));
        [$openH, $openM]   = array_map('intval', explode(':', $openTime));
        [$closeH, $closeM] = array_map('intval', explode(':', $closeTime));

        $opensAtToday  = $now->copy()->setTime($openH, $openM);
        $closesAtToday = $now->copy()->setTime($closeH, $closeM);

        $isOpen = $now->betweenIncluded($opensAtToday, $closesAtToday);

        $nextOpen = null;
        if (! $isOpen) {
            $nextOpen = $now->lessThan($opensAtToday)
                ? $opensAtToday
                : $opensAtToday->copy()->addDay();
        }

        $lastOrder = Order::where('customer_id', $customer->id)
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->first();

        return response()->json([
            'shop_status' => [
                'open'       => $isOpen,
                'opens_at'   => $openTime,
                'closes_at'  => $closeTime,
                'next_open'  => $nextOpen?->toIso8601String(),
            ],
            'customer' => [
                'name'      => $customer->name,
                'gaspoints' => (int) $customer->gaspoints_balance,
            ],
            'last_order' => $lastOrder ? [
                'id'           => $lastOrder->id,
                'order_number' => $lastOrder->order_number,
                'status'       => $lastOrder->status,
                'order_type'   => $lastOrder->order_type,
                'size_name'    => $lastOrder->size?->name,
                'brand_name'   => $lastOrder->brand?->name,
                'total_amount' => (int) $lastOrder->total_amount,
                'can_reorder'  => $lastOrder->status === 'delivered',
                'created_at'   => $lastOrder->created_at?->toIso8601String(),
            ] : null,
            // Static window for now. Future: compute per-area from rider load.
            'eta_minutes' => 25,
            'payment' => [
                'mpesa_till_number' => $till !== null && $till !== '' ? (string) $till : null,
            ],
        ]);
    }
}
