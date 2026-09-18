<?php

use App\Http\Controllers\Admin\Auth\AdminAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─── Guest (unauthenticated) ────────────────────────────────────────────────
Route::middleware('guest.admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
});

// ─── Authenticated ──────────────────────────────────────────────────────────
Route::middleware('auth.admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Catalogue — Cylinder Sizes
    Route::resource('catalogue/sizes', \App\Http\Controllers\Admin\Catalogue\CylinderSizeController::class)
        ->names('catalogue.sizes');

    // Catalogue — Gas Brands
    Route::resource('catalogue/brands', \App\Http\Controllers\Admin\Catalogue\GasBrandController::class)
        ->names('catalogue.brands');

    // Catalogue — Pricing
    Route::get('catalogue/pricing', [\App\Http\Controllers\Admin\Catalogue\CylinderPriceController::class, 'index'])
        ->name('catalogue.pricing.index');
    Route::get('catalogue/pricing/{size}/edit', [\App\Http\Controllers\Admin\Catalogue\CylinderPriceController::class, 'edit'])
        ->name('catalogue.pricing.edit');
    Route::put('catalogue/pricing/{size}', [\App\Http\Controllers\Admin\Catalogue\CylinderPriceController::class, 'update'])
        ->name('catalogue.pricing.update');

    // Catalogue — Add-ons
    Route::resource('catalogue/addon-groups', \App\Http\Controllers\Admin\Catalogue\AddonGroupController::class)
        ->names('catalogue.addon-groups');
    Route::resource('catalogue/addon-items', \App\Http\Controllers\Admin\Catalogue\AddonItemController::class)
        ->names('catalogue.addon-items');

    // Stock
    Route::get('stock', [\App\Http\Controllers\Admin\StockController::class, 'index'])->name('stock.index');
    Route::get('stock/{size}/adjust', [\App\Http\Controllers\Admin\StockController::class, 'adjust'])->name('stock.adjust');
    Route::put('stock/{size}', [\App\Http\Controllers\Admin\StockController::class, 'update'])->name('stock.update');
    Route::get('stock/{size}/audit', [\App\Http\Controllers\Admin\StockController::class, 'auditLog'])->name('stock.audit');

    // Riders
    Route::resource('riders', \App\Http\Controllers\Admin\RiderController::class)
        ->names('riders');

    // Orders. `create` and `catalogue` sit above `{order}` so they are not
    // swallowed as an id.
    Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [\App\Http\Controllers\Admin\OrderController::class, 'create'])->name('orders.create');
    Route::get('orders/catalogue', [\App\Http\Controllers\Admin\OrderController::class, 'catalogue'])->name('orders.catalogue');
    Route::post('orders', [\App\Http\Controllers\Admin\OrderController::class, 'store'])
        // Stock is deducted and, for a counter sale, the order is closed and
        // paid — a double-submit must not become two sales.
        ->middleware('throttle:20,1')
        ->name('orders.store');
    Route::get('orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])
        ->whereNumber('order')->name('orders.show');
    Route::post('orders/{order}/assign', [\App\Http\Controllers\Admin\OrderController::class, 'assign'])->name('orders.assign');
    Route::post('orders/{order}/reassign', [\App\Http\Controllers\Admin\OrderController::class, 'reassign'])->name('orders.reassign');
    Route::post('orders/{order}/status', [\App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/cancel', [\App\Http\Controllers\Admin\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/collect-payment', [\App\Http\Controllers\Admin\OrderController::class, 'collectPayment'])->name('orders.collect-payment');

    // Order issues (Phase 9)
    Route::post('orders/{order}/issues/out-of-stock',              [\App\Http\Controllers\Admin\OrderIssueController::class, 'outOfStock'])->name('orders.issues.out-of-stock');
    Route::post('orders/{order}/issues/payment-dispute',           [\App\Http\Controllers\Admin\OrderIssueController::class, 'flagPaymentDispute'])->name('orders.issues.payment-dispute');
    Route::post('orders/{order}/issues/payment-dispute/resolve',   [\App\Http\Controllers\Admin\OrderIssueController::class, 'resolvePaymentDispute'])->name('orders.issues.payment-dispute.resolve');
    Route::post('orders/{order}/issues/resolve-correction',        [\App\Http\Controllers\Admin\OrderIssueController::class, 'resolveCorrection'])->name('orders.issues.resolve-correction');

    // Customers. `search` sits above `{customer}` so it is not swallowed as an id.
    Route::get('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/search', [\App\Http\Controllers\Admin\CustomerController::class, 'search'])->name('customers.search');
    Route::post('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])
        ->whereNumber('customer')->name('customers.show');
    Route::post('customers/{customer}/addresses', [\App\Http\Controllers\Admin\CustomerController::class, 'storeAddress'])
        ->whereNumber('customer')->name('customers.addresses.store');
    Route::post('customers/{customer}/sms-opt-out', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'toggleOptOut'])
        ->whereNumber('customer')->name('customers.sms-opt-out');

    // Address lookup for the order composer — a phone order needs coordinates,
    // and nobody taking a call is going to type latitude and longitude.
    Route::get('geocode/search', [\App\Http\Controllers\Admin\GeocodeController::class, 'search'])->name('geocode.search');

    // Bulk SMS. `create` and `preview` sit above `{campaign}` so they are not
    // swallowed as an id.
    Route::get('sms', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'index'])->name('sms.index');
    Route::get('sms/create', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'create'])->name('sms.create');
    Route::post('sms/preview', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'preview'])->name('sms.preview');
    Route::post('sms', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'store'])
        // A bulk send cannot be recalled, so a double-submit must not become
        // two campaigns.
        ->middleware('throttle:10,1')
        ->name('sms.store');
    Route::get('sms/{campaign}', [\App\Http\Controllers\Admin\SmsCampaignController::class, 'show'])
        ->whereNumber('campaign')->name('sms.show');

    // Reports
    Route::get('reports/revenue', [\App\Http\Controllers\Admin\Reports\RevenueReportController::class, 'index'])->name('reports.revenue');
    Route::get('reports/revenue/export', [\App\Http\Controllers\Admin\Reports\RevenueReportController::class, 'export'])->name('reports.revenue.export');
    Route::get('reports/orders', [\App\Http\Controllers\Admin\Reports\OrderReportController::class, 'index'])->name('reports.orders');
    Route::get('reports/orders/export', [\App\Http\Controllers\Admin\Reports\OrderReportController::class, 'export'])->name('reports.orders.export');

    // Admin users
    Route::resource('users', \App\Http\Controllers\Admin\AdminUserController::class)
        ->names('users');

    // Rider tracking — current positions (JSON, for map initial load) + location update (from Rider App)
    Route::get('tracking/positions', [\App\Http\Controllers\Admin\RiderTrackingController::class, 'positions'])
        ->name('tracking.positions');
    Route::put('tracking/riders/{rider}', [\App\Http\Controllers\Admin\RiderTrackingController::class, 'update'])
        ->name('tracking.update');

    // Dev OTP lookup — only active when AT_API_KEY is not configured
    Route::get('dev/otp', [\App\Http\Controllers\Admin\DevOtpController::class, 'show'])->name('dev.otp');
    Route::post('dev/otp/lookup', [\App\Http\Controllers\Admin\DevOtpController::class, 'lookup'])->name('dev.otp.lookup');

    // Settings
    Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/general',    [\App\Http\Controllers\Admin\SettingsController::class, 'updateGeneral'])->name('settings.general');
    Route::post('settings/shop-hours', [\App\Http\Controllers\Admin\SettingsController::class, 'updateShopHours'])->name('settings.shop-hours');
    Route::post('settings/delivery',   [\App\Http\Controllers\Admin\SettingsController::class, 'updateDelivery'])->name('settings.delivery');
    Route::post('settings/rider-pay', [\App\Http\Controllers\Admin\SettingsController::class, 'updateRiderPay'])->name('settings.rider-pay');
    Route::post('settings/points',     [\App\Http\Controllers\Admin\SettingsController::class, 'updatePoints'])->name('settings.points');
    Route::post('settings/account',    [\App\Http\Controllers\Admin\SettingsController::class, 'updateAccount'])->name('settings.account');
});
