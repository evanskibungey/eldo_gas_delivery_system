<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

class StoreGasBrandRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100', 'unique:gas_brands,name'],
            'logo'      => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'size_ids'  => ['array'],
            'size_ids.*'=> ['exists:cylinder_sizes,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'  => 'A brand with this name already exists.',
            'logo.max'     => 'Logo must be under 1MB.',
            'logo.mimes'   => 'Logo must be a JPG, PNG, or WebP image.',
        ];
    }
}
