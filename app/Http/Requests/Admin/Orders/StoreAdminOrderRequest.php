<?php

namespace App\Http\Requests\Admin\Orders;

use App\Models\CylinderSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            // 'app' is deliberately not accepted: that channel means the
            // customer placed it themselves, which this endpoint never does.
            'channel' => ['required', 'in:phone,walk_in'],

            // Either an existing customer, or enough to create one. Enforced
            // together in withValidator() below.
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],

            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.size_id' => ['required', 'integer', 'exists:cylinder_sizes,id'],
            'items.*.brand_id' => ['required', 'integer', 'exists:gas_brands,id'],
            'items.*.order_type' => ['required', 'in:swap,new_cylinder'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],

            'addon_ids' => ['array'],
            'addon_ids.*' => ['integer', 'exists:addon_items,id'],

            'payment_method' => ['required', 'in:cash,mpesa'],
            'delivery_notes' => ['nullable', 'string', 'max:255'],

            // Only a delivery needs somewhere to go.
            'address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasExisting = ! empty($this->input('customer_id'));
            $hasPhone = trim((string) $this->input('customer_phone')) !== '';

            if (! $hasExisting && ! $hasPhone) {
                $validator->errors()->add(
                    'customer_phone',
                    'Choose a customer, or enter a name and phone number to create one.',
                );
            }

            // A phone order is delivered, so it needs an address. A counter
            // sale is collected and never has one.
            if ($this->input('channel') === 'phone' && empty($this->input('address_id'))) {
                $validator->errors()->add(
                    'address_id',
                    'A phone order needs a delivery address.',
                );
            }

            $this->assertBrandsAreSoldInTheirSizes($validator);
        });
    }

    /**
     * Both ids exist, but not every brand is sold in every size.
     *
     * The composer filters the brand list by the chosen size, so this only
     * catches a stale form or a hand-rolled post — but the price row read
     * downstream is keyed on the size alone, so a mismatch would be accepted
     * and priced rather than refused.
     */
    private function assertBrandsAreSoldInTheirSizes(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $sizes = CylinderSize::with('brands:id')
            ->whereIn('id', array_filter(array_column($items, 'size_id')))
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $size = $sizes[$item['size_id'] ?? null] ?? null;

            if ($size && ! $size->brands->contains('id', (int) ($item['brand_id'] ?? 0))) {
                $validator->errors()->add(
                    "items.{$index}.brand_id",
                    "That brand is not sold in the {$size->name} size.",
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one cylinder to the order.',
            'items.*.brand_id.required' => 'Choose a brand for every cylinder.',
        ];
    }
}
