<?php

namespace App\Services\Customer;

use App\Exceptions\OtpDeliveryException;
use App\Models\Customer;
use App\Models\OtpToken;
use App\Models\SystemSetting;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly SmsServiceInterface $sms) {}

    public function generate(string $phone): OtpToken
    {
        // Store-review bypass: never dispatch a real SMS to the reserved
        // reviewer number. verify() accepts the fixed code from config.
        if ($this->isReviewerPhone($phone)) {
            Log::info("[OTP] Skipping SMS for reviewer number {$phone}");

            return (new OtpToken())->forceFill([
                'phone' => $phone,
                'token' => (string) config('services.customer_review.otp'),
                'expires_at' => now()->addYear(),
            ]);
        }

        $expiryMinutes = empty(config('services.talksasa.api_token')) ? 30 : 10;

        $otp = OtpToken::create([
            'phone' => $phone,
            'token' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes($expiryMinutes),
            'created_at' => now(),
        ]);

        $appName = SystemSetting::get('app_name', 'EldoGas');
        $message = "Your {$appName} code is: {$otp->token}. Valid for {$expiryMinutes} minutes. Do not share.";

        if (! $this->sms->send($phone, $message)) {
            $otp->delete();
            throw OtpDeliveryException::unavailable();
        }

        OtpToken::where('phone', $phone)
            ->whereNull('used_at')
            ->whereKeyNot($otp->id)
            ->delete();

        Log::info("[OTP] Code sent to {$phone}");

        return $otp;
    }

    public function verify(string $phone, string $token): Customer
    {
        // Store-review bypass: accept the fixed reviewer code without a
        // database OTP record. A wrong code falls through to the normal
        // (guaranteed-to-fail, since no token was stored) path below.
        if ($this->isReviewerPhone($phone) && $this->matchesReviewerOtp($token)) {
            return $this->reviewerCustomer($phone);
        }

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
                'name' => '',
                'phone_verified_at' => now(),
                'referral_code' => $this->uniqueReferralCode(),
                'is_active' => true,
            ]
        );

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'Your account is currently inactive. Please contact support.',
            ]);
        }

        $updates = [];
        if (! $customer->phone_verified_at) {
            $updates['phone_verified_at'] = now();
        }
        if (! $customer->referral_code) {
            $updates['referral_code'] = $this->uniqueReferralCode();
        }
        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer->fresh();
    }

    /**
     * True when store-review credentials are configured AND the incoming
     * phone is the reserved reviewer number. Returns false when either env
     * value is blank, so the bypass is fully off in normal environments.
     */
    private function isReviewerPhone(string $phone): bool
    {
        $reviewer = (string) config('services.customer_review.phone');
        $code = (string) config('services.customer_review.otp');

        return $reviewer !== '' && $code !== '' && hash_equals($reviewer, $phone);
    }

    private function matchesReviewerOtp(string $token): bool
    {
        $code = (string) config('services.customer_review.otp');

        return $code !== '' && hash_equals($code, $token);
    }

    /**
     * The seeded reviewer customer. Has a name set (so profile_complete is
     * true and the app routes straight to Home) and is always active.
     */
    private function reviewerCustomer(string $phone): Customer
    {
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Play Reviewer',
                'phone_verified_at' => now(),
                'referral_code' => $this->uniqueReferralCode(),
                'is_active' => true,
            ]
        );

        $updates = [];
        if (empty($customer->name)) {
            $updates['name'] = 'Play Reviewer';
        }
        if (! $customer->is_active) {
            $updates['is_active'] = true;
        }
        if (! $customer->phone_verified_at) {
            $updates['phone_verified_at'] = now();
        }
        if (! $customer->referral_code) {
            $updates['referral_code'] = $this->uniqueReferralCode();
        }
        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer->fresh();
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }
}
