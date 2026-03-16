<?php

namespace App\Filament\Pages;

use App\Models\MDivision;
use App\Models\Presence;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LeaderboardFull extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-trophy';
    protected string $view = 'filament.pages.leaderboard-full';
    protected static ?string $navigationLabel = 'Leaderboard';
    protected static ?string $title = 'Leaderboard Absensi';
    protected static ?int $navigationSort = 7;
    protected static bool $shouldRegisterNavigation = false;
    
    public $activeTab = 'late'; // 'late' or 'absent'
    
    /**
     * Get cut-off period dates (16th prev month to 15th current month)
     */
    protected function getCutOffPeriod(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        
        $endDate = Carbon::create($currentYear, $currentMonth, 15);
        $startDate = $endDate->copy()->subMonth()->addDay();
        
        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }
    
    public function getAllLateRankings()
    {
        $period = $this->getCutOffPeriod();
        
        $divisions = DB::connection('pgsql_master')
            ->table('m_division')
            ->select('div_code', 'div_name')
            ->get();
        
        foreach ($divisions as $division) {
            $employeeIds = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->join('m_presensi', 'm_karyawan.id', '=', 'm_presensi.karyawan_id')
                ->where('m_karyawan.div_id', $division->div_code)
                ->where('m_presensi.is_active', true)
                ->pluck('m_presensi.id')
                ->toArray();
            
            $lateCount = empty($employeeIds) ? 0 : DB::connection('pgsql')
                ->table('presences')
                ->whereIn('user_id', $employeeIds)
                ->whereBetween('created_at', [
                    $period['start']->startOfDay(),
                    $period['end']->endOfDay()
                ])
                ->where('late_minutes', '>', 0)
                ->count();
            
            $division->late_count = $lateCount;
            $division->employee_count = count($employeeIds);
        }
        
        return $divisions->sortBy('late_count')->values();
    }
    
    public function getAllAbsentRankings()
    {
        $period = $this->getCutOffPeriod();
        $workingDays = $this->getWorkingDaysCount($period['start'], $period['end']);
        
        $divisions = DB::connection('pgsql_master')
            ->table('m_division')
            ->select('div_code', 'div_name')
            ->get();
        
        foreach ($divisions as $division) {
            $employeeIds = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->join('m_presensi', 'm_karyawan.id', '=', 'm_presensi.karyawan_id')
                ->where('m_karyawan.div_id', $division->div_code)
                ->where('m_presensi.is_active', true)
                ->pluck('m_presensi.id')
                ->toArray();
            
            if (empty($employeeIds)) {
                $division->alpha_count = 0;
                $division->employee_count = 0;
                continue;
            }
            
            $presenceCount = DB::connection('pgsql')
                ->table('presences')
                ->whereIn('user_id', $employeeIds)
                ->whereBetween('created_at', [
                    $period['start']->startOfDay(),
                    $period['end']->endOfDay()
                ])
                ->count();
            
            $expectedPresences = count($employeeIds) * $workingDays;
            $division->alpha_count = max(0, $expectedPresences - $presenceCount);
            $division->employee_count = count($employeeIds);
        }
        
        return $divisions->sortByDesc('alpha_count')->values();
    }
    
    protected function getWorkingDaysCount(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            if (!in_array($current->dayOfWeek, [0, 6])) {
                $count++;
            }
            $current->addDay();
        }
        
        return $count;
    }
    
    public function getPeriodLabel(): string
    {
        $period = $this->getCutOffPeriod();
        return $period['start']->format('d M Y') . ' - ' . $period['end']->format('d M Y');
    }
}
