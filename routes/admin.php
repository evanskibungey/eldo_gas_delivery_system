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

    // Orders
    Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('orders.show');
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

    // Customers
    Route::get('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');

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
    Route::post('settings/commission', [\App\Http\Controllers\Admin\SettingsController::class, 'updateCommission'])->name('settings.commission');
    Route::post('settings/account',    [\App\Http\Controllers\Admin\SettingsController::class, 'updateAccount'])->name('settings.account');
});
