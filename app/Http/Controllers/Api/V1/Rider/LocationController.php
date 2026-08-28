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
            // Android reports -1 when it has no bearing (stationary, or no
            // compass fix). Accepting it here and normalising below stops the
            // whole ping — position included — being rejected as a 422.
            'heading'   => 'nullable|numeric|between:-1,360',
        ]);

        $heading = $data['heading'] ?? null;
        if ($heading !== null && ($heading < 0 || $heading > 360)) {
            $heading = null;
        }

        // Route through the tracking service so the RiderLocationUpdated
        // event fires for every position update — admin map and the
        // customer's tracking screen both depend on it.
        $tracking->updateLocation($request->user(), [
            'lat'     => $data['latitude'],
            'lng'     => $data['longitude'],
            'heading' => $heading === null ? null : (int) round($heading) % 360,
        ]);

        return response()->json(['message' => 'Location updated.']);
    }

    /**
     * Set availability to an explicit value.
     *
     * The flip-based toggleAvailability() below is not safe to retry: a dropped
     * response followed by a client retry inverts the rider twice and leaves
     * them offline while the app shows "Available".
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_available' => 'required|boolean',
        ]);

        $rider = $request->user();
        $rider->update(['is_available' => $data['is_available']]);

        return $this->availabilityResponse($rider->is_available);
    }

    /** @deprecated Use setAvailability(); kept for older rider-app installs. */
    public function toggleAvailability(Request $request): JsonResponse
    {
        $rider = $request->user();
        $rider->update(['is_available' => ! $rider->is_available]);

        return $this->availabilityResponse($rider->is_available);
    }

    private function availabilityResponse(bool $isAvailable): JsonResponse
    {
        return response()->json([
            'is_available' => $isAvailable,
            'message'      => $isAvailable ? 'You are now available.' : 'You are now offline.',
        ]);
    }
}
