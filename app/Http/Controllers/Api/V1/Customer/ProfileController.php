<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'id'             => $customer->id,
            'name'           => $customer->name,
            'phone'          => $customer->phone,
            'gaspoints'      => $customer->gaspoints_balance,
            'referral_code'  => $customer->referral_code,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100']);

        $request->user()->update($data);

        return response()->json(['message' => 'Profile updated.', 'name' => $data['name']]);
    }
}
