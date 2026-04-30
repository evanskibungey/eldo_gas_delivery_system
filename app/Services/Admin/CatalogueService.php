<?php

namespace App\Services\Admin;

use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CatalogueService
{
    // ─── Cylinder Sizes ──────────────────────────────────────────────────────

    public function allSizes(): Collection
    {
        return CylinderSize::with(['price', 'stockLevel'])
            ->orderBy('sort_order')
            ->get();
    }

    public function createSize(array $data): CylinderSize
    {
        return CylinderSize::create([
            'name'          => $data['name'],
            'weight_kg'     => $data['weight_kg'],
            'sort_order'    => $data['sort_order'] ?? 0,
            'is_commercial' => $data['is_commercial'] ?? false,
            'is_active'     => $data['is_active'] ?? true,
        ]);
    }

    public function updateSize(CylinderSize $size, array $data): CylinderSize
    {
        $size->update([
            'name'          => $data['name'],
            'weight_kg'     => $data['weight_kg'],
            'sort_order'    => $data['sort_order'] ?? $size->sort_order,
            'is_commercial' => $data['is_commercial'] ?? false,
            'is_active'     => $data['is_active'] ?? $size->is_active,
        ]);
        return $size;
    }

    public function deleteSize(CylinderSize $size): void
    {
        $size->delete();
    }

    // ─── Gas Brands ──────────────────────────────────────────────────────────

    public function allBrands(): Collection
    {
        return GasBrand::with('sizes')->orderBy('name')->get();
    }

    public function createBrand(array $data, ?UploadedFile $logo = null): GasBrand
    {
        $brand = GasBrand::create([
            'name'      => $data['name'],
            'logo_path' => $logo?->store('brands', 'public'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $brand->sizes()->sync($data['size_ids'] ?? []);

        return $brand;
    }

    public function updateBrand(GasBrand $brand, array $data, ?UploadedFile $logo = null): GasBrand
    {
        $logoPath = $brand->logo_path;

        if ($logo) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            $logoPath = $logo->store('brands', 'public');
        }

        $brand->update([
            'name'      => $data['name'],
            'logo_path' => $logoPath,
            'is_active' => $data['is_active'] ?? $brand->is_active,
        ]);

        $brand->sizes()->sync($data['size_ids'] ?? []);

        return $brand;
    }

    public function deleteBrand(GasBrand $brand): void
    {
        if ($brand->logo_path) Storage::disk('public')->delete($brand->logo_path);
        $brand->delete();
    }

    // ─── Pricing ─────────────────────────────────────────────────────────────

    public function allPricing(): Collection
    {
        return CylinderSize::with('price')->orderBy('sort_order')->get();
    }

    public function updatePrice(CylinderSize $size, array $data): CylinderPrice
    {
        $old = $size->price?->toArray() ?? [];

        $price = CylinderPrice::updateOrCreate(
            ['size_id' => $size->id],
            [
                'gas_refill_price'   => $data['gas_refill_price'],
                'new_cylinder_price' => $data['new_cylinder_price'],
                'new_gas_fill_price' => $data['new_gas_fill_price'],
                'delivery_fee'       => $data['delivery_fee'],
                'updated_by'         => Auth::guard('admin')->id(),
            ]
        );

        activity('catalogue')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($price)
            ->withProperties(['old' => $old, 'new' => $price->fresh()->toArray()])
            ->log("Price updated for {$size->name}");

        return $price;
    }

    // ─── Addon Groups ─────────────────────────────────────────────────────────

    public function allSizesWithGroups(): Collection
    {
        return CylinderSize::with(['addonGroups' => fn ($q) => $q->orderBy('sort_order'),
                                   'addonGroups.items' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function createGroup(array $data): AddonGroup
    {
        return AddonGroup::create([
            'size_id'        => $data['size_id'],
            'name'           => $data['name'],
            'selection_type' => $data['selection_type'],
            'sort_order'     => $data['sort_order'] ?? 0,
            'is_active'      => $data['is_active'] ?? true,
        ]);
    }

    public function updateGroup(AddonGroup $group, array $data): AddonGroup
    {
        $group->update([
            'size_id'        => $data['size_id'],
            'name'           => $data['name'],
            'selection_type' => $data['selection_type'],
            'sort_order'     => $data['sort_order'] ?? $group->sort_order,
            'is_active'      => $data['is_active'] ?? $group->is_active,
        ]);
        return $group;
    }

    public function deleteGroup(AddonGroup $group): void
    {
        $group->delete();
    }

    // ─── Addon Items ─────────────────────────────────────────────────────────

    public function createItem(array $data, ?UploadedFile $photo = null): AddonItem
    {
        return AddonItem::create([
            'group_id'    => $data['group_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'photo_path'  => $photo?->store('addons', 'public'),
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $data['is_active'] ?? true,
        ]);
    }

    public function updateItem(AddonItem $item, array $data, ?UploadedFile $photo = null): AddonItem
    {
        $photoPath = $item->photo_path;

        if ($photo) {
            if ($photoPath) Storage::disk('public')->delete($photoPath);
            $photoPath = $photo->store('addons', 'public');
        }

        $item->update([
            'group_id'    => $data['group_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'photo_path'  => $photoPath,
            'sort_order'  => $data['sort_order'] ?? $item->sort_order,
            'is_active'   => $data['is_active'] ?? $item->is_active,
        ]);

        return $item;
    }

    public function deleteItem(AddonItem $item): void
    {
        if ($item->photo_path) Storage::disk('public')->delete($item->photo_path);
        $item->delete();
    }
}
