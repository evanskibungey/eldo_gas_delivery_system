<?php

namespace App\Http\Requests\Admin\Tracking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiderLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat'      => ['required', 'numeric', 'between:-90,90'],
            'lng'      => ['required', 'numeric', 'between:-180,180'],
            'heading'  => ['nullable', 'integer', 'between:0,359'],
            'location' => ['nullable', 'string', 'max:100'],
        ];
    }
}
