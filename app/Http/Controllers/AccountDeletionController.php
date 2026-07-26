<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Models\CustomerStreak;
use App\Models\NotificationLog;
use App\Models\OtpToken;
use App\Services\Customer\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public, self-service account + data deletion flow required by the app
 * stores (Google Play "Data safety", Apple account-deletion policy).
 *
 * The user proves ownership of the number with the same OTP used to sign
 * in, then their personal data is purged and the account is deactivated.
 * Order records are retained (anonymised) for financial/legal compliance,
 * which is disclosed on the page.
 */
class AccountDeletionController extends Controller
{
    public function show(): View
    {
        return view('legal.account-deletion');
    }

    public function sendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $phone = $this->normalizePhone($data['phone']);

        // Only dispatch a code when the number is actually registered, but
        // always respond the same way so we never reveal who has an account.
        if (Customer::where('phone', $phone)->exists()) {
            try {
                $otp->generate($phone);
            } catch (\Throwable $e) {
                // Swallow delivery errors — the generic response stands.
            }
        }

        return redirect()
            ->route('account-deletion')
            ->with('otp_sent', true)
            ->withInput(['phone' => $data['phone']]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'token' => 'required|string|size:4',
        ]);
        $phone = $this->normalizePhone($data['phone']);

        // Verify the OTP directly (without OtpService::verify, which would
        // create a customer) so deletion never resurrects an account.
        $otp = OtpToken::where('phone', $phone)
            ->where('token', $data['token'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        $customer = Customer::where('phone', $phone)->first();

        if (! $otp || ! $customer) {
            return redirect()
                ->route('account-deletion')
                ->with('otp_sent', true)
                ->withInput(['phone' => $data['phone']])
                ->withErrors(['token' => 'Invalid or expired code. Please try again.']);
        }

        $otp->update(['used_at' => now()]);
        $this->purgeCustomer($customer);

        return redirect()->route('account-deletion')->with('deleted', true);
    }

    /**
     * Remove all personal data for a customer and deactivate the account.
     * Runs in a transaction so a mid-way failure leaves nothing partial.
     */
    private function purgeCustomer(Customer $customer): void
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

    private function normalizePhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }

        return $phone;
    }
}
