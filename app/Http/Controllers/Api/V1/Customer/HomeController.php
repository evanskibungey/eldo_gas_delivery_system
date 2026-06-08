<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\EtaService;
use App\Services\ShopHoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aggregator endpoint consumed by the Flutter customer Home screen.
 * Composes shop status, the customer's GasPoints balance and most recent
 * order summary, and a delivery ETA hint into a single round-trip.
 */
class HomeController extends Controller
{
    public function index(Request $request, ShopHoursService $shopHours, EtaService $eta): JsonResponse
    {
        $customer = $request->user();
        $till      = SystemSetting::get('mpesa_till_number') ?? config('services.mpesa.till_number');
        $shopStatus = $shopHours->status();

        $lastOrder = Order::where('customer_id', $customer->id)
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->first();

        // Use the customer's last delivery address as a distance hint for ETA.
        $etaMinutes = $eta->estimate(
            $lastOrder?->delivery_lat,
            $lastOrder?->delivery_lng,
        );

        return response()->json([
            'shop_status' => $shopStatus,
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
                'can_reorder'  => $lastOrder->canBeReorderedByCustomer(),
                'created_at'   => $lastOrder->created_at?->toIso8601String(),
            ] : null,
            'eta_minutes' => $etaMinutes,
            'payment' => [
                'mpesa_till_number' => $till !== null && $till !== '' ? (string) $till : null,
            ],
        ]);
    }
}
