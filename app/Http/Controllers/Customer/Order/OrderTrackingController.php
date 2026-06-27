<?php

namespace App\Http\Controllers\Customer\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderLifecycle;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function show(Order $order): Response
    {
        abort_unless(auth('customer')->user()->can('track', $order), 403);
        abort_unless($order->isActive(), 404);

        $order->load([
            'size:id,name',
            'rider:id,name,phone,avg_rating,photo_path,is_safety_certified,current_latitude,current_longitude,heading,location_updated_at',
        ]);

        return Inertia::render('Customer/Order/Tracking', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'delivery_lat' => $order->delivery_lat,
                'delivery_lng' => $order->delivery_lng,
                'delivery_notes' => $order->delivery_notes,
                'size_name' => $order->size?->name,
                'stage_index' => OrderLifecycle::trackingStageIndex($order->status),
                'has_issue' => $order->has_issue,
                'issue_type' => $order->issue_type,
                'rider_assigned_at' => $order->rider_assigned_at?->toIso8601String(),
                'can_cancel' => $order->canBeCancelledByCustomer(),
            ],
            'rider' => $order->rider ? [
                'id' => $order->rider->id,
                'name' => $order->rider->name,
                'phone' => $order->rider->phone,
                'avg_rating' => $order->rider->avg_rating,
                'avatar_url' => $order->rider->avatar_url,
                'is_certified' => $order->rider->is_safety_certified,
                'lat' => $order->rider->current_latitude,
                'lng' => $order->rider->current_longitude,
                'heading' => $order->rider->heading,
            ] : null,
            'mpesa_till' => env('MPESA_TILL_NUMBER', ''),
        ]);
    }

    public function trackingData(Order $order): JsonResponse
    {
        abort_unless(auth('customer')->user()->can('track', $order), 403);

        $order->loadMissing('rider:id,current_latitude,current_longitude,heading,location_updated_at');

        return response()->json([
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'has_issue' => (bool) $order->has_issue,
            'issue_type' => $order->issue_type,
            'rider_lat' => $order->rider?->current_latitude,
            'rider_lng' => $order->rider?->current_longitude,
            'rider_heading' => $order->rider?->heading,
            'updated_at' => $order->rider?->location_updated_at?->toIso8601String(),
        ]);
    }
}