<?php

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Every channel below declares its guards explicitly.
|
| Without a 'guards' option, Laravel's Broadcaster::retrieveUser() falls back
| to $request->user() with no guard — which resolves the DEFAULT guard. This
| application has no default-guard users at all: everyone is an admin, a
| customer or a rider. The default therefore resolves null, authorisation is
| refused, and POST /broadcasting/auth answers 403 for everybody.
|
| That failure is invisible from the browser. The WebSocket still connects and
| still pings, so the panel shows no "offline" indicator, but Echo never sends
| a pusher:subscribe frame and not one broadcast is ever delivered.
|
| Covered by tests/Feature/Admin/BroadcastAuthTest.php.
|
*/

// Order channel — the customer who placed it, and any admin.
Broadcast::channel('orders.{orderId}', function ($user, int $orderId) {
    if ($user instanceof Customer) {
        return Order::where('id', $orderId)
            ->where('customer_id', $user->id)
            ->exists();
    }

    // Admins have access to all order channels
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin', 'customer']]);

// Rider private channel — authenticated via Bearer token (see api.php broadcast auth route).
// That route resolves the rider itself and sets it on the request, so no guard
// list is declared here.
Broadcast::channel('rider.{riderId}', function ($user, int $riderId) {
    return $user instanceof \App\Models\Rider && $user->id === $riderId;
});

// Admin-only channels
Broadcast::channel('admin.orders', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('admin.stock', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('admin.riders', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);
