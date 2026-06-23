<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'gaspoints_enabled'                   => ['boolean'],
            'gaspoints_earn_new_cylinder'         => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_swap'                 => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_large_cylinder'       => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_welcome'              => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_review'               => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_referral'             => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_earn_referral_third_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'gaspoints_redemption_tiers'           => ['required', 'array', 'min:1'],
            'gaspoints_redemption_tiers.*.points'  => ['required', 'integer', 'min:1', 'distinct'],
            'gaspoints_redemption_tiers.*.kes'     => ['required', 'integer', 'min:1'],
        ];
    }
}
