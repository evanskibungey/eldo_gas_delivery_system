<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\ReportDamagedCylinderAction;
use App\Actions\ReportWrongCylinderAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer endpoint for reporting an issue on an active order. Maps the
 * `issue_type` payload onto the existing admin-side actions so the same
 * downstream listeners (admin alerts, stock flags, rider incident
 * counters) fire the same way regardless of who reported it.
 */
class IssueController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        ReportWrongCylinderAction $wrong,
        ReportDamagedCylinderAction $damaged,
    ): JsonResponse {
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validate([
            'issue_type'  => 'required|in:wrong_cylinder,damaged_cylinder,other',
            'description' => 'nullable|string|max:500',
        ]);

        $description = $data['description'] ?? '';
        $actorId     = $request->user()->id;

        try {
            switch ($data['issue_type']) {
                case 'wrong_cylinder':
                    $wrong->execute($order, $description, 'customer', $actorId);
                    break;

                case 'damaged_cylinder':
                    $damaged->execute($order, $description, 'customer', $actorId);
                    break;

                case 'other':
                    if ($order->status === 'cancelled') {
                        throw ValidationException::withMessages([
                            'order' => 'Cannot report an issue on a cancelled order.',
                        ]);
                    }
                    $order->update([
                        'has_issue'         => true,
                        'issue_type'        => 'other',
                        'issue_description' => $description,
                    ]);
                    OrderStatusHistory::create([
                        'order_id'   => $order->id,
                        'status'     => $order->status,
                        'note'       => 'Customer reported an issue: ' . $description,
                        'actor_type' => 'customer',
                        'actor_id'   => $actorId,
                        'created_at' => now(),
                    ]);
                    break;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Issue reported. We will get in touch shortly.',
        ], 201);
    }
}
