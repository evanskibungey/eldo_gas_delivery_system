<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\UpdateCylinderPriceRequest;
use App\Models\CylinderSize;
use App\Services\Admin\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CylinderPriceController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue/Pricing/Index', [
            'sizes' => $this->catalogue->allPricing()->map(fn (CylinderSize $s) => [
                'id'            => $s->id,
                'name'          => $s->name,
                'is_commercial' => $s->is_commercial,
                'price'         => $s->price ? [
                    'gas_refill_price'   => $s->price->gas_refill_price,
                    'new_cylinder_price' => $s->price->new_cylinder_price,
                    'new_gas_fill_price' => $s->price->new_gas_fill_price,
                    'delivery_fee'       => $s->price->delivery_fee,
                    'updated_at'         => $s->price->updated_at?->diffForHumans(),
                ] : null,
            ]),
        ]);
    }

    public function edit(CylinderSize $size): Response
    {
        return Inertia::render('Admin/Catalogue/Pricing/Edit', [
            'size'  => ['id' => $size->id, 'name' => $size->name],
            'price' => $size->price ? [
                'gas_refill_price'   => $size->price->gas_refill_price,
                'new_cylinder_price' => $size->price->new_cylinder_price,
                'new_gas_fill_price' => $size->price->new_gas_fill_price,
                'delivery_fee'       => $size->price->delivery_fee,
            ] : ['gas_refill_price' => 0, 'new_cylinder_price' => 0, 'new_gas_fill_price' => 0, 'delivery_fee' => 0],
        ]);
    }

    public function update(UpdateCylinderPriceRequest $request, CylinderSize $size): RedirectResponse
    {
        $this->catalogue->updatePrice($size, $request->validated());

        return redirect()->route('admin.catalogue.pricing.index')
            ->with('success', "Prices for {$size->name} updated.");
    }
}
