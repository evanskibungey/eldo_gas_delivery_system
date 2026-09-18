<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Geocoding\GeocodingService;
use App\Services\ServiceAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Address lookup for the admin order composer.
 *
 * An admin taking an order over the telephone has a place name and nothing
 * else, but customer_addresses.latitude/longitude are NOT NULL and the rider's
 * map link is built from them — so the name has to become coordinates before
 * the address can be saved.
 *
 * Mirrors Api\V1\Customer\GeocodeController: same service, same service-area
 * bound, and the same decision to return 503 rather than an empty list when
 * the upstream fails, because an empty list reads as "no such place".
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
            $results = $this->geocoding->search($data['q'], $this->serviceArea->viewbox());
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Address lookup is unavailable right now.',
            ], 503);
        }

        return response()->json(['data' => $results]);
    }
}
