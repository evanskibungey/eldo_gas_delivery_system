<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Apply a referral code to the signed-in customer's account. The
 * actual referral bonus + third-order bonus fire from
 * AwardGasPointsOnDelivery; this endpoint just records the
 * attribution.
 */
class ReferralController extends Controller
{
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $customer = $request->user();

        if ($customer->referred_by) {
            return response()->json([
                'message' => 'You have already used a referral code.',
            ], 422);
        }

        $referrer = Customer::where('referral_code', strtoupper($data['code']))
            ->orWhere('referral_code', $data['code'])
            ->first();

        if (! $referrer) {
            return response()->json([
                'message' => 'That referral code is not valid.',
                'errors'  => ['code' => ['That referral code is not valid.']],
            ], 422);
        }

        if ($referrer->id === $customer->id) {
            return response()->json([
                'message' => 'You cannot refer yourself.',
                'errors'  => ['code' => ['You cannot refer yourself.']],
            ], 422);
        }

        $customer->update(['referred_by' => $referrer->id]);

        return response()->json([
            'message'         => "Welcome — referred by {$referrer->name}.",
            'referrer_name'   => $referrer->name,
        ]);
    }
}
