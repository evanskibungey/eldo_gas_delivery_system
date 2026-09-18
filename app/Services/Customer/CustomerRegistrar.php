<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Support\ManagerContacts;

/**
 * The one way a customer record comes into existence.
 *
 * Until now that was `OtpService::firstOrCreate` with a private referral-code
 * generator, which meant an admin taking an order over the phone had no way to
 * create the caller: `customers.referral_code` is NOT NULL UNIQUE and nothing
 * outside OtpService could mint one.
 *
 * Phone numbers are normalised on the way in. `customers.phone` is UNIQUE, so a
 * walk-in entered as 0712345678 and the same person later logging in as
 * +254712345678 must resolve to one row — otherwise the second insert fails and
 * their walk-in history is orphaned from the account they end up using.
 */
class CustomerRegistrar
{
    /**
     * Find the customer behind this number, or create them.
     *
     * @param  string  $createdVia  'app' when they registered themselves,
     *                              'admin' when a counter or phone order made
     *                              the record for them.
     */
    public function findOrCreateByPhone(
        string $phone,
        ?string $name = null,
        string $createdVia = 'app',
        bool $verified = false,
    ): Customer {
        $phone = $this->normalisePhone($phone);

        $customer = Customer::where('phone', $phone)->first();

        if ($customer) {
            // An existing customer keeps the name they already have. A rushed
            // counter entry must not overwrite the one they set themselves.
            if ($name !== null && trim($name) !== '' && trim((string) $customer->name) === '') {
                $customer->update(['name' => trim($name)]);
            }

            return $customer->fresh();
        }

        return Customer::create([
            'name' => trim((string) $name),
            'phone' => $phone,
            // Only a completed OTP round trip proves the number. An admin
            // typing it in does not, and this null is what the conversion
            // badge reads.
            'phone_verified_at' => $verified ? now() : null,
            'referral_code' => $this->uniqueReferralCode(),
            'is_active' => true,
            'created_via' => $createdVia,
        ]);
    }

    /**
     * Eight characters, no vowels and no 0/1/I/O — the code gets read aloud and
     * typed by hand, so the ambiguous glyphs are left out.
     */
    public function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }

    /** Local 07… becomes +2547…; anything already international is left alone. */
    private function normalisePhone(string $phone): string
    {
        return ManagerContacts::normalize($phone);
    }
}
