<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\AddonGroup;
use App\Models\CylinderSize;
use Illuminate\Http\JsonResponse;

class CatalogueController extends Controller
{
    public function index(): JsonResponse
    {
        // Groups with no size belong to no particular cylinder. They are
        // offered alongside every size *and* stand alone as the accessory
        // catalogue, so they are fetched once and used in both places rather
        // than duplicated per size the way the old model forced.
        $universal = AddonGroup::query()
            ->universal()
            ->active()
            ->ordered()
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->get();

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
                'image_url'    => $s->image_path ? asset('storage/' . $s->image_path) : null,
                'prices'       => [
                    'refill'       => $s->price?->gas_refill_price,
                    'new_cylinder' => $s->price?->new_cylinder_price,
                    'new_gas_fill' => $s->price?->new_gas_fill_price,
                    'delivery_fee' => $s->price?->delivery_fee,
                ],
                'brands'       => $s->brands->map(fn ($b) => [
                    'id'        => $b->id,
                    'name'      => $b->name,
                    'logo_url'  => $b->logo_url ?? null,
                    'image_url' => $b->pivot->image_path
                        ? asset('storage/' . $b->pivot->image_path)
                        : ($b->logo_url ?? null),
                ]),
                // Size-specific groups first, then the universal ones. An
                // older build of the app reads this key and nothing else, so
                // it simply gains the accessories rather than breaking.
                'addon_groups' => $s->addonGroups
                    ->map(fn ($g) => $this->group($g))
                    ->concat($universal->map(fn ($g) => $this->group($g)))
                    ->values(),
            ]);

        return response()->json([
            'data' => $sizes,
            // New key. Absent from the shipped app's parser, which ignores it.
            'accessories' => $universal->map(fn ($g) => $this->group($g))->values(),
        ]);
    }

    private function group(AddonGroup $g): array
    {
        return [
            'id'             => $g->id,
            'name'           => $g->name,
            'selection_type' => $g->selection_type,
            'items'          => $g->items->map(
                fn ($i) => ['id' => $i->id, 'name' => $i->name, 'price' => $i->price],
            ),
        ];
    }
}
