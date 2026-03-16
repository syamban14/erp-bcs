<?php

namespace App\Filament\Widgets;

use App\Models\MPresensi;
use App\Models\Presence;
use App\Models\Leave;
use App\Models\PermissionRequest;
use App\Models\AttendanceCorrection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use App\Filament\Concerns\ResolvesDashboardDates;

class StatsOverviewWidget extends BaseWidget
{
    use ResolvesDashboardDates;

    protected static ?int $sort = 1;
    protected ?string $pollingInterval = '30s'; // Auto-refresh setiap 30 detik
    
    // Disable caching to ensure filters work in real-time
    protected static bool $isLazy = false;
    
    protected function getStats(): array
    {
        // Resolve dates from dashboard filters
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        // Total Karyawan
        $totalEmployees = MPresensi::count();
        $activeEmployees = MPresensi::where('is_active', true)->count();
        
        // Generate trend charts data (last 7 days from end date)
        $chartStart = $endDate->copy()->subDays(6);
        $chartRange = [];
        for ($i = 0; $i <= 6; $i++) {
            $chartRange[] = $chartStart->copy()->addDays($i)->format('Y-m-d');
        }
        
        // 1. Total Employees Chart (Daily snapshot - approximation)
        $empChart = array_fill(0, 7, $totalEmployees); // Flat line for now unless we track history
        
        // 2. Presence Chart - use `date` column (type: date), NOT clock_in (type: time)
        $presenceStats = Presence::selectRaw('date::text as pdate, COUNT(DISTINCT user_id) as count')
            ->whereBetween('date', [$chartStart->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('pdate')
            ->pluck('count', 'pdate')
            ->toArray();
            
        $presenceChart = array_map(fn($date) => (int)($presenceStats[$date] ?? 0), $chartRange);
        
        // 3. Sick Leave Chart
        $sickStats = Leave::selectRaw('start_date::text as sdate, COUNT(*) as count')
            ->where('type', 'sick')
            ->where('status', 'approved')
            ->whereBetween('start_date', [$chartStart->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('sdate')
            ->pluck('count', 'sdate')
            ->toArray();
            
        $sickChart = array_map(fn($date) => (int)($sickStats[$date] ?? 0), $chartRange);
        
        // 4. Late Chart - use `date` column
        $lateStats = Presence::selectRaw('date::text as pdate, COUNT(DISTINCT user_id) as count')
            ->where('late_minutes', '>', 0)
            ->whereBetween('date', [$chartStart->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('pdate')
            ->pluck('count', 'pdate')
            ->toArray();
            
        $lateChart = array_map(fn($date) => (int)($lateStats[$date] ?? 0), $chartRange);
        
        // Kehadiran Periode Ini - use `date` column (type: date)
        $presentToday = Presence::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->distinct('user_id')
            ->count('user_id');
            
        $attendanceRate = $activeEmployees > 0 
            ? round(($presentToday / $activeEmployees) * 100, 1) 
            : 0;
        
        // Pending Approvals
        $pendingLeaves = Leave::where('status', 'pending')->count();
        $pendingPermissions = PermissionRequest::where('status', 'pending')->count();
        $pendingCorrections = AttendanceCorrection::where('status', 'pending')->count();
        $totalPending = $pendingLeaves + $pendingPermissions + $pendingCorrections;
        
        // Cuti Sakit Hari Ini / Periode Ini
        $sickLeaveToday = Leave::where('type', 'sick')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->count();
        
        // Tugas Luar Aktif (Outstation) - Real Data
        // Assuming we have OutstationRequest model and table outstation_requests
        $activeOutstation = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('outstation_requests')) {
                $activeOutstation = \App\Models\OutstationRequest::where('status', 'approved')
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->count();
            }
        } catch (\Exception $e) {
            // Table might not exist or model missing
        }
        
        // Tugas Luar Chart
        $outstationChart = array_fill(0, 7, 0); // Placeholder until schema verified
        
        // Belum Hadir (Karyawan aktif yang belum clock-in periode ini)
        // Only makes sense if filter is Today. If period > 1 day, user present once is counted as present.
        $notPresentYet = max(0, $activeEmployees - $presentToday);
        
        // Belum Hadir Chart (Inverse of presence chart relative to active employees)
        $notPresentChart = array_map(fn($p) => max(0, $activeEmployees - $p), $presenceChart);
        
        // Belum Pulang (Sudah clock-in tapi belum clock-out dalam periode)
        $notClockedOut = Presence::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereNull('clock_out')
            ->distinct('user_id')
            ->count('user_id');
            
        // ✅ Total Shift Siang & Malam
        // Catatan: Shift Pagi TIDAK dihitung sesuai aturan BCS.
        // Filter berdasarkan nama shift_code yang mengandung 'Siang' atau 'Malam'.
        $totalShiftSiangMalam = Presence::whereHas('shiftCode', function ($q) {
                $q->where('name', 'ilike', '%Siang%')
                  ->orWhere('name', 'ilike', '%Malam%');
            })
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->count();

        // ✅ Total Lembur (hanya dari SPL yang disetujui, bukan otomatis)
        $totalApprovedOvertime = \App\Models\OvertimeRequest::where('status', 'approved')
            ->whereBetween('start_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->count();

        // Stats Label Adjustment based on filter
        $filterLabel = match($this->filters['period'] ?? 'cutoff') {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            'cutoff' => 'Periode',
            default => 'Periode',
        };
        
        return [
            Stat::make('Total Karyawan', number_format($totalEmployees))
                ->description($activeEmployees . ' karyawan aktif')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart($empChart),
            
            Stat::make("Kehadiran {$filterLabel}", number_format($presentToday))
                ->description(new \Illuminate\Support\HtmlString(
                    '<span style="display:flex; align-items:center; gap:0.25rem;">' . 
                    $attendanceRate . '% dari total karyawan ' .
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 1.25rem; height: 1.25rem; color: ' . ($attendanceRate >= 90 ? '#22c55e' : ($attendanceRate >= 75 ? '#eab308' : '#ef4444')) . ';">' .
                    '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />' .
                    '</svg></span>'
                ))
                ->color($attendanceRate >= 90 ? 'success' : ($attendanceRate >= 75 ? 'warning' : 'danger'))
                ->chart($presenceChart),
            
            Stat::make('Cuti Sakit', number_format($sickLeaveToday))
                ->description('Karyawan cuti sakit')
                ->descriptionIcon('heroicon-m-heart')
                ->color($sickLeaveToday > 5 ? 'danger' : 'info')
                ->chart($sickChart),
            
            Stat::make('Tugas Luar', number_format($activeOutstation))
                ->description('Karyawan dalam tugas luar')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary')
                ->chart($outstationChart),
            
            Stat::make('Belum Hadir', number_format($notPresentYet))
                ->description('Belum clock-in (' . $filterLabel . ')')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($notPresentYet > 10 ? 'warning' : 'gray')
                ->chart($notPresentChart),
            
            Stat::make("Terlambat ({$filterLabel})", number_format(
                    Presence::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                        ->where('late_minutes', '>', 0)
                        ->count()
                ))
                ->description('Karyawan terlambat')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->chart($lateChart),
            
            Stat::make('Belum Pulang', number_format($notClockedOut))
                ->description('Sudah clock-in, belum clock-out')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('info'),
            
            Stat::make("Total Shift Malam & Siang ({$filterLabel})", number_format($totalShiftSiangMalam))
                ->description('Jumlah kehadiran shift siang & malam (pagi tidak dihitung)')
                ->descriptionIcon('heroicon-m-moon')
                ->color('warning'),
            
            Stat::make("Lembur Disetujui ({$filterLabel})", number_format($totalApprovedOvertime))
                ->description('Pengajuan lembur (SPL) yang sudah disetujui')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($totalApprovedOvertime > 0 ? 'info' : 'gray'),

            Stat::make('Menunggu Persetujuan', number_format($totalPending))
                ->description($pendingLeaves . ' cuti, ' . $pendingPermissions . ' izin, ' . $pendingCorrections . ' koreksi')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($totalPending > 10 ? 'warning' : 'success'),
        ];
    }
}
