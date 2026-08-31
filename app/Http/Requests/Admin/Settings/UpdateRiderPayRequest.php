<?php

namespace App\Http\Requests\Admin\Settings;

use App\Support\RiderEarnings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Replaces UpdateCommissionRequest. Riders are paid a flat fee per delivery,
 * not a share of the order value, so the input is an amount in KSh rather
 * than a percentage.
 */
class UpdateRiderPayRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Integer KSh, matching how money is stored everywhere else.
            RiderEarnings::SETTING_KEY => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
