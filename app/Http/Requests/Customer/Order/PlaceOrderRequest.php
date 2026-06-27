<?php

namespace App\Http\Requests\Customer\Order;

use App\Models\AddonItem;
use App\Models\CylinderSize;
use App\Services\GasPointsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_type' => ['required', 'in:swap,new_cylinder'],
            'size_id' => ['required', 'exists:cylinder_sizes,id'],
            'brand_id' => ['required', 'exists:gas_brands,id'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['exists:addon_items,id'],
            'address_id' => ['required', 'exists:customer_addresses,id'],
            'payment_method' => ['required', 'in:cash,mpesa'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'redemption_points' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $addressId = $this->input('address_id');
                $customer = auth('customer')->user();
                if ($addressId && $customer && ! $customer->addresses()->where('id', $addressId)->exists()) {
                    $validator->errors()->add('address_id', 'The selected address does not belong to your account.');
                }
            },
            function (Validator $validator) {
                $sizeId = $this->input('size_id');
                $brandId = $this->input('brand_id');
                if (! $sizeId || ! $brandId) {
                    return;
                }

                $size = CylinderSize::find($sizeId);
                if ($size && ! $size->brands()->where('gas_brands.id', $brandId)->exists()) {
                    $validator->errors()->add('brand_id', 'The selected brand is not available for this cylinder size.');
                }
            },
            function (Validator $validator) {
                $addonIds = $this->input('addon_ids', []);
                $sizeId = $this->input('size_id');
                if (empty($addonIds) || ! $sizeId) {
                    return;
                }

                $items = AddonItem::whereIn('id', $addonIds)
                    ->where('is_active', true)
                    ->with('group')
                    ->get();

                if ($items->count() !== count(array_unique($addonIds))) {
                    $validator->errors()->add('addon_ids', 'One or more selected add-ons are invalid for the selected cylinder size.');
                    return;
                }

                foreach ($items as $item) {
                    if (! $item->group || ! $item->group->is_active || (int) $item->group->size_id !== (int) $sizeId) {
                        $validator->errors()->add('addon_ids', 'One or more add-ons are not available for the selected cylinder size.');
                        return;
                    }
                }

                foreach ($items->groupBy('group_id') as $groupItems) {
                    $group = $groupItems->first()->group;
                    if ($group && $group->selection_type === 'single' && $groupItems->count() > 1) {
                        $validator->errors()->add('addon_ids', "Only one item can be selected from \"{$group->name}\".");
                    }
                }
            },
            function (Validator $validator) {
                $points = (int) ($this->input('redemption_points') ?? 0);
                if ($points === 0) {
                    return;
                }

                $tiers = app(GasPointsService::class)->redemptionTiersMap();
                if (! array_key_exists($points, $tiers)) {
                    $validator->errors()->add('redemption_points', 'Invalid redemption amount.');
                }
            },
        ];
    }
}
