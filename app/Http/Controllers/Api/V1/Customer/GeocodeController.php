<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\Geocoding\GeocodingService;
use App\Services\ServiceAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Geocoding for the customer app.
 *
 * Search is bounded to the service area, so a common street name cannot
 * return a result several hundred kilometres away that the customer has no
 * way to tell apart from a local one.
 *
 * Upstream failures return 503 rather than an empty list. An empty list reads
 * as "no such place", and the app would show the previous pin's address as if
 * it still applied.
 */
class GeocodeController extends Controller
{
    public function __construct(
        private readonly GeocodingService $geocoding,
        private readonly ServiceAreaService $serviceArea,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:120',
        ]);

        try {
            $results = $this->geocoding->search($data['q'], $this->viewbox());
        } catch (RuntimeException) {
            return $this->unavailable();
        }

        return response()->json(['data' => $results]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        try {
            $place = $this->geocoding->reverse((float) $data['lat'], (float) $data['lng']);
        } catch (RuntimeException) {
            return $this->unavailable();
        }

        return response()->json(['data' => $place]);
    }

    /** @return array{west: float, south: float, east: float, north: float} */
    private function viewbox(): array
    {
        [$lat, $lng] = $this->serviceArea->centre();
        $radiusKm = $this->serviceArea->radiusKm();

        $latDelta = $radiusKm / 110.574;
        $cosLat = max(0.01, abs(cos(deg2rad($lat))));
        $lngDelta = $radiusKm / (111.320 * $cosLat);

        return [
            'west' => $lng - $lngDelta,
            'south' => $lat - $latDelta,
            'east' => $lng + $lngDelta,
            'north' => $lat + $latDelta,
        ];
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'message' => 'Address lookup is unavailable right now.',
        ], 503);
    }
}
