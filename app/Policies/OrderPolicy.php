<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Order;

class OrderPolicy
{
    public function view(Customer $customer, Order $order): bool
    {
        return $customer->id === $order->customer_id;
    }

    public function cancel(Customer $customer, Order $order): bool
    {
        return $customer->id === $order->customer_id;
    }

    public function rate(Customer $customer, Order $order): bool
    {
        return $customer->id === $order->customer_id;
    }

    public function track(Customer $customer, Order $order): bool
    {
        return $customer->id === $order->customer_id;
    }
}
