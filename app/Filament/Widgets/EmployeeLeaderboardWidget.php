<?php

namespace App\Filament\Widgets;

use App\Models\MKaryawan;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Filament\Concerns\ResolvesDashboardDates;

class EmployeeLeaderboardWidget extends Widget
{
    use ResolvesDashboardDates;

    protected string $view = 'filament.widgets.employee-leaderboard-widget';
    protected static ?int $sort = 7; // After Division Leaderboard
    protected int | string | array $columnSpan = 'full';
    protected ?string $pollingInterval = '300s';
    
    // Disable caching to ensure filters work in real-time
    protected static bool $isLazy = false;
    
    /**
     * Get employees with LEAST late minutes (Top Rajin)
     * Criteria: Count of days present where late_minutes = 0.
     * Sorted by count DESC.
     */
    public function getMostDiligentEmployees()
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        // We query 'presences' table for aggregation
        // user_id is the key
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : collect($user ? $user->getSubordinateUserIds() : []);

        $query = DB::connection('pgsql')
            ->table('presences')
            ->select('user_id', DB::raw('count(*) as on_time_count'))
            ->whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay()
            ])
            ->where('late_minutes', '=', 0)
            ->whereNotNull('clock_in')
            ->groupBy('user_id')
            ->orderByDesc('on_time_count')
            ->limit(5);

        if (!$isGlobalAdmin) {
            $query->whereIn('user_id', $subordinateIds->isEmpty() ? [-1] : $subordinateIds->toArray());
        }

        $stats = $query->get();
            
        // Now fetch employee details from master db
        $userIds = $stats->pluck('user_id')->toArray();
        
        if (empty($userIds)) {
            return collect();
        }
        
        // Get employee details via m_presensi (since user_id = m_presensi.id)
        $presensiUsers = DB::connection('pgsql_master')
            ->table('m_presensi')
            ->join('m_karyawan', 'm_presensi.karyawan_id', '=', 'm_karyawan.id')
            ->whereIn('m_presensi.id', $userIds)
            ->select('m_presensi.id', 'm_karyawan.nama_karyawan', 'm_karyawan.dept_id')
            ->get()
            ->keyBy('id');
            
        // Get department names
        $deptIds = $presensiUsers->pluck('dept_id')->unique()->filter();
        $departments = collect();
        if ($deptIds->isNotEmpty()) {
            $departments = DB::connection('pgsql_master')
                ->table('m_dept')
                ->whereIn('dept_code', $deptIds)
                ->pluck('dept_name', 'dept_code');
        }
            
        // Merge data
        return $stats->map(function ($stat) use ($presensiUsers, $departments) {
            $emp = $presensiUsers->get($stat->user_id);
            $stat->name = $emp ? $emp->nama_karyawan : 'Unknown';
            $stat->dept = ($emp && $emp->dept_id && isset($departments[$emp->dept_id])) 
                ? $departments[$emp->dept_id] 
                : '-';
            return $stat;
        });
    }
    
    /**
     * Get employees with MOST late minutes (Tukang Telat)
     * Criteria: Sum of late_minutes.
     * Sorted by sum DESC.
     */
    public function getMostLateEmployees()
    {
        $dates = $this->getFilterDates();
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        
        $user = auth()->user();
        $isGlobalAdmin = $user ? $user->isGlobalAdmin() : false;
        $subordinateIds = $isGlobalAdmin ? [] : collect($user ? $user->getSubordinateUserIds() : []);

        $query = DB::connection('pgsql')
            ->table('presences')
            ->select('user_id', DB::raw('sum(late_minutes) as total_late_minutes'))
            ->whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay()
            ])
            ->where('late_minutes', '>', 0)
            ->groupBy('user_id')
            ->orderByDesc('total_late_minutes')
            ->limit(5);

        if (!$isGlobalAdmin) {
            $query->whereIn('user_id', $subordinateIds->isEmpty() ? [-1] : $subordinateIds->toArray());
        }

        $stats = $query->get();
            
        // Now fetch employee details from master db
        $userIds = $stats->pluck('user_id')->toArray();
        
        if (empty($userIds)) {
            return collect();
        }
        
        $employees = DB::connection('pgsql_master')
            ->table('m_karyawan')
            ->whereIn('id', $userIds)
            ->select('id', 'nama_karyawan', 'dept_id') // Correct column name
            ->get()
            ->keyBy('id');
            
        // Fetch dept names manual lookup to avoid N+1 or complex joins if tables are messy
        $deptIds = $employees->pluck('dept_id')->unique()->filter();
        $departments = collect();
        if ($deptIds->isNotEmpty()) {
            $departments = DB::connection('pgsql_master')
                ->table('m_dept')
                ->whereIn('dept_code', $deptIds)
                ->pluck('dept_name', 'dept_code');
        }
            
        // Merge data
        return $stats->map(function ($stat) use ($employees, $departments) {
            $emp = $employees->get($stat->user_id);
            $stat->name = $emp ? $emp->nama_karyawan : 'Unknown';
            $stat->dept = ($emp && $emp->dept_id && isset($departments[$emp->dept_id])) 
                ? $departments[$emp->dept_id] 
                : '-';
            return $stat;
        });
    }
}
