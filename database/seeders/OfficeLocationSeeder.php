<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OfficeLocation;

class OfficeLocationSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            [
                'name' => 'BCS Logistics Cilegon',
                'address' => 'Cilegon, Banten',
                'latitude' => -6.0186817,  // Koordinat perkiraan Cilegon
                'longitude' => 106.0558217,
                'radius' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'BCS Logistics Gunung Putri Bogor',
                'address' => 'Gunung Putri, Bogor, Jawa Barat',
                'latitude' => -6.4058172,  // Koordinat perkiraan Gunung Putri
                'longitude' => 106.9650326,
                'radius' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($offices as $office) {
            OfficeLocation::create($office);
        }
    }
}
