<?php

namespace App\Filament\Widgets;

use App\Models\MKaryawan;
use App\Models\Presence;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Filament\Concerns\ResolvesDashboardDates;

class LeaderboardWidget extends Widget
{
    use ResolvesDashboardDates;

    protected string $view = 'filament.widgets.leaderboard-widget';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 'full';
    
    // Disable caching to ensure filters work in real-time
    protected static bool $isLazy = false;
    
    protected ?string $pollingInterval = '30s'; // Changed to 30s for more real-time feel
    
    /**
     * Get divisions with least late arrivals
     */
    public function getLeastLateDivisions()
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : collect($user ? $user->getSubordinateUserIds() : []);

        // Step 1: Get all divisions
        $divisions = DB::connection('pgsql_master')
            ->table('m_division')
            ->select('div_code', 'div_name')
            ->get();
            
        $validDivisions = collect();
        
        // Step 2: For each division, count late arrivals
        foreach ($divisions as $division) {
            // Get employee IDs in this division
            $query = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->join('m_presensi', 'm_karyawan.id', '=', 'm_presensi.karyawan_id')
                ->where('m_karyawan.div_id', $division->div_code)
                ->where('m_presensi.is_active', true);
                
            if (!$isGlobalAdmin) {
                $query->whereIn('m_presensi.id', $subordinateIds->isEmpty() ? [-1] : $subordinateIds->toArray());
            }
                
            $employeeIds = $query->pluck('m_presensi.id')->toArray();
            
            if (empty($employeeIds)) {
                continue; // Jangan tampilkan divisi ini jika Atasan tsb tidak punya bawahan di sini
            }
            
            // Count late arrivals for these employees
            $lateCount = DB::connection('pgsql')
                ->table('presences')
                ->whereIn('user_id', $employeeIds)
                ->whereBetween('created_at', [
                    $startDate->startOfDay(),
                    $endDate->endOfDay()
                ])
                ->where('late_minutes', '>', 0)
                ->count();
            
            $division->late_count = $lateCount;
            $division->employee_count = count($employeeIds);
            
            $validDivisions->push($division);
        }
        
        // Sort by late_count ascending and take top 5
        return $validDivisions->sortBy('late_count')->take(5)->values();
    }
    
    /**
     * Get divisions with most absences (alpha)
     * Alpha = no clock-in AND no clock-out for the day
     */
    public function getMostAbsentDivisions()
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $workingDays = $this->getWorkingDaysCount($startDate, $endDate);
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : collect($user ? $user->getSubordinateUserIds() : []);

        // Step 1: Get all divisions
        $divisions = DB::connection('pgsql_master')
            ->table('m_division')
            ->select('div_code', 'div_name')
            ->get();
        
        $validDivisions = collect();
        
        // Step 2: For each division, calculate alpha count
        foreach ($divisions as $division) {
            // Get employee IDs in this division
            $query = DB::connection('pgsql_master')
                ->table('m_karyawan')
                ->join('m_presensi', 'm_karyawan.id', '=', 'm_presensi.karyawan_id')
                ->where('m_karyawan.div_id', $division->div_code)
                ->where('m_presensi.is_active', true);
                
            if (!$isGlobalAdmin) {
                $query->whereIn('m_presensi.id', $subordinateIds->isEmpty() ? [-1] : $subordinateIds->toArray());
            }
                
            $employeeIds = $query->pluck('m_presensi.id')->toArray();
            
            if (empty($employeeIds)) {
                $division->alpha_count = 0;
                continue;
            }
            
            // Count actual presences
            $presenceCount = DB::connection('pgsql')
                ->table('presences')
                ->whereIn('user_id', $employeeIds)
                ->whereBetween('created_at', [
                    $startDate->startOfDay(),
                    $endDate->endOfDay()
                ])
                ->count();
            
            // Alpha = (employees * working_days) - actual_presences
            $expectedPresences = count($employeeIds) * $workingDays;
            $division->alpha_count = max(0, $expectedPresences - $presenceCount);
            $division->employee_count = count($employeeIds);
            
            $validDivisions->push($division);
        }
        
        // Filter divisions with alpha > 0, sort descending, take top 5
        return $validDivisions->filter(fn($d) => $d->alpha_count > 0)
            ->sortByDesc('alpha_count')
            ->take(5)
            ->values();
    }
    
    /**
     * Count working days (excluding weekends)
     */
    protected function getWorkingDaysCount(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            // Skip Saturdays (6) and Sundays (0)
            if (!in_array($current->dayOfWeek, [0, 6])) {
                $count++;
            }
            $current->addDay();
        }
        
        return $count;
    }
}
