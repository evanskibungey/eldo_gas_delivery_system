<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $customer = $request->user();

        if (! empty($data['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create($data);

        return response()->json($address, 201);
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'label'       => 'sometimes|string|max:50',
            'latitude'    => 'sometimes|numeric|between:-90,90',
            'longitude'   => 'sometimes|numeric|between:-180,180',
            'description' => 'nullable|string|max:200',
            'is_default'  => 'boolean',
        ]);

        if (! empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json($address);
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
