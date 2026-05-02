<?php

namespace App\Http\Requests\Admin\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'            => ['required', 'string', 'in:picked_up,on_the_way,delivered'],
            'delivery_note'     => ['nullable', 'string', 'max:500'],
            'payment_collected' => ['nullable', 'boolean'],
        ];
    }
}
