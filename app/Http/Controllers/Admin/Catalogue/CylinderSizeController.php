<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\StoreCylinderSizeRequest;
use App\Http\Requests\Admin\Catalogue\UpdateCylinderSizeRequest;
use App\Models\CylinderSize;
use App\Services\Admin\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CylinderSizeController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue/Sizes/Index', [
            'sizes' => $this->catalogue->allSizes()->map(fn (CylinderSize $s) => [
                'id'            => $s->id,
                'name'          => $s->name,
                'weight_kg'     => $s->weight_kg,
                'sort_order'    => $s->sort_order,
                'is_commercial' => $s->is_commercial,
                'is_active'     => $s->is_active,
                'swap_price'    => $s->price?->gas_refill_price,
                'stock'         => $s->stockLevel?->filled_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalogue/Sizes/Create');
    }

    public function store(StoreCylinderSizeRequest $request): RedirectResponse
    {
        $size = $this->catalogue->createSize($request->validated());

        return redirect()->route('admin.catalogue.sizes.index')
            ->with('success', "{$size->name} cylinder size added.");
    }

    public function edit(CylinderSize $size): Response
    {
        return Inertia::render('Admin/Catalogue/Sizes/Edit', [
            'size' => [
                'id'            => $size->id,
                'name'          => $size->name,
                'weight_kg'     => $size->weight_kg,
                'sort_order'    => $size->sort_order,
                'is_commercial' => $size->is_commercial,
                'is_active'     => $size->is_active,
            ],
        ]);
    }

    public function update(UpdateCylinderSizeRequest $request, CylinderSize $size): RedirectResponse
    {
        $this->catalogue->updateSize($size, $request->validated());

        return redirect()->route('admin.catalogue.sizes.index')
            ->with('success', "{$size->name} updated.");
    }

    public function destroy(CylinderSize $size): RedirectResponse
    {
        $this->catalogue->deleteSize($size);

        return redirect()->route('admin.catalogue.sizes.index')
            ->with('success', 'Cylinder size deleted.');
    }
}
