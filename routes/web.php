<?php

use App\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

// Health check / home redirect
Route::get('/', function () {
    return redirect()->route('customer.home');
});

/*
 * Short download link, used in every customer SMS.
 *
 * SMS is billed per 160-character segment. The full Play Store URL is 68
 * characters and pushes the order confirmation, rider-assigned and thank-you
 * messages into a second segment each; this link is 22 and keeps them in one.
 * Across four messages per delivery that is 9 billed segments down to 6.
 *
 * It is also a better link to send: a branded address on our own domain,
 * rather than a bit.ly that reads as spam and that some people will not tap.
 *
 * Deliberately NOT /app — Nginx proxies `location ^~ /app/` to Reverb for
 * WebSockets, so a trailing slash would hit the socket server instead of
 * Laravel and fail intermittently.
 *
 * 302, not 301: the destination is a system setting so the store URL can be
 * changed without a deploy, and a permanent redirect would be cached by
 * browsers long after it moved.
 */
Route::get('/get', function () {
    return redirect()->away(
        \App\Models\SystemSetting::get(
            'play_store_url',
            'https://play.google.com/store/apps/details?id=co.ke.eldogas.customer',
        ),
        302,
    );
})->name('app.download');

// Legal pages — publicly reachable (no auth), required by the app stores.
Route::view('/privacy-policy', 'legal.privacy-policy')->name('privacy-policy');

// Self-service account + data deletion (Google Play Data safety / Apple).
Route::get('/account-deletion', [AccountDeletionController::class, 'show'])->name('account-deletion');
Route::post('/account-deletion/otp', [AccountDeletionController::class, 'sendOtp'])
    ->middleware('throttle:6,1')->name('account-deletion.otp');
Route::post('/account-deletion', [AccountDeletionController::class, 'destroy'])
    ->middleware('throttle:6,1')->name('account-deletion.destroy');

// Broadcasting auth is handled by Illuminate\Broadcasting\BroadcastController.
// The user resolver is overridden in AppServiceProvider to support admin + customer guards.

// Admin routes (prefix: /admin)
Route::prefix('admin')->name('admin.')->group(function () {
    require base_path('routes/admin.php');
});

// Customer routes
Route::name('customer.')->group(function () {
    require base_path('routes/customer.php');
});
