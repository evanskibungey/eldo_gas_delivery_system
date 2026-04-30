<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'remember_me' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Your email address is required.',
            'email.email'       => 'Enter a valid email address.',
            'password.required' => 'Your password is required.',
        ];
    }
}
