<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading'   => 'nullable|numeric|between:0,360',
        ]);

        $request->user()->update([
            'current_latitude'    => $data['latitude'],
            'current_longitude'   => $data['longitude'],
            'heading'             => $data['heading'] ?? null,
            'location_updated_at' => now(),
        ]);

        return response()->json(['message' => 'Location updated.']);
    }

    public function toggleAvailability(Request $request): JsonResponse
    {
        $rider = $request->user();
        $rider->update(['is_available' => ! $rider->is_available]);

        return response()->json([
            'is_available' => $rider->is_available,
            'message'      => $rider->is_available ? 'You are now available.' : 'You are now offline.',
        ]);
    }
}
