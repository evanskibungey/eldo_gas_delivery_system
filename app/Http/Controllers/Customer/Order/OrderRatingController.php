<?php

namespace App\Http\Controllers\Customer\Order;

use App\Actions\RateOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Order\RateOrderRequest;
use App\Models\Order;
use App\Support\OrderLifecycle;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderRatingController extends Controller
{
    public function show(Order $order): Response|RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('rate', $order), 403);

        if ($order->status !== OrderLifecycle::STATUS_DELIVERED || $order->rating) {
            return redirect()->route('customer.orders.show', $order);
        }

        $order->load('rider:id,name,avg_rating,photo_path');

        return Inertia::render('Customer/Order/Rate', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'rider' => $order->rider ? [
                'name' => $order->rider->name,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
            ] : null,
        ]);
    }

    public function store(RateOrderRequest $request, Order $order, RateOrderAction $rateOrder): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('rate', $order), 403);

        if ($order->status !== OrderLifecycle::STATUS_DELIVERED || $order->rating) {
            return redirect()->route('customer.orders.show', $order);
        }

        $result = $rateOrder->execute($order, auth('customer')->id(), $request->validated());
        $awardedPoints = (int) ($result['awarded_points'] ?? 0);
        $message = $awardedPoints > 0
            ? "Thank you for your feedback! {$awardedPoints} GasPoints added to your account."
            : 'Thank you for your feedback!';

        return redirect()->route('customer.orders.show', $order)
            ->with('success', $message);
    }
}