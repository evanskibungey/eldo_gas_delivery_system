<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user();

        $ratings = $rider->ratings()
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'avg_rating'    => $rider->avg_rating,
            'total_ratings' => $rider->ratings()->count(),
            'data'          => $ratings->getCollection()->map(fn ($r) => [
                'id'            => $r->id,
                'order_id'      => $r->order_id,
                'stars'         => $r->stars,
                'tags'          => $r->tags,
                'review'        => $r->review,
                'customer_name' => $r->customer?->name ?? 'Customer',
                'created_at'    => $r->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $ratings->currentPage(),
                'last_page'    => $ratings->lastPage(),
            ],
        ]);
    }
}
