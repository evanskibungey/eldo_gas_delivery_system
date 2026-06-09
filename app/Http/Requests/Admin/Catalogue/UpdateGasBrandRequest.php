<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGasBrandRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;

        return [
            'name'                  => ['required', 'string', 'max:100', Rule::unique('gas_brands', 'name')->ignore($brandId)],
            'logo'                  => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'size_ids'              => ['array'],
            'size_ids.*'            => ['exists:cylinder_sizes,id'],
            'size_images'           => ['array'],
            'size_images.*'         => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'remove_size_images'    => ['array'],
            'remove_size_images.*'  => ['integer'],
            'is_active'             => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another brand already uses this name.',
            'logo.max'    => 'Logo must be under 1MB.',
            'logo.mimes'  => 'Logo must be a JPG, PNG, or WebP image.',
        ];
    }
}
