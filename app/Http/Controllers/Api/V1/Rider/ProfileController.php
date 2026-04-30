<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $rider = $request->user();

        $todayEarnings = $rider->orders()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->sum('total_amount');

        $todayDeliveries = $rider->orders()
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

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
        ]);
    }
}
