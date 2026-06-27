<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\RateOrderAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RatingController extends Controller
{
    public function store(Request $request, Order $order, RateOrderAction $action): JsonResponse
    {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
            'review' => 'nullable|string|max:500',
            'flagged' => 'boolean',
            'flag_reason' => 'nullable|string|max:255',
        ]);

        try {
            $result = $action->execute(
                order: $order,
                stars: $data['stars'],
                tags: $data['tags'] ?? [],
                review: $data['review'] ?? null,
                flagged: $data['flagged'] ?? false,
                flagReason: $data['flag_reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        $points = $request->user()->fresh()->gaspoints_balance;

        return response()->json([
            'message' => 'Thanks for your feedback.',
            'rating_id' => $result['rating']->id,
            'gaspoints_awarded' => (int) $result['awarded_points'],
            'gaspoints_balance' => (int) $points,
        ], 201);
    }
}
