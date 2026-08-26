<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OtpToken;
use App\Services\Customer\AccountDeletionService;
use App\Services\Customer\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function destroy(Request $request, AccountDeletionService $deletion): RedirectResponse
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
        $deletion->purge($customer);

        return redirect()->route('account-deletion')->with('deleted', true);
    }

    private function normalizePhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }

        return $phone;
    }
}
