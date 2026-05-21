<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer device registry for push notifications. The Flutter app
 * registers its FCM token here on sign-in (and on every token-refresh
 * callback) so the SendOrderStatusPushJob can fan out notifications
 * across all of the customer's devices.
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

        $customer = $request->user();

        // Upsert by token — same physical device might have a stale row
        // owned by a previous customer (account switch).
        $device = Device::updateOrCreate(
            ['token' => $data['token']],
            [
                'customer_id'  => $customer->id,
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
        $deleted = Device::where('customer_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return response()->json([
            'message' => $deleted ? 'Device removed.' : 'Device not found.',
        ]);
    }
}
