<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        foreach (['super_admin', 'shop_manager', 'dispatcher'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'admin']);
        }

        // Create default super admin
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@eldogas.co.ke'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('Admin@1234'),
                'is_active' => true,
            ]
        );

        $admin->assignRole('super_admin');
    }
}
