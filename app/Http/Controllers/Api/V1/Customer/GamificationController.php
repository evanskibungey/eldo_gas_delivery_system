<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerBadge;
use App\Models\CustomerStreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $streak = CustomerStreak::firstOrCreate(
            ['customer_id' => $customer->id],
            ['current_streak' => 0, 'longest_streak' => 0, 'order_count' => 0]
        );

        $badges = CustomerBadge::where('customer_id', $customer->id)
            ->orderBy('earned_at', 'desc')
            ->get()
            ->map(fn (CustomerBadge $b) => [
                'key'       => $b->badge_key,
                'earned_at' => $b->earned_at->toIso8601String(),
            ]);

        return response()->json([
            'streak' => [
                'current'     => (int) $streak->current_streak,
                'longest'     => (int) $streak->longest_streak,
                'order_count' => (int) $streak->order_count,
            ],
            'badges' => $badges,
        ]);
    }
}
