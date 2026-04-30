<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\StoreGasBrandRequest;
use App\Http\Requests\Admin\Catalogue\UpdateGasBrandRequest;
use App\Models\GasBrand;
use App\Services\Admin\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GasBrandController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue/Brands/Index', [
            'brands' => $this->catalogue->allBrands()->map(fn (GasBrand $b) => [
                'id'        => $b->id,
                'name'      => $b->name,
                'logo_url'  => $b->logo_url,
                'is_active' => $b->is_active,
                'sizes'     => $b->sizes->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalogue/Brands/Create', [
            'sizes' => $this->catalogue->allSizes()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function store(StoreGasBrandRequest $request): RedirectResponse
    {
        $brand = $this->catalogue->createBrand($request->validated(), $request->file('logo'));

        return redirect()->route('admin.catalogue.brands.index')
            ->with('success', "{$brand->name} brand added.");
    }

    public function edit(GasBrand $brand): Response
    {
        return Inertia::render('Admin/Catalogue/Brands/Edit', [
            'brand' => [
                'id'        => $brand->id,
                'name'      => $brand->name,
                'logo_url'  => $brand->logo_url,
                'is_active' => $brand->is_active,
                'size_ids'  => $brand->sizes->pluck('id'),
            ],
            'sizes' => $this->catalogue->allSizes()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function update(UpdateGasBrandRequest $request, GasBrand $brand): RedirectResponse
    {
        $this->catalogue->updateBrand($brand, $request->validated(), $request->file('logo'));

        return redirect()->route('admin.catalogue.brands.index')
            ->with('success', "{$brand->name} updated.");
    }

    public function destroy(GasBrand $brand): RedirectResponse
    {
        $name = $brand->name;
        $this->catalogue->deleteBrand($brand);

        return redirect()->route('admin.catalogue.brands.index')
            ->with('success', "{$name} deleted.");
    }
}
