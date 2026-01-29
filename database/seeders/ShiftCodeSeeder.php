<?php

namespace Database\Seeders;

use App\Models\ShiftCode;
use Illuminate\Database\Seeder;

class ShiftCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shiftCodes = [
            // MALAM shifts
            ['code' => 'M4', 'name' => 'MALAM 4', 'time_in' => '22:00', 'time_out' => '06:00', 'is_off' => false],
            ['code' => 'M1', 'name' => 'MALAM 1', 'time_in' => '22:00', 'time_out' => '06:00', 'is_off' => false],
            ['code' => 'M2', 'name' => 'MALAM 2', 'time_in' => '23:00', 'time_out' => '07:00', 'is_off' => false],
            ['code' => 'M3', 'name' => 'MALAM 3', 'time_in' => '20:00', 'time_out' => '08:00', 'is_off' => false],
            
            // PAGI shifts
            ['code' => 'P1', 'name' => 'PAGI 1', 'time_in' => '06:00', 'time_out' => '14:00', 'is_off' => false],
            ['code' => 'P2', 'name' => 'PAGI 2', 'time_in' => '07:00', 'time_out' => '15:00', 'is_off' => false],
            ['code' => 'P3', 'name' => 'PAGI 3', 'time_in' => '08:00', 'time_out' => '17:00', 'is_off' => false],
            ['code' => 'P4', 'name' => 'PAGI 4', 'time_in' => '07:00', 'time_out' => '16:00', 'is_off' => false],
            ['code' => 'P5', 'name' => 'PAGI 5', 'time_in' => '08:00', 'time_out' => '16:00', 'is_off' => false],
            ['code' => 'P6', 'name' => 'PAGI 6', 'time_in' => '07:30', 'time_out' => '16:30', 'is_off' => false],
            ['code' => 'P7', 'name' => 'PAGI 7', 'time_in' => '09:00', 'time_out' => '17:00', 'is_off' => false],
            ['code' => 'P9', 'name' => 'PAGI 9', 'time_in' => '07:30', 'time_out' => '16:00', 'is_off' => false],
            ['code' => 'P10', 'name' => 'PAGI 10', 'time_in' => '07:00', 'time_out' => '19:00', 'is_off' => false],
            
            // SIANG shifts
            ['code' => 'S1', 'name' => 'SIANG 1', 'time_in' => '14:00', 'time_out' => '22:00', 'is_off' => false],
            ['code' => 'S2', 'name' => 'SIANG 2', 'time_in' => '15:00', 'time_out' => '23:00', 'is_off' => false],
            ['code' => 'S3', 'name' => 'SIANG 3', 'time_in' => '16:00', 'time_out' => '23:59', 'is_off' => false],
            
            // ERA shifts
            ['code' => 'ERA1', 'name' => 'PAGI 5', 'time_in' => '08:00', 'time_out' => '20:00', 'is_off' => false],
            ['code' => 'ERA2', 'name' => 'MALAM 3', 'time_in' => '20:00', 'time_out' => '08:00', 'is_off' => false],
            
            // Libur
            ['code' => 'X', 'name' => 'Libur', 'description' => 'Hari libur', 'is_off' => true],
            ['code' => 'Off', 'name' => 'Off', 'description' => 'Off duty', 'is_off' => true],
            ['code' => 'PH', 'name' => 'Libur', 'description' => 'Public Holiday', 'is_off' => true],
            
            // Cuti & Izin
            ['code' => 'CTK10', 'name' => 'Khitanan anak laki-laki atau pembaptisan', 'is_off' => true],
            ['code' => 'CTK11', 'name' => 'Bencana Alam atau Kebakaran', 'is_off' => true],
            ['code' => 'CTK12', 'name' => 'Mengurus surat atau mendapat panggilan dari instansi pemerintah', 'is_off' => true],
            ['code' => 'CTK13', 'name' => 'Cuti Pekerja Melahirkan', 'is_off' => true],
            ['code' => 'CTK14', 'name' => 'Cuti Umroh', 'is_off' => true],
            ['code' => 'CTK15', 'name' => 'Cuti Haji', 'is_off' => true],
            ['code' => 'CTK7', 'name' => 'Anggota Keluarga yang tinggal serumah meninggal dunia', 'is_off' => true],
            ['code' => 'LL', 'name' => 'Long leave', 'is_off' => true],
            ['code' => 'RO', 'name' => 'Replacement off', 'is_off' => true],
            ['code' => 'UL', 'name' => 'Unpaid leave', 'is_off' => true],
            ['code' => 'BT', 'name' => 'Perjalanan dinas', 'is_off' => true],
            ['code' => 'TR', 'name' => 'Pelatihan', 'is_off' => true],
            ['code' => 'DP', 'name' => 'Day off payment', 'is_off' => true],
            ['code' => 'RPH', 'name' => 'Replacement public holiday', 'is_off' => true],
            ['code' => 'CTK16', 'name' => 'Dirumahkan', 'is_off' => true],
            ['code' => 'CTK17', 'name' => 'Cuti Khusus', 'is_off' => true],
            ['code' => 'TML', 'name' => 'Tugas masuk hari libur', 'is_off' => false],
            ['code' => 'CTK1', 'name' => 'Pernikahan Pekerja Sendiri', 'is_off' => true],
            ['code' => 'CTK2', 'name' => 'Pernikahan Anak Sah Pekerja', 'is_off' => true],
            ['code' => 'CTK3', 'name' => 'Istri atau Suami atau Gugur Kandungan', 'is_off' => true],
            ['code' => 'CTK4', 'name' => 'Istri atau Suami atau Anak Sah Pekerja Meninggal Dunia', 'is_off' => true],
            ['code' => 'CTK5', 'name' => 'Orangtua atau Mertua atau Menantu Meninggal Dunia', 'is_off' => true],
            ['code' => 'CTK6', 'name' => 'Istri Kandung Pekerja Melahirkan', 'is_off' => true],
            ['code' => 'CTK8', 'name' => 'Istri atau Suami atau Anak Sah Pekerja Sakit Keras', 'is_off' => true],
            ['code' => 'CTK9', 'name' => 'Haid atau Datang Bulan Jika Disertai Rasa Sakit', 'is_off' => true],
        ];

        foreach ($shiftCodes as $code) {
            ShiftCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }

        $this->command->info('Shift codes seeded successfully!');
    }
}
