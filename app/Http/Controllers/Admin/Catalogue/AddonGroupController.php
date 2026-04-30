<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\StoreAddonGroupRequest;
use App\Http\Requests\Admin\Catalogue\UpdateAddonGroupRequest;
use App\Models\AddonGroup;
use App\Services\Admin\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AddonGroupController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Catalogue/Addons/Index', [
            'sizes' => $this->catalogue->allSizesWithGroups()->map(fn ($s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'groups' => $s->addonGroups->map(fn ($g) => [
                    'id'             => $g->id,
                    'name'           => $g->name,
                    'selection_type' => $g->selection_type,
                    'sort_order'     => $g->sort_order,
                    'is_active'      => $g->is_active,
                    'items'          => $g->items->map(fn ($i) => [
                        'id'          => $i->id,
                        'name'        => $i->name,
                        'description' => $i->description,
                        'price'       => $i->price,
                        'photo_url'   => $i->photo_url,
                        'sort_order'  => $i->sort_order,
                        'is_active'   => $i->is_active,
                    ]),
                ]),
            ]),
            'allSizes' => $this->catalogue->allSizes()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Catalogue/Addons/CreateGroup', [
            'sizes'           => $this->catalogue->allSizes()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'default_size_id' => (int) request('size_id', 0),
        ]);
    }

    public function store(StoreAddonGroupRequest $request): RedirectResponse
    {
        $group = $this->catalogue->createGroup($request->validated());

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on group \"{$group->name}\" created.");
    }

    public function edit(AddonGroup $addonGroup): Response
    {
        return Inertia::render('Admin/Catalogue/Addons/EditGroup', [
            'group' => [
                'id'             => $addonGroup->id,
                'size_id'        => $addonGroup->size_id,
                'name'           => $addonGroup->name,
                'selection_type' => $addonGroup->selection_type,
                'sort_order'     => $addonGroup->sort_order,
                'is_active'      => $addonGroup->is_active,
            ],
            'sizes' => $this->catalogue->allSizes()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function update(UpdateAddonGroupRequest $request, AddonGroup $addonGroup): RedirectResponse
    {
        $this->catalogue->updateGroup($addonGroup, $request->validated());

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on group \"{$addonGroup->name}\" updated.");
    }

    public function destroy(AddonGroup $addonGroup): RedirectResponse
    {
        $name = $addonGroup->name;
        $this->catalogue->deleteGroup($addonGroup);

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on group \"{$name}\" deleted.");
    }
}
