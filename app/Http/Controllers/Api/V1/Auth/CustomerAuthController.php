<?php

namespace App\Http\Controllers\Api\V1\Auth;

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
        $data  = $request->validate(['phone' => 'required|string|max:20']);
        $phone = $this->normalizePhone($data['phone']);
        $key   = 'api-otp:' . $phone;

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

        try {
            $customer = $this->otp->verify($this->normalizePhone($data['phone']), $data['token']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        $token = $customer->createToken('mobile', ['customer'])->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'customer'     => [
                'id'             => $customer->id,
                'name'           => $customer->name,
                'phone'          => $customer->phone,
                'gaspoints'      => $customer->gaspoints_balance,
                'referral_code'  => $customer->referral_code,
                'profile_complete' => ! empty($customer->name),
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
