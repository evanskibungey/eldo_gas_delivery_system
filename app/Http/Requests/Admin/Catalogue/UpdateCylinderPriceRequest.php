<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCylinderPriceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'gas_refill_price'   => ['required', 'integer', 'min:0'],
            'new_cylinder_price' => ['required', 'integer', 'min:0'],
            'new_gas_fill_price' => ['required', 'integer', 'min:0'],
            'delivery_fee'       => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'All price fields are required.',
            '*.min'      => 'Prices cannot be negative.',
        ];
    }
}
