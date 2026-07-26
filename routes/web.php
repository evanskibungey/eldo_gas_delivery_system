<?php

use App\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

// Health check / home redirect
Route::get('/', function () {
    return redirect()->route('customer.home');
});

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
