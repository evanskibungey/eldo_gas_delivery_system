<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\CylinderSize;
use Illuminate\Http\JsonResponse;

class CatalogueController extends Controller
{
    public function index(): JsonResponse
    {
        $sizes = CylinderSize::active()
            ->with(['price', 'brands', 'stockLevel', 'addonGroups.items' => fn ($q) => $q->where('is_active', true)])
            ->ordered()
            ->get()
            ->map(fn ($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'weight_kg'    => $s->weight_kg,
                'is_commercial'=> $s->is_commercial,
                'in_stock'     => ($s->stockLevel?->filled_count ?? 0) > 0,
                'prices'       => [
                    'refill'       => $s->price?->gas_refill_price,
                    'new_cylinder' => $s->price?->new_cylinder_price,
                    'new_gas_fill' => $s->price?->new_gas_fill_price,
                    'delivery_fee' => $s->price?->delivery_fee,
                ],
                'brands'       => $s->brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'logo_url' => $b->logo_url ?? null]),
                'addon_groups' => $s->addonGroups->map(fn ($g) => [
                    'id'             => $g->id,
                    'name'           => $g->name,
                    'selection_type' => $g->selection_type,
                    'items'          => $g->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'price' => $i->price]),
                ]),
            ]);

        return response()->json(['data' => $sizes]);
    }
}
