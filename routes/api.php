<?php

use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Auth\RiderAuthController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\CatalogueController;
use App\Http\Controllers\Api\V1\Customer\DeviceController;
use App\Http\Controllers\Api\V1\Customer\GamificationController;
use App\Http\Controllers\Api\V1\Customer\GasPointsController;
use App\Http\Controllers\Api\V1\Customer\GeocodeController;
use App\Http\Controllers\Api\V1\Customer\HomeController;
use App\Http\Controllers\Api\V1\Customer\IssueController;
use App\Http\Controllers\Api\V1\Customer\NotificationsController;
use App\Http\Controllers\Api\V1\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\RatingController;
use App\Http\Controllers\Api\V1\Customer\ReferralController;
use App\Http\Controllers\Api\V1\Customer\SosController as ApiSosController;
use App\Http\Controllers\Api\V1\Rider\DeviceController as RiderDeviceController;
use App\Http\Controllers\Api\V1\Rider\EarningsController;
use App\Http\Controllers\Api\V1\Rider\LocationController;
use App\Http\Controllers\Api\V1\Rider\OrderController as RiderOrderController;
use App\Http\Controllers\Api\V1\Rider\ProfileController as RiderProfileController;
use App\Http\Controllers\Api\V1\Rider\RatingController as RiderRatingController;
use App\Http\Controllers\Api\Webhooks\MpesaCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// The OTP controllers rate-limit per phone number, which stops one number
// being hammered but not one client walking thousands of numbers — every
// new number gets a fresh bucket, and every request costs a real SMS. These
// group throttles are keyed by IP for unauthenticated calls and close that
// gap. Kept generous because carrier-grade NAT puts many genuine users
// behind a single mobile IP.
Route::middleware('throttle:30,1')->prefix('auth')->group(function () {
    Route::post('request-otp', [CustomerAuthController::class, 'requestOtp']);
    Route::post('verify-otp', [CustomerAuthController::class, 'verifyOtp']);
    Route::post('logout', [CustomerAuthController::class, 'logout'])->middleware('auth.api.customer');
    Route::post('logout-all', [CustomerAuthController::class, 'logoutAll'])->middleware('auth.api.customer');
});

Route::middleware('throttle:30,1')->prefix('rider/auth')->group(function () {
    Route::post('request-otp', [RiderAuthController::class, 'requestOtp']);
    Route::post('verify-otp', [RiderAuthController::class, 'verifyOtp']);
    Route::post('logout', [RiderAuthController::class, 'logout'])->middleware('auth.api.rider');
    Route::post('logout-all', [RiderAuthController::class, 'logoutAll'])->middleware('auth.api.rider');
});

// Auth runs before the throttle so the limiter keys on the customer id
// rather than the IP — one abusive account cannot then exhaust the budget
// for everyone else sharing a carrier NAT address.
Route::middleware(['auth.api.customer', 'throttle:120,1'])->group(function () {
    Route::get('home', [HomeController::class, 'index']);
    Route::get('catalogue', [CatalogueController::class, 'index']);
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    // In-app account deletion, required by Google Play alongside the public
    // web form at /account-deletion. Tighter throttle than the group default:
    // it is irreversible and one call per account is all anyone ever needs.
    Route::delete('profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:6,1');

    // Geocoding proxy. Its own bucket because search is typed-into: a single
    // customer filling in a place name generates a burst that would otherwise
    // eat their whole 120/min allowance and start failing real requests.
    // Results are cached server-side, so most of these never reach upstream.
    Route::get('geocode/search', [GeocodeController::class, 'search'])
        ->withoutMiddleware('throttle:120,1')
        ->middleware('throttle:60,1');
    Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])
        ->withoutMiddleware('throttle:120,1')
        ->middleware('throttle:60,1');

    Route::get('addresses', [AddressController::class, 'index']);
    Route::post('addresses', [AddressController::class, 'store']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

    Route::get('orders', [CustomerOrderController::class, 'index']);
    Route::post('orders', [CustomerOrderController::class, 'store']);
    Route::get('orders/{order}', [CustomerOrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

    Route::post('orders/{order}/rate', [RatingController::class, 'store']);
    Route::post('orders/{order}/report-issue', [IssueController::class, 'store']);

    Route::post('devices', [DeviceController::class, 'register']);
    Route::delete('devices/{token}', [DeviceController::class, 'unregister'])->where('token', '.*');
    Route::get('notifications', [NotificationsController::class, 'index']);
    Route::patch('notifications/read-all', [NotificationsController::class, 'markAllRead']);

    Route::post('referral/apply', [ReferralController::class, 'apply']);

    // Safety-critical: each trigger dispatches an SMS to the shop manager.
    // Its own bucket caps runaway cost, set far above any genuine emergency
    // need — a real SOS must never be the request that gets refused.
    Route::post('sos/trigger', [ApiSosController::class, 'trigger'])
        ->withoutMiddleware('throttle:120,1')
        ->middleware('throttle:10,1');
    Route::get('gaspoints', [GasPointsController::class, 'index']);
    Route::get('gamification', [GamificationController::class, 'index']);
});

Route::middleware(['auth.api.rider', 'throttle:120,1'])->prefix('rider')->group(function () {
    Route::get('orders', [RiderOrderController::class, 'active']);
    // Declared before orders/{order} so 'history' is not swallowed as an id.
    Route::get('orders/history', [RiderOrderController::class, 'history']);
    Route::get('orders/{order}', [RiderOrderController::class, 'show'])->whereNumber('order');
    Route::put('orders/{order}/status', [RiderOrderController::class, 'updateStatus']);
    Route::post('orders/{order}/accept', [RiderOrderController::class, 'accept']);
    Route::post('orders/{order}/decline', [RiderOrderController::class, 'decline']);

    // Location pings are the highest-frequency call the rider app makes, so
    // they get their own bucket — a stuck client here must not exhaust the
    // group allowance and lock the rider out of accepting work.
    Route::put('location', [LocationController::class, 'update'])
        ->withoutMiddleware('throttle:120,1')
        ->middleware('throttle:60,1');

    // Idempotent: the client sends the state it wants, not "flip whatever you
    // have". A retry or a double-tap on the old toggle route silently inverted
    // the rider's availability.
    Route::put('location/availability', [LocationController::class, 'setAvailability']);
    // Retained for older installs still on the flip semantics. Remove once the
    // rider app's minimum supported version is past this release.
    Route::post('location/toggle-availability', [LocationController::class, 'toggleAvailability']);
    Route::get('profile', [RiderProfileController::class, 'show']);
    Route::get('earnings', [EarningsController::class, 'index']);
    Route::get('ratings', [RiderRatingController::class, 'index']);

    Route::post('devices', [RiderDeviceController::class, 'register']);
    Route::delete('devices/{token}', [RiderDeviceController::class, 'unregister'])->where('token', '.*');
});

Route::post('/webhooks/mpesa/callback', [MpesaCallbackController::class, 'handle']);

Route::post('/broadcasting/auth', function (Request $request) {
    $user = auth('rider-api')->user() ?? auth('customer-api')->user();
    if (! $user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    $request->setUserResolver(fn () => $user);
    return Broadcast::auth($request);
});
