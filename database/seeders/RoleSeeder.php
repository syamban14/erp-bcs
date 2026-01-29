<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);

        // Assign super_admin role to the first user if exists
        $user = User::first();
        if ($user) {
            $user->assignRole($superAdminRole);
            $this->command->info('User ' . $user->email . ' assigned to super_admin role.');
        }
    }
}
