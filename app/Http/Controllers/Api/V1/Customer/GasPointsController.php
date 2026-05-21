<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GasPointsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        $paginator = $customer->gasPointsTransactions()
            ->latest()
            ->paginate(20);

        $items = $paginator->through(fn ($t) => [
            'id'          => $t->id,
            'type'        => $t->type,
            'points'      => $t->points,
            'description' => $t->description,
            'created_at'  => $t->created_at->toIso8601String(),
        ])->items();

        return response()->json([
            'balance'      => $customer->gaspoints_balance,
            'transactions' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'total'        => $paginator->total(),
                ],
            ],
        ]);
    }
}
