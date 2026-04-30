<?php

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminAuthService
{
    /**
     * Attempt to authenticate an admin and return the authenticated model.
     *
     * Throws ValidationException (converted to an Inertia form error by Laravel)
     * on bad credentials or a deactivated account.
     */
    public function login(array $credentials, bool $remember = false): Admin
    {
        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact support.',
            ]);
        }

        $admin->update(['last_login_at' => now()]);

        return $admin;
    }

    /**
     * Log out the currently authenticated admin and invalidate their session.
     */
    public function logout(): void
    {
        Auth::guard('admin')->logout();
    }
}
