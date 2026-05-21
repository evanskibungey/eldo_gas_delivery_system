<?php

use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\RiderAuthController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\CatalogueController;
use App\Http\Controllers\Api\V1\Customer\DeviceController;
use App\Http\Controllers\Api\V1\Customer\GasPointsController;
use App\Http\Controllers\Api\V1\Customer\HomeController;
use App\Http\Controllers\Api\V1\Customer\IssueController;
use App\Http\Controllers\Api\V1\Customer\NotificationsController;
use App\Http\Controllers\Api\V1\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\RatingController;
use App\Http\Controllers\Api\V1\Customer\ReferralController;
use App\Http\Controllers\Api\V1\Customer\SosController as ApiSosController;
use App\Http\Controllers\Api\V1\Rider\EarningsController;
use App\Http\Controllers\Api\V1\Rider\LocationController;
use App\Http\Controllers\Api\V1\Rider\OrderController as RiderOrderController;
use App\Http\Controllers\Api\V1\Rider\ProfileController as RiderProfileController;
use App\Http\Controllers\Api\V1\Rider\RatingController as RiderRatingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ─── Customer Auth ──────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('request-otp', [CustomerAuthController::class, 'requestOtp']);
    Route::post('verify-otp',  [CustomerAuthController::class, 'verifyOtp']);
    Route::post('logout',      [CustomerAuthController::class, 'logout'])->middleware('auth.api.customer');
});

// ─── Rider Auth ─────────────────────────────────────────────────────────────
Route::prefix('rider/auth')->group(function () {
    Route::post('request-otp', [RiderAuthController::class, 'requestOtp']);
    Route::post('verify-otp',  [RiderAuthController::class, 'verifyOtp']);
    Route::post('logout',      [RiderAuthController::class, 'logout'])->middleware('auth.api.rider');
});

// ─── Customer Protected ──────────────────────────────────────────────────────
Route::middleware('auth.api.customer')->group(function () {
    // Home (aggregator: shop status + last order + GasPoints + ETA)
    Route::get('home', [HomeController::class, 'index']);

    // Catalogue (public data but requires auth for personalised in-stock)
    Route::get('catalogue', [CatalogueController::class, 'index']);

    // Profile
    Route::get('profile',  [ProfileController::class, 'show']);
    Route::put('profile',  [ProfileController::class, 'update']);

    // Addresses
    Route::get('addresses',         [AddressController::class, 'index']);
    Route::post('addresses',        [AddressController::class, 'store']);
    Route::put('addresses/{address}',   [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

    // Orders
    Route::get('orders',          [CustomerOrderController::class, 'index']);
    Route::post('orders',         [CustomerOrderController::class, 'store']);
    Route::get('orders/{order}',  [CustomerOrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

    // Rating + issue reporting (Sprint 8)
    Route::post('orders/{order}/rate',         [RatingController::class, 'store']);
    Route::post('orders/{order}/report-issue', [IssueController::class, 'store']);

    // Push devices + in-app inbox (Sprint 9)
    Route::post('devices',           [DeviceController::class, 'register']);
    Route::delete('devices/{token}', [DeviceController::class, 'unregister'])
        ->where('token', '.*');
    Route::get('notifications',           [NotificationsController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationsController::class, 'markAllRead']);

    // Referral apply (Sprint 10)
    Route::post('referral/apply', [ReferralController::class, 'apply']);

    // SOS (Sprint 11)
    Route::post('sos/trigger', [ApiSosController::class, 'trigger']);

    // Gas Points
    Route::get('gaspoints', [GasPointsController::class, 'index']);
});

// ─── Rider Protected ─────────────────────────────────────────────────────────
Route::middleware('auth.api.rider')->prefix('rider')->group(function () {
    Route::get('orders',                        [RiderOrderController::class, 'active']);
    Route::get('orders/{order}',                [RiderOrderController::class, 'show']);
    Route::put('orders/{order}/status',         [RiderOrderController::class, 'updateStatus']);
    Route::put('location',                      [LocationController::class, 'update']);
    Route::post('location/toggle-availability', [LocationController::class, 'toggleAvailability']);
    Route::get('profile',                       [RiderProfileController::class, 'show']);
    Route::get('earnings',                      [EarningsController::class, 'index']);
    Route::get('ratings',                       [RiderRatingController::class, 'index']);
});

// ─── Bearer-aware broadcast auth ──────────────────────────────────────────────
// Pusher/Reverb private channel auth for API token users (rider & customer).
// The default Broadcast::routes(['middleware' => ['web']]) only handles sessions.
Route::post('/broadcasting/auth', function (Request $request) {
    $user = auth('rider-api')->user() ?? auth('customer-api')->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    $request->setUserResolver(fn () => $user);
    return Broadcast::auth($request);
});
