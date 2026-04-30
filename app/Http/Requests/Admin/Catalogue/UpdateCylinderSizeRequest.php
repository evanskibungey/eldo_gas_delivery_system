<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCylinderSizeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $sizeId = $this->route('size')?->id;

        return [
            'name'          => ['required', 'string', 'max:20', Rule::unique('cylinder_sizes', 'name')->ignore($sizeId)],
            'weight_kg'     => ['required', 'numeric', 'min:0.1', 'max:100'],
            'sort_order'    => ['integer', 'min:0'],
            'is_commercial' => ['boolean'],
            'is_active'     => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'   => 'Another cylinder size already uses this name.',
            'weight_kg.min' => 'Weight must be greater than 0.',
        ];
    }
}
