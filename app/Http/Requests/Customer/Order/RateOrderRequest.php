<?php

namespace App\Http\Requests\Customer\Order;

use Illuminate\Foundation\Http\FormRequest;

class RateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stars'       => ['required', 'integer', 'min:1', 'max:5'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:50'],
            'review'      => ['nullable', 'string', 'max:1000'],
            'flagged'     => ['boolean'],
            'flag_reason' => ['nullable', 'required_if:flagged,true', 'string', 'max:255'],
        ];
    }
}
