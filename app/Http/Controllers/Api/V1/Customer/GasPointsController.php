<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasPointsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer     = $request->user();
        $transactions = $customer->gasPointsTransactions()
            ->latest()
            ->paginate(20)
            ->through(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'points'      => $t->points,
                'description' => $t->description,
                'created_at'  => $t->created_at->toIso8601String(),
            ]);

        return response()->json([
            'balance'      => $customer->gaspoints_balance,
            'transactions' => $transactions,
        ]);
    }
}
