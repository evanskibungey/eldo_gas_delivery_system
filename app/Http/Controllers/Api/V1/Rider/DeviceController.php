<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rider device registry for push notifications. The rider app registers its
 * FCM token on sign-in and on every token-refresh callback; without it a
 * backgrounded app cannot be woken inside the acceptance window.
 */
class DeviceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'       => 'required|string|max:512',
            'platform'    => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:32',
        ]);

        $rider = $request->user();

        // Upsert by token — the same handset may carry a stale row owned by
        // a previous rider (shared device, account switch). Clearing
        // customer_id keeps ownership single-sided.
        $device = Device::updateOrCreate(
            ['token' => $data['token']],
            [
                'rider_id'     => $rider->id,
                'customer_id'  => null,
                'platform'     => $data['platform']    ?? 'android',
                'app_version'  => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'message'   => 'Device registered.',
            'device_id' => $device->id,
        ], 201);
    }

    public function unregister(Request $request, string $token): JsonResponse
    {
        $deleted = Device::where('rider_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return response()->json([
            'message' => $deleted ? 'Device removed.' : 'Device not found.',
        ]);
    }
}
