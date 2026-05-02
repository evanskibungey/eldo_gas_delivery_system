<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopHoursRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'shop_open_time'  => ['required', 'date_format:H:i'],
            'shop_close_time' => ['required', 'date_format:H:i', 'after:shop_open_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_close_time.after' => 'Closing time must be after opening time.',
        ];
    }
}
