<?php

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

// Order channel — accessible by the customer who placed the order and any admin
Broadcast::channel('orders.{orderId}', function ($user, int $orderId) {
    if ($user instanceof Customer) {
        return Order::where('id', $orderId)
            ->where('customer_id', $user->id)
            ->exists();
    }
    // Admins have access to all order channels
    return $user instanceof \App\Models\Admin;
});

// Rider private channel — authenticated via Bearer token (see api.php broadcast auth route)
Broadcast::channel('rider.{riderId}', function ($user, int $riderId) {
    return $user instanceof \App\Models\Rider && $user->id === $riderId;
});

// Admin-only channels
Broadcast::channel('admin.orders', function ($user) {
    return $user instanceof \App\Models\Admin;
});

Broadcast::channel('admin.stock', function ($user) {
    return $user instanceof \App\Models\Admin;
});

Broadcast::channel('admin.riders', function ($user) {
    return $user instanceof \App\Models\Admin;
});
