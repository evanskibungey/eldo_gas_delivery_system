<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\OtpDeliveryException;
use App\Http\Controllers\Controller;
use App\Services\Customer\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $phone = $this->normalizePhone($data['phone']);
        $key = 'api-otp:' . $phone;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many requests. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 429);
        }

        RateLimiter::hit($key, 600);

        try {
            $this->otp->generate($phone);
        } catch (OtpDeliveryException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'token' => 'required|string|size:4',
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $verifyKey = 'api-otp-verify:' . $phone . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($verifyKey, 10)) {
            return response()->json([
                'message' => 'Too many verification attempts. Try again in ' . RateLimiter::availableIn($verifyKey) . ' seconds.',
            ], 429);
        }

        RateLimiter::hit($verifyKey, 600);

        try {
            $customer = $this->otp->verify($phone, $data['token']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        RateLimiter::clear($verifyKey);

        $plainTextToken = $customer->createToken('mobile', ['customer'])->plainTextToken;
        $expiresAt = $this->tokenExpiresAt();

        return response()->json([
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'gaspoints' => $customer->gaspoints_balance,
                'referral_code' => $customer->referral_code,
                'profile_complete' => ! empty($customer->name),
                'is_active' => (bool) $customer->is_active,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()?->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    private function tokenExpiresAt(): ?\Illuminate\Support\Carbon
    {
        $minutes = (int) config('sanctum.expiration');

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    private function normalizePhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }

        return $phone;
    }
}
