<?php

namespace App\Http\Requests\Admin\Sms;

use Illuminate\Foundation\Http\FormRequest;

class SendCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            // Capped rather than unbounded: every 153 characters past the first
            // 160 is another segment billed to every single recipient.
            'message' => ['required', 'string', 'max:918'],
            'kind' => ['required', 'in:promo,info'],
            'audience' => ['required', 'in:selected,all_active,ordered_30d,no_order_60d,never_ordered'],
            'customer_ids' => ['array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            // The admin has to have seen the cost preview and agreed to it.
            'confirmed_segments' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.max' => 'That is longer than 6 SMS segments. Shorten it.',
            'confirmed_segments.required' => 'Confirm the send cost before sending.',
        ];
    }
}
