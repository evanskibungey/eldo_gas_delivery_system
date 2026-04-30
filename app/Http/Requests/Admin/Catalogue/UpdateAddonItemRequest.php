<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddonItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'group_id'    => ['required', 'exists:addon_groups,id'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'price'       => ['required', 'integer', 'min:0'],
            'photo'       => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
            'sort_order'  => ['integer', 'min:0'],
            'is_active'   => ['boolean'],
        ];
    }
}
