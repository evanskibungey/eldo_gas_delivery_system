<?php

namespace App\Http\Requests\Admin\Riders;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100'],
            'phone'               => ['required', 'string', 'max:20', 'unique:riders,phone'],
            'national_id'         => ['nullable', 'string', 'max:20'],
            'is_safety_certified' => ['boolean'],
            'certification_date'  => ['nullable', 'date', 'required_if:is_safety_certified,true', 'before_or_equal:today'],
            'is_active'           => ['boolean'],
            'photo'               => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'is_safety_certified' => 'safety certification',
            'certification_date'  => 'certification date',
            'is_active'           => 'active status',
        ];
    }
}
