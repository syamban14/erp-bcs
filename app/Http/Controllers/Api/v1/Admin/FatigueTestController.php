<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FatigueTest;
use App\Models\MKaryawan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FatigueTestController extends Controller
{
    /**
     * Get today's fatigue test summary for admin dashboard
     * GET /api/admin/fatigue-tests/today
     */
    public function todaySummary(Request $request): JsonResponse
    {
        // Validate query parameters
        $request->validate([
            'date' => 'nullable|date',
            'fatigue_level' => 'nullable|in:normal,moderate,severe',
            'department' => 'nullable|string',
        ]);
        
        $date = $request->input('date', today()->toDateString());
        $fatigueLevel = $request->input('fatigue_level');
        $department = $request->input('department');
        
        // Get all tests for the date
        $testsQuery = FatigueTest::whereDate('test_datetime', $date);
        
        if ($fatigueLevel) {
            $testsQuery->where('fatigue_level', $fatigueLevel);
        }
        
        $tests = $testsQuery->get();
        
        // Calculate summary statistics
        $summary = [
            'total_tests' => $tests->count(),
            'total_employees' => $tests->unique('employee_id')->count(),
            'not_tested' => 0, // TODO: Calculate from total active employees
            'normal' => $tests->where('fatigue_level', 'normal')->count(),
            'moderate' => $tests->where('fatigue_level', 'moderate')->count(),
            'severe' => $tests->where('fatigue_level', 'severe')->count(),
            'severe_retried' => $tests->where('fatigue_level', 'severe')->where('is_retry', true)->count(),
            'severe_pending' => $tests->where('fatigue_level', 'severe')->where('is_retry', false)->count(),
        ];
        
        // Get severe employees with details
        $severeEmployees = $this->getEmployeeDetails(
            $tests->where('fatigue_level', 'severe'),
            $department
        );
        
        // Get moderate employees with details
        $moderateEmployees = $this->getEmployeeDetails(
            $tests->where('fatigue_level', 'moderate'),
            $department
        );
        
        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'summary' => $summary,
                'severe_employees' => $severeEmployees,
                'moderate_employees' => $moderateEmployees,
            ],
        ]);
    }
    
    /**
     * Get fatigue test history for specific employee
     * GET /api/admin/fatigue-tests/employee/{employee_id}
     */
    public function employeeHistory(Request $request, int $employeeId): JsonResponse
    {
        // Validate query parameters
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', today()->toDateString());
        $limit = $request->input('limit', 50);
        
        // Get employee details
        $employee = MKaryawan::on('pgsql_master')
            ->with(['division', 'department'])
            ->find($employeeId);
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan tidak ditemukan',
            ], 404);
        }
        
        // Get tests
        $tests = FatigueTest::forUser($employeeId)
            ->whereBetween('test_datetime', [$startDate, $endDate])
            ->orderBy('test_datetime', 'desc')
            ->limit($limit)
            ->get();
        
        // Calculate statistics
        $statistics = [
            'total_tests' => $tests->count(),
            'normal_count' => $tests->where('fatigue_level', 'normal')->count(),
            'moderate_count' => $tests->where('fatigue_level', 'moderate')->count(),
            'severe_count' => $tests->where('fatigue_level', 'severe')->count(),
            'avg_reaction_ms' => $tests->avg('reaction_avg_ms'),
            'avg_memory_score' => $tests->avg('memory_score'),
        ];
        
        return response()->json([
            'success' => true,
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->nama,
                    'position' => $employee->jabatan,
                    'department' => $employee->department->dept_name ?? null,
                ],
                'statistics' => $statistics,
                'tests' => $tests->map(function ($test) {
                    return [
                        'id' => $test->id,
                        'test_datetime' => $test->test_datetime->toIso8601String(),
                        'fatigue_level' => $test->fatigue_level,
                        'memory_score' => $test->memory_score,
                        'sleep_time' => $test->sleep_time->format('H:i'),
                        'reaction_avg_ms' => $test->reaction_avg_ms,
                        'is_retry' => $test->is_retry,
                    ];
                }),
            ],
        ]);
    }
    
    /**
     * Helper: Get employee details for tests
     */
    private function getEmployeeDetails($tests, $departmentFilter = null)
    {
        $userIds = $tests->pluck('user_id')->unique()->toArray();
        
        if (empty($userIds)) {
            return [];
        }
        
        // Get employee details from master_db
        $employeesQuery = MKaryawan::on('pgsql_master')
            ->with(['division', 'department'])
            ->whereIn('id', $userIds);
        
        if ($departmentFilter) {
            $employeesQuery->whereHas('department', function ($q) use ($departmentFilter) {
                $q->where('dept_name', 'like', "%{$departmentFilter}%");
            });
        }
        
        $employees = $employeesQuery->get()->keyBy('id');
        
        // Map tests with employee details
        return $tests->map(function ($test) use ($employees) {
            $employee = $employees->get($test->user_id);
            
            if (!$employee) {
                return null;
            }
            
            // Check if has clocked in today (from presences table)
            $hasClockedIn = DB::connection('pgsql')
                ->table('presences')
                ->where('user_id', $test->user_id)
                ->whereDate('tanggal', $test->test_datetime->toDateString())
                ->exists();
            
            return [
                'user_id' => $test->user_id,
                'employee_name' => $employee->nama,
                'employee_code' => $employee->nik,
                'position' => $employee->jabatan,
                'department' => $employee->department->dept_name ?? null,
                'test_time' => $test->test_datetime->format('H:i:s'),
                'fatigue_level' => $test->fatigue_level,
                'memory_score' => $test->memory_score,
                'reaction_avg_ms' => $test->reaction_avg_ms,
                'has_retried' => $test->is_retry,
                'can_retry_at' => $test->retry_after?->format('H:i:s'),
                'has_clocked_in' => $hasClockedIn,
            ];
        })->filter()->values();
    }
}
