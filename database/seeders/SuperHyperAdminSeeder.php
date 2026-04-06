<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\MPresensi;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperHyperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat role superhyperadmin jika belum ada
        $role = Role::firstOrCreate(['name' => 'superhyperadmin']);
        
        // 2. Buat akun superhyperadmin default jika belum ada
        $user = MPresensi::firstOrCreate(
            ['email' => 'superhyperadmin@bcs-logistics.co.id'],
            [
                'name' => 'Super Hyper Admin',
                'password' => Hash::make('Bcsbcs123!*'), // Default kuat
                'is_active' => true,
                'role' => 'superhyperadmin',
                'employment_type' => 'regular',
                'office_location_id' => 1,
            ]
        );

        // 3. Assign role ke user tersebut
        // Di aplikasi ini mungkin pakai $user->assignRole($role) jika menggunakan Spatie 
        // pada model MPresensi, tapi dari struktur saat ini column "role" sering jadi acuan. 
        // Supaya aman kita berikan assignRole jika Traits HasRoles ada,
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);
        }
        
        $this->command->info('SuperHyperAdmin user created successfully.');
        $this->command->info('Email: superhyperadmin@bcs-logistics.co.id');
        $this->command->info('Password: Bcsbcs123!*');
    }
}
