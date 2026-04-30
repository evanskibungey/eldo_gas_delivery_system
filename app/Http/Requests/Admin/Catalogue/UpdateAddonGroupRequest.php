<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddonGroupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'size_id'        => ['required', 'exists:cylinder_sizes,id'],
            'name'           => ['required', 'string', 'max:100'],
            'selection_type' => ['required', 'in:multi,single'],
            'sort_order'     => ['integer', 'min:0'],
            'is_active'      => ['boolean'],
        ];
    }
}
