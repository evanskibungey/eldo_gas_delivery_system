<?php

use Illuminate\Support\Facades\Route;

// Health check / home redirect
Route::get('/', function () {
    return redirect()->route('customer.home');
});

// Legal pages — publicly reachable (no auth), required by the app stores.
Route::view('/privacy-policy', 'legal.privacy-policy')->name('privacy-policy');

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
