<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tracking\UpdateRiderLocationRequest;
use App\Models\Rider;
use App\Services\Admin\RiderTrackingService;
use Illuminate\Http\JsonResponse;

class RiderTrackingController extends Controller
{
    public function __construct(private readonly RiderTrackingService $tracking) {}

    public function positions(): JsonResponse
    {
        return response()->json($this->tracking->getActivePositions());
    }

    public function update(UpdateRiderLocationRequest $request, Rider $rider): JsonResponse
    {
        $this->tracking->updateLocation($rider, $request->validated());

        return response()->json(['ok' => true]);
    }
}
