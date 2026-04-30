<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\Customer\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RiderAuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data  = $request->validate(['phone' => 'required|string|max:20']);
        $phone = $this->normalizePhone($data['phone']);

        if (! Rider::where('phone', $phone)->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Phone number not registered as a rider.'], 422);
        }

        $key = 'rider-otp:' . $phone;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many requests. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 429);
        }

        RateLimiter::hit($key, 600);

        $this->otp->generate($phone);

        return response()->json(['message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data  = $request->validate([
            'phone' => 'required|string|max:20',
            'token' => 'required|string|size:4',
        ]);

        $phone = $this->normalizePhone($data['phone']);

        $rider = Rider::where('phone', $phone)->where('is_active', true)->first();

        if (! $rider) {
            return response()->json(['message' => 'Phone number not registered as a rider.'], 422);
        }

        // Validate OTP (re-use OtpService, but verify manually without creating customer)
        $otp = \App\Models\OtpToken::where('phone', $phone)
            ->where('token', $data['token'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $otp) {
            return response()->json(['message' => 'Invalid or expired code.', 'errors' => ['token' => ['Invalid or expired code.']]], 422);
        }

        $otp->update(['used_at' => now()]);

        $token = $rider->createToken('rider-mobile', ['rider'])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'rider'        => [
                'id'           => $rider->id,
                'name'         => $rider->name,
                'phone'        => $rider->phone,
                'is_available' => $rider->is_available,
                'avg_rating'   => $rider->avg_rating,
                'avatar_url'   => $rider->avatar_url,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function normalizePhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }

        return $phone;
    }
}
