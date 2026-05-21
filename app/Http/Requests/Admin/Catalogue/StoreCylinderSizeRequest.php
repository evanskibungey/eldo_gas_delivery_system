<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

class StoreCylinderSizeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:20', 'unique:cylinder_sizes,name'],
            'weight_kg'     => ['required', 'numeric', 'min:0.1', 'max:100'],
            'sort_order'    => ['integer', 'min:0'],
            'is_commercial' => ['boolean'],
            'is_active'     => ['boolean'],
            'image'         => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'   => 'A cylinder size with this name already exists.',
            'weight_kg.min' => 'Weight must be greater than 0.',
        ];
    }
}
