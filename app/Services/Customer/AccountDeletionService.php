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
                'phone' => 'deleted_' . $id . '_' . Str::random(8),
                'phone_verified_at' => null,
                'referred_by' => null,
                'gaspoints_balance' => 0,
                'is_active' => false,
            ])->save();
        });
    }
}
