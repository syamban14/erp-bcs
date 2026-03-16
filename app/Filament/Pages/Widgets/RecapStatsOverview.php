<?php

namespace App\Filament\Pages\Widgets;

use App\Models\MPresensi;
use App\Services\RecapService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class RecapStatsOverview extends BaseWidget
{
    public ?int $month = null;
    public ?int $year = null;
    public ?int $unitId = null;

    protected function getStats(): array
    {
        // Defaults
        $month = $this->month ?? now()->month;
        $year = $this->year ?? now()->year;
        
        // Calculate period
        $endDate = Carbon::create($year, $month, 15);
        $startDate = $endDate->copy()->subMonth()->addDay();
        
        $service = app(RecapService::class);
        
        // Query Users
        $query = MPresensi::query();
        if ($this->unitId) {
            $query->where('office_location_id', $this->unitId);
        }
        $employees = $query->get();
        
        $totalEmployees = $employees->count();
        if ($totalEmployees === 0) {
             return [
                Stat::make('Total Karyawan', 0),
            ];
        }

        $totalLateHours = 0;
        $totalOvertimeHours = 0;
        $totalWorkingDays = 0;
        $totalPresence = 0;
        
        foreach ($employees as $employee) {
            $data = $service->getRecapData($employee, $startDate, $endDate);
            
            $totalLateHours += (float) $data['terlambat_jam'];
            $totalOvertimeHours += (float) $data['lembur_jam'];
            $totalWorkingDays += (int) $data['total_hari_kerja'];
            $totalPresence += (int) $data['total_kehadiran'];
        }
        
        // Avg Attendance
        $avgAttendance = $totalWorkingDays > 0 ? ($totalPresence / $totalWorkingDays) * 100 : 0;
        
        return [
            Stat::make('Kehadiran (Avg)', number_format($avgAttendance, 1) . '%')
                ->description('Rata-rata kehadiran seluruh karyawan')
                ->color($avgAttendance > 90 ? 'success' : 'danger')
                ->chart([70, 80, 85, 90, 95, 90, $avgAttendance]),
                
            Stat::make('Total Terlambat', number_format($totalLateHours, 1) . ' Jam')
                ->description('Akumulasi jam keterlambatan')
                ->color('danger')
                ->chart([10, 20, 15, 30, 20, 10, $totalLateHours]),

            Stat::make('Total Lembur', number_format($totalOvertimeHours, 1) . ' Jam')
                ->description('Akumulasi jam lembur')
                ->color('info'),
        ];
    }
}
