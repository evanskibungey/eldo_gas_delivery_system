<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return response()->json($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'       => 'required|string|max:50',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'description' => 'nullable|string|max:200',
            'is_default'  => 'boolean',
        ]);

        $data['description'] = $this->normaliseDescription($data['description'] ?? null);

        $customer = $request->user();

        if (! empty($data['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create($data);

        return response()->json($address, 201);
    }

    /**
     * Partial update. Every rule is `sometimes|required` so an absent key means
     * "leave this alone" while a key that is present but null is rejected
     * outright — a null latitude must never be able to blank out a delivery
     * pin. Latitude and longitude are additionally bound together so a half
     * update can never leave a row with a coordinate from two different places.
     */
    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'label'       => 'sometimes|required|string|max:50',
            'latitude'    => 'sometimes|required|numeric|between:-90,90',
            'longitude'   => 'sometimes|required|numeric|between:-180,180',
            'description' => 'sometimes|nullable|string|max:200',
            'is_default'  => 'sometimes|required|boolean',
        ]);

        // A half update must never leave a row holding one coordinate from the
        // old pin and one from the new. `required_with` cannot express this
        // alongside `sometimes` — `sometimes` skips the whole ruleset when the
        // key is absent, so the pairing rule would never run.
        $hasLat = array_key_exists('latitude', $data);
        $hasLng = array_key_exists('longitude', $data);
        if ($hasLat !== $hasLng) {
            throw ValidationException::withMessages([
                ($hasLat ? 'longitude' : 'latitude') => ['Latitude and longitude must be updated together.'],
            ]);
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->normaliseDescription($data['description']);
        }

        if (! empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json($address->fresh());
    }

    /** Treat a whitespace-only landmark as "no landmark" rather than storing blanks. */
    private function normaliseDescription(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }
}
