<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'id'               => $customer->id,
            'name'             => $customer->name,
            'phone'            => $customer->phone,
            'gaspoints'        => $customer->gaspoints_balance,
            'referral_code'    => $customer->referral_code,
            'profile_complete' => ! empty($customer->name),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        $request->user()->update($data);

        return response()->json(['message' => 'Profile updated.', 'name' => $data['name']]);
    }

    /**
     * Delete the signed-in customer's account and personal data.
     *
     * Google Play requires an in-app deletion path alongside the public web
     * form at /account-deletion. That form needs an OTP because anyone can
     * reach it and type a number; here the bearer token has already proved
     * ownership, so re-verifying would only add friction — the same device
     * could request a fresh code anyway.
     *
     * Purging revokes every token, including the one authenticating this
     * request, so the caller is signed out on all devices by the time the
     * 200 lands. Order rows survive, anonymised, for financial retention.
     */
    public function destroy(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $deletion->purge($request->user());

        return response()->json(['message' => 'Account deleted.']);
    }
}
