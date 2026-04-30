<?php

namespace App\Http\Requests\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^(\+254|0)[0-9]{9}$/'],
            'token' => ['required', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.size'  => 'Enter the 4-digit code.',
            'token.regex' => 'Enter the 4-digit code.',
        ];
    }
}
