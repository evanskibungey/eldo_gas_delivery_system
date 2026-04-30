<?php

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminUserService
{
    /**
     * Create a new admin user and assign their role.
     */
    public function create(array $data): Admin
    {
        $admin = Admin::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $admin->assignRole($data['role']);

        return $admin;
    }

    /**
     * Update an existing admin user's profile and role.
     *
     * Guards against an admin deactivating their own account.
     */
    public function update(Admin $admin, array $data, bool $isSelf): Admin
    {
        $payload = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            // Self-edit: keep active regardless of what was submitted
            'is_active' => $isSelf ? true : ($data['is_active'] ?? $admin->is_active),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $admin->update($payload);
        $admin->syncRoles([$data['role']]);

        return $admin;
    }

    /**
     * Delete an admin user.
     *
     * Prevents an admin from deleting their own account.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(Admin $admin): void
    {
        if ($admin->id === Auth::guard('admin')->id()) {
            throw ValidationException::withMessages([
                'admin' => 'You cannot delete your own account.',
            ]);
        }

        $admin->delete();
    }

    public function isSelf(Admin $admin): bool
    {
        return $admin->id === Auth::guard('admin')->id();
    }

    /**
     * Return all active admin roles for the admin guard.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getRoles(): \Illuminate\Support\Collection
    {
        return Role::where('guard_name', 'admin')->pluck('name');
    }

    /**
     * Return a formatted list of all admins suitable for the index view.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function listForIndex(): \Illuminate\Support\Collection
    {
        $currentId = Auth::guard('admin')->id();

        return Admin::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (Admin $a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'email'         => $a->email,
                'is_active'     => $a->is_active,
                'last_login_at' => $a->last_login_at?->diffForHumans(),
                'roles'         => $a->roles->pluck('name'),
                'is_self'       => $a->id === $currentId,
            ]);
    }

    /**
     * Return a formatted admin record for the edit view.
     *
     * @return array<string, mixed>
     */
    public function formatForEdit(Admin $admin): array
    {
        return [
            'id'            => $admin->id,
            'name'          => $admin->name,
            'email'         => $admin->email,
            'is_active'     => $admin->is_active,
            'last_login_at' => $admin->last_login_at?->format('d M Y, H:i'),
            'roles'         => $admin->roles->pluck('name'),
        ];
    }
}
