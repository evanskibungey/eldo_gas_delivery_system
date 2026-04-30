<?php

namespace App\Http\Requests\Admin\Stock;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filled_count' => ['required', 'integer', 'min:0'],
            'empty_count'  => ['required', 'integer', 'min:0'],
            'threshold'    => ['nullable', 'integer', 'min:1', 'max:1000'],
            'note'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'filled_count' => 'filled cylinder count',
            'empty_count'  => 'empty cylinder count',
            'threshold'    => 'low-stock threshold',
        ];
    }
}
