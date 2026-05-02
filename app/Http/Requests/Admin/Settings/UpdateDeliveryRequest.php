<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'delivery_fee_mode'   => ['required', Rule::in(['per_size', 'flat_rate', 'per_km'])],
            'delivery_base_fee'   => ['required', 'numeric', 'min:0'],
            'delivery_per_km_fee' => ['required', 'numeric', 'min:0'],
            'shop_lat'            => ['nullable', 'numeric', 'between:-90,90'],
            'shop_lng'            => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
