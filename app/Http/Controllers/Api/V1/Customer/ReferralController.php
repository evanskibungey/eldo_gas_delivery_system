<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\GasPointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $customer = $request->user();
        $error = $this->gasPoints->canApplyReferral($customer);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $referrer = Customer::where('referral_code', strtoupper($data['code']))->first();

        if (! $referrer) {
            return response()->json([
                'message' => 'That referral code is not valid.',
                'errors' => ['code' => ['That referral code is not valid.']],
            ], 422);
        }

        if ($referrer->id === $customer->id) {
            return response()->json([
                'message' => 'You cannot refer yourself.',
                'errors' => ['code' => ['You cannot refer yourself.']],
            ], 422);
        }

        $customer->update([
            'referred_by' => $referrer->id,
            'referral_applied_at' => now(),
        ]);

        return response()->json([
            'message' => "Welcome - referred by {$referrer->name}.",
            'referrer_name' => $referrer->name,
        ]);
    }
}
