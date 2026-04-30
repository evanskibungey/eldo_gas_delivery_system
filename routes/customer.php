<?php

use App\Http\Controllers\Customer\Auth\CustomerAuthController;
use App\Http\Controllers\Customer\CustomerHomeController;
use App\Http\Controllers\Customer\Order\OrderController;
use App\Http\Controllers\Customer\Order\OrderIssueController;
use App\Http\Controllers\Customer\Order\OrderRatingController;
use App\Http\Controllers\Customer\Order\OrderTrackingController;
use App\Http\Controllers\Customer\CustomerAddressController;
use App\Http\Controllers\Customer\GasPoints\GasPointsController;
use App\Http\Controllers\Customer\SosController;
use Illuminate\Support\Facades\Route;

// ─── Guest (unauthenticated) ────────────────────────────────────────────────
Route::middleware('guest.customer')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showPhoneEntry'])->name('login');
    Route::post('/login/send-otp', [CustomerAuthController::class, 'sendOtp'])->name('login.send-otp');
    Route::get('/login/verify', [CustomerAuthController::class, 'showOtpVerification'])->name('login.verify');
    Route::post('/login/verify', [CustomerAuthController::class, 'verifyOtp'])->name('login.verify.attempt');
});

// ─── Authenticated ──────────────────────────────────────────────────────────
Route::middleware('auth.customer')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

    // Onboarding (name setup — shown only on first login; location is captured at first order)
    Route::get('/onboarding/name', [CustomerAuthController::class, 'showSetName'])->name('onboarding.name');
    Route::post('/onboarding/name', [CustomerAuthController::class, 'storeName'])->name('onboarding.name.store');

    // Home
    Route::get('/home', [CustomerHomeController::class, 'index'])->name('home');

    // Addresses
    Route::post('addresses/detect', [CustomerAddressController::class, 'storeDetected'])
        ->name('addresses.store-detected');
    Route::resource('addresses', CustomerAddressController::class)
        ->names('addresses');
    Route::post('addresses/{address}/set-default', [CustomerAddressController::class, 'setDefault'])
        ->name('addresses.set-default');

    // Order
    Route::get('/order/new',                  [OrderController::class, 'build'])->name('order.build');
    Route::post('/order',                     [OrderController::class, 'placeOrder'])->name('order.place');
    Route::get('/order/{order}/confirmation', [OrderController::class, 'confirmation'])->name('order.confirmation');

    // Order history
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Order issues (Phase 9)
    Route::post('/orders/{order}/issues/wrong-cylinder',    [OrderIssueController::class, 'wrongCylinder'])->name('orders.issues.wrong-cylinder');
    Route::post('/orders/{order}/issues/rider-no-show',     [OrderIssueController::class, 'riderNoShow'])->name('orders.issues.rider-no-show');
    Route::post('/orders/{order}/issues/damaged-cylinder',  [OrderIssueController::class, 'damagedCylinder'])->name('orders.issues.damaged-cylinder');

    // Live tracking
    Route::get('/orders/{order}/tracking', [OrderTrackingController::class, 'show'])->name('orders.tracking');
    Route::get('/orders/{order}/tracking/data', [OrderTrackingController::class, 'trackingData'])->name('orders.tracking.data');

    // Rating
    Route::get('/orders/{order}/rate', [OrderRatingController::class, 'show'])->name('orders.rate');
    Route::post('/orders/{order}/rate', [OrderRatingController::class, 'store'])->name('orders.rate.store');

    // GasPoints
    Route::get('/gaspoints', [GasPointsController::class, 'index'])->name('gaspoints.index');

    // SOS
    Route::get('/sos', [SosController::class, 'trigger'])->name('sos.trigger');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\Customer\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [\App\Http\Controllers\Customer\ProfileController::class, 'update'])->name('profile.update');
});
