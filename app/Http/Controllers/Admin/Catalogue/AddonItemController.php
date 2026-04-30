<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogue\StoreAddonItemRequest;
use App\Http\Requests\Admin\Catalogue\UpdateAddonItemRequest;
use App\Models\AddonItem;
use App\Services\Admin\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AddonItemController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function create(): Response
    {
        return Inertia::render('Admin/Catalogue/Addons/CreateItem', [
            'sizes'            => $this->catalogue->allSizesWithGroups()->map(fn ($s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'groups' => $s->addonGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name]),
            ]),
            'default_group_id' => (int) request('group_id', 0),
        ]);
    }

    public function store(StoreAddonItemRequest $request): RedirectResponse
    {
        $item = $this->catalogue->createItem($request->validated(), $request->file('photo'));

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on item \"{$item->name}\" created.");
    }

    public function edit(AddonItem $addonItem): Response
    {
        return Inertia::render('Admin/Catalogue/Addons/EditItem', [
            'item' => [
                'id'          => $addonItem->id,
                'group_id'    => $addonItem->group_id,
                'name'        => $addonItem->name,
                'description' => $addonItem->description,
                'price'       => $addonItem->price,
                'photo_url'   => $addonItem->photo_url,
                'sort_order'  => $addonItem->sort_order,
                'is_active'   => $addonItem->is_active,
            ],
            'sizes' => $this->catalogue->allSizesWithGroups()->map(fn ($s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'groups' => $s->addonGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name]),
            ]),
        ]);
    }

    public function update(UpdateAddonItemRequest $request, AddonItem $addonItem): RedirectResponse
    {
        $this->catalogue->updateItem($addonItem, $request->validated(), $request->file('photo'));

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on item \"{$addonItem->name}\" updated.");
    }

    public function destroy(AddonItem $addonItem): RedirectResponse
    {
        $name = $addonItem->name;
        $this->catalogue->deleteItem($addonItem);

        return redirect()->route('admin.catalogue.addon-groups.index')
            ->with('success', "Add-on item \"{$name}\" deleted.");
    }
}
