<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminId = $this->route('user')?->id;

        $rules = [
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'max:150', Rule::unique('admins', 'email')->ignore($adminId)],
            'role'      => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            'is_active' => ['boolean'],
        ];

        if ($this->filled('password')) {
            $rules['password'] = ['string', 'min:8', 'confirmed'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Full name is required.',
            'email.required'     => 'Email address is required.',
            'email.unique'       => 'Another admin already uses this email.',
            'password.min'       => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
            'role.required'      => 'Please assign a role.',
            'role.exists'        => 'The selected role is invalid.',
        ];
    }
}
