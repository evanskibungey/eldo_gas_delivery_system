<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Models\CustomerStreak;
use App\Models\NotificationLog;
use App\Models\OtpToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erases a customer's personal data and deactivates the account.
 *
 * Two entry points reach this: the public web form at /account-deletion
 * (which proves ownership with an OTP, since it is unauthenticated) and the
 * in-app DELETE /profile (where the bearer token has already proved it).
 * Google Play requires both — a deletion path inside the app and a publicly
 * reachable URL for people who no longer have it installed.
 *
 * Both must purge identically, so the logic lives here rather than in either
 * controller. Two copies of a destructive transaction is two chances for one
 * of them to quietly stop matching the privacy policy.
 */
class AccountDeletionService
{
    /** Width of customers.phone, which the anonymised value has to fit. */
    private const PHONE_MAX = 20;

    /**
     * Remove all personal data for a customer and deactivate the account.
     * Runs in a transaction so a mid-way failure leaves nothing partial.
     */
    public function purge(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $id = $customer->id;

            // Revoke every API token so all devices are signed out.
            $customer->tokens()->delete();

            // Purge personal + device-linked data.
            $customer->addresses()->delete();
            $customer->devices()->delete();
            $customer->gasPointsTransactions()->delete();
            CustomerBadge::where('customer_id', $id)->delete();
            CustomerStreak::where('customer_id', $id)->delete();
            NotificationLog::where('recipient_type', 'customer')
                ->where('recipient_id', $id)
                ->delete();
            OtpToken::where('phone', $customer->phone)->delete();

            // Break referral links pointing at this account.
            Customer::where('referred_by', $id)->update(['referred_by' => null]);

            // Anonymise the row: strips PII and frees the phone number for
            // reuse, while keeping the (now anonymous) record so retained
            // order history stays referentially valid.
            // referral_code is a random share code (not PII) and is NOT NULL
            // in the schema, so it is left intact.
            $customer->forceFill([
                'name' => '',
                'phone' => self::tombstonePhone($id),
                'phone_verified_at' => null,
                'referred_by' => null,
                'gaspoints_balance' => 0,
                'is_active' => false,
            ])->save();
        });
    }

    /**
     * The value left in `phone` once the real number is gone.
     *
     * customers.phone is varchar(20) and unique. The obvious placeholder —
     * 'deleted_' + id + '_' + eight random characters — is 21 characters for
     * any four-digit id, so MySQL in strict mode rejected the write, the
     * transaction rolled back, and every customer numbered 1000 or above got
     * a 500 instead of a deleted account. The test suite runs on SQLite,
     * which ignores column lengths, so nothing caught it.
     *
     * The id alone is what makes this unique; the random tail only makes the
     * value unguessable, so it is the part that gives way when space runs
     * short.
     */
    public static function tombstonePhone(int $id): string
    {
        $prefix = 'deleted_' . $id . '_';

        return Str::limit($prefix . Str::random(self::PHONE_MAX), self::PHONE_MAX, '');
    }
}
