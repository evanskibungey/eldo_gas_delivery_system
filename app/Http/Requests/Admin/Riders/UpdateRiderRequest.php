<?php

namespace App\Http\Requests\Admin\Riders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $riderId = $this->route('rider')?->id;

        return [
            'name'                => ['required', 'string', 'max:100'],
            'phone'               => ['required', 'string', 'max:20', Rule::unique('riders', 'phone')->ignore($riderId)],
            'national_id'         => ['nullable', 'string', 'max:20'],
            'is_safety_certified' => ['boolean'],
            'certification_date'  => ['nullable', 'date', 'required_if:is_safety_certified,true', 'before_or_equal:today'],
            'is_active'           => ['boolean'],
            'is_available'        => ['boolean'],
            'photo'               => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'is_safety_certified' => 'safety certification',
            'certification_date'  => 'certification date',
            'is_active'           => 'active status',
            'is_available'        => 'availability status',
        ];
    }
}
