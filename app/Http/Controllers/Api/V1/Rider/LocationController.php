<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Services\Admin\RiderTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function update(Request $request, RiderTrackingService $tracking): JsonResponse
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading'   => 'nullable|numeric|between:0,360',
        ]);

        // Route through the tracking service so the RiderLocationUpdated
        // event fires for every position update — admin map and the
        // customer's tracking screen both depend on it.
        $tracking->updateLocation($request->user(), [
            'lat'     => $data['latitude'],
            'lng'     => $data['longitude'],
            'heading' => $data['heading'] ?? null,
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
