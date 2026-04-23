<?php

namespace App\Filament\Widgets;

use App\Models\MPresensi;
use App\Models\Presence;
use App\Models\Leave;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Filament\Concerns\ResolvesDashboardDates;

class LiveStatusWidget extends BaseWidget
{
    use ResolvesDashboardDates;

    protected static ?int $sort = 1;
    protected ?string $pollingInterval = '30s'; // Auto-refresh setiap 30 detik
    
    // Disable caching to ensure filters work in real-time
    protected static bool $isLazy = false;

    // Tambahkan Heading Tabel / Widget
    protected function getHeading(): ?string
    {
        return "Live Status (Hari Ini & " . match($this->filters['period'] ?? 'cutoff') {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            'cutoff' => 'Periode',
            default => 'Periode',
        } . " Aktif)";
    }
    
    protected function getStats(): array
    {
        // Resolve dates from dashboard filters
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : ($user ? $user->getSubordinateUserIds() : []);
        $scopeIds = empty($subordinateIds) ? [-1] : $subordinateIds;

        $applyBaseScope = fn($q) => $isGlobalAdmin ? $q : $q->whereIn('id', $scopeIds);
        $applyScope = fn($q) => $isGlobalAdmin ? $q : $q->whereIn('user_id', $scopeIds);
        
        $activeEmployees = $applyBaseScope(MPresensi::where('is_active', true))->count();
        $today = \Carbon\Carbon::today();

        // 1. BELUM HADIR
        $filterLabel = match($this->filters['period'] ?? 'cutoff') {
            'today' => 'Hari Ini',
            'week' => 'Minggu Ini',
            'month' => 'Bulan Ini',
            'cutoff' => 'Periode',
            default => 'Periode',
        };

        // Jika filter bukan 'Hari Ini', kita hitung rata-rata atau total? 
        // Lebih baik tampilkan spesifik untuk rentang filter (Jika rentang filter panjang, "Belum Hadir" kurang relevan. Konteksnya diubah ke label dinamis namun datanya tetap merepresentasikan karyawan yang belum absen di "hari terakhir filter" atau hari ini).
        // Kita sesuaikan: Belum Hadir & Belum Pulang selalu hari ini.
        $presentTodayCount = $applyScope(Presence::query())
            ->whereDate('date', $today->format('Y-m-d'))
            ->distinct('user_id')
            ->count('user_id');

        $notPresentYet = max(0, $activeEmployees - $presentTodayCount);

        // 2. BELUM PULANG 
        $notClockedOut = $applyScope(Presence::query())
            ->whereDate('date', $today->format('Y-m-d'))
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->distinct('user_id')
            ->count('user_id');

        // 3. CUTI SAKIT
        // Rentangnya dinamis sesuai filter tanggal ($startDate - $endDate)
        $sickLeaveCount = $applyScope(Leave::query())
            ->where('type', 'sick')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->count();
            
        // Generate trend charts data (last 7 days from end date) for Sick Leave
        $chartStart = $endDate->copy()->subDays(6);
        $chartRange = [];
        for ($i = 0; $i <= 6; $i++) {
            $chartRange[] = $chartStart->copy()->addDays($i)->format('Y-m-d');
        }

        $sickStats = $applyScope(Leave::query())
            ->selectRaw('start_date::text as sdate, COUNT(*) as count')
            ->where('type', 'sick')
            ->where('status', 'approved')
            ->whereBetween('start_date', [$chartStart->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->groupBy('sdate')
            ->pluck('count', 'sdate')
            ->toArray();
            
        $sickChart = array_map(fn($date) => (int)($sickStats[$date] ?? 0), $chartRange);

        // 4. TUGAS LUAR
        $activeOutstation = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('outstation_requests')) {
                $activeOutstation = $applyScope(\App\Models\OutstationRequest::query())
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->count();
            }
        } catch (\Exception $e) {}

        return [
            Stat::make('Cuti Sakit (' . $filterLabel . ')', number_format($sickLeaveCount))
                ->description('Karyawan sakit periode ini (Grafik 7 Hari)')
                ->descriptionIcon('heroicon-m-heart')
                ->color($sickLeaveCount > 5 ? 'danger' : 'info')
                ->chart($sickChart),
            
            Stat::make('Tugas Luar (' . $filterLabel . ')', number_format($activeOutstation))
                ->description('Karyawan tugas luar periode ini')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),
            
            // Belum Hadir dan Belum Pulang selalu konteksnya Hari Ini agar tidak menyesatkan
            Stat::make('Belum Hadir (Hari Ini)', number_format($notPresentYet))
                ->description('Karyawan aktif belum clock-in (Hari Ini)')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($notPresentYet > 10 ? 'warning' : 'gray'),
            
            Stat::make('Belum Pulang (Hari Ini)', number_format($notClockedOut))
                ->description('Sudah clock-in, belum clock-out (Hari Ini)')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('info'),
        ];
    }
}
