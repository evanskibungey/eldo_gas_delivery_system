<?php

namespace App\Services\Customer;

use App\Jobs\SendOtpJob;
use App\Models\Customer;
use App\Models\OtpToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function generate(string $phone): OtpToken
    {
        OtpToken::where('phone', $phone)->whereNull('used_at')->delete();

        // Longer window in dev (no real SMS) so the rider has time to check the panel.
        $expiryMinutes = empty(config('services.talksasa.api_token')) ? 30 : 10;

        $otp = OtpToken::create([
            'phone'      => $phone,
            'token'      => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes($expiryMinutes),
            'created_at' => now(),
        ]);

        Log::info("[OTP] {$phone} → {$otp->token}");

        SendOtpJob::dispatch($phone, $otp->token);

        return $otp;
    }

    public function verify(string $phone, string $token): Customer
    {
        $otp = OtpToken::where('phone', $phone)
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'token' => 'Invalid or expired code. Please try again.',
            ]);
        }

        $otp->update(['used_at' => now()]);

        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name'              => '',
                'phone_verified_at' => now(),
                'referral_code'     => $this->uniqueReferralCode(),
            ]
        );

        if (! $customer->phone_verified_at) {
            $customer->update(['phone_verified_at' => now()]);
        }

        return $customer;
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }
}
