<?php

namespace App\Http\Controllers\Customer\Order;

use App\Events\RatingSubmittedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Order\RateOrderRequest;
use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderRatingController extends Controller
{
    public function show(Order $order): Response|RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('rate', $order), 403);

        if ($order->status !== 'delivered') return redirect()->route('customer.orders.show', $order);
        if ($order->rating) return redirect()->route('customer.orders.show', $order);

        $order->load('rider:id,name,avg_rating,photo_path');

        return Inertia::render('Customer/Order/Rate', [
            'order' => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
            ],
            'rider' => $order->rider ? [
                'name'       => $order->rider->name,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
            ] : null,
        ]);
    }

    public function store(RateOrderRequest $request, Order $order): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('rate', $order), 403);

        if ($order->status !== 'delivered' || $order->rating) {
            return redirect()->route('customer.orders.show', $order);
        }

        OrderRating::create([
            ...$request->validated(),
            'order_id'   => $order->id,
            'customer_id'=> auth('customer')->id(),
            'rider_id'   => $order->rider_id,
            'created_at' => now(),
        ]);

        // Recalculate rider average rating and total deliveries from source of truth
        if ($order->rider_id) {
            $avg            = OrderRating::where('rider_id', $order->rider_id)->avg('stars');
            $totalDeliveries = \App\Models\Order::where('rider_id', $order->rider_id)
                ->where('status', 'delivered')
                ->count();
            $order->rider()->update([
                'avg_rating'       => round($avg, 2),
                'total_deliveries' => $totalDeliveries,
            ]);
        }

        $rating = $order->fresh()->rating;
        if ($rating) {
            event(new RatingSubmittedEvent($order, $rating));
        }

        return redirect()->route('customer.orders.show', $order)
            ->with('success', 'Thank you for your feedback!');
    }
}