<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $adminId = Auth::guard('admin')->id();

        return [
            'name'                  => ['required', 'string', 'max:100'],
            'email'                 => ['required', 'email', 'max:150', "unique:admins,email,{$adminId}"],
            'password'              => ['nullable', 'confirmed', Password::min(8)],
            'password_confirmation' => ['nullable'],
        ];
    }
}
