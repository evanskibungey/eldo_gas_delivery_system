<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\RiderEarnings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $rider = $request->user();

        $todayDeliveries = $rider->orders()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        // Flat fee per delivery, NOT the value of the gas sold. This used to
        // sum total_amount, so a rider who delivered two cylinders appeared to
        // have earned several thousand shillings.
        $todayEarnings = RiderEarnings::forDeliveries($todayDeliveries);

        return response()->json([
            'id'                  => $rider->id,
            'name'                => $rider->name,
            'phone'               => $rider->phone,
            'avatar_url'          => $rider->avatar_url,
            'avg_rating'          => $rider->avg_rating,
            'total_deliveries'    => $rider->total_deliveries,
            'incident_count'      => $rider->incident_count,
            'is_safety_certified' => $rider->is_safety_certified,
            'certification_date'  => $rider->certification_date?->toDateString(),
            'certification_valid' => $rider->isCertificationValid(),
            'is_available'        => $rider->is_available,
            'is_active'           => $rider->is_active,
            'today_earnings'      => $todayEarnings,
            'today_deliveries'    => $todayDeliveries,
            'shop_status'         => [
                'always_open' => (bool) SystemSetting::get('shop_always_open', '1'),
                'opens_at'    => SystemSetting::get('shop_open_time',  '07:00'),
                'closes_at'   => SystemSetting::get('shop_close_time', '21:00'),
            ],
        ]);
    }
}
