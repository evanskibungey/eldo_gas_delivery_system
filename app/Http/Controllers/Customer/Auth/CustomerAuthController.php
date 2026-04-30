<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\SendOtpRequest;
use App\Http\Requests\Customer\Auth\VerifyOtpRequest;
use App\Http\Requests\Customer\StoreNameRequest;
use App\Services\Customer\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function showPhoneEntry(): Response
    {
        return Inertia::render('Customer/Auth/PhoneEntry');
    }

    public function sendOtp(SendOtpRequest $request): RedirectResponse
    {
        $phone = $this->normalizePhone($request->validated('phone'));
        $key   = 'otp:' . $phone;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'phone' => "Too many requests. Try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($key, 600);

        $this->otp->generate($phone);

        session(['otp_phone' => $phone]);

        return redirect()->route('customer.login.verify');
    }

    public function showOtpVerification(Request $request): Response|RedirectResponse
    {
        $phone = session('otp_phone');

        if (! $phone) {
            return redirect()->route('customer.login');
        }

        return Inertia::render('Customer/Auth/OtpVerification', [
            'phone' => $phone,
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $data     = $request->validated();
        $customer = $this->otp->verify($this->normalizePhone($data['phone']), $data['token']);

        auth('customer')->login($customer);
        $request->session()->regenerate();
        session()->forget('otp_phone');

        if (empty($customer->name)) {
            return redirect()->route('customer.onboarding.name');
        }

        return redirect()->route('customer.home');
    }

    public function showSetName(): Response
    {
        return Inertia::render('Customer/Auth/SetName');
    }

    public function storeName(StoreNameRequest $request): RedirectResponse
    {
        $customer = auth('customer')->user();
        $customer->update(['name' => $request->validated('name')]);

        return redirect()->route('customer.home');
    }

    private function normalizePhone(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }

        return $phone;
    }

    public function logout(Request $request): RedirectResponse
    {
        auth('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login');
    }
}
