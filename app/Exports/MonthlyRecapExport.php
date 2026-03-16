<?php

namespace App\Exports;

use App\Models\MPresensi;
use App\Services\RecapService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MonthlyRecapExport implements FromCollection, WithHeadings, WithMapping
{
    protected $month;
    protected $year;
    protected $unitId;
    protected $recapService;
    
    public function __construct($month, $year, $unitId = null)
    {
        $this->month = $month;
        $this->year = $year;
        $this->unitId = $unitId;
        $this->recapService = app(RecapService::class);
    }

    public function collection()
    {
        $query = MPresensi::query()->orderBy('name');
        
        if ($this->unitId) {
            $query->where('office_location_id', $this->unitId);
        }
        
        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Nama Karyawan',
            'Unit Kerja', // Added Unit
            'Hari Kerja',
            'Hadir',
            'Durasi (Jam)',
            'Cuti Tahunan',
            'Cuti Spesial',
            'Sakit',
            'Izin (Kali)',
            'Tugas Luar',
            'Alpa',
            'Lembur (Jam)',
            'Telat (Jam)',
            'Pulang Awal (Jam)',
        ];
    }

    public function map($employee): array
    {
        // Calculate period
        $currentMonth = $this->month;
        $currentYear = $this->year;
        $endDate = Carbon::create($currentYear, $currentMonth, 15);
        $startDate = $endDate->copy()->subMonth()->addDay();
        
        // Get Data
        $data = $this->recapService->getRecapData($employee, $startDate, $endDate);
        
        return [
            $employee->name,
            $employee->officeLocation->name ?? '-',
            $data['total_hari_kerja'],
            $data['total_kehadiran'],
            $data['durasi_kehadiran'],
            $data['cuti_tahunan'],
            $data['cuti_special'],
            $data['cuti_sakit'],
            $data['izin_jam'], // Actually count
            $data['tugas_luar'],
            $data['alpa'],
            $data['lembur_jam'],
            $data['terlambat_jam'],
            $data['pulang_awal_jam'],
        ];
    }
}
