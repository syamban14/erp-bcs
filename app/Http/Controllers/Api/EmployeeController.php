<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Get employee information for profile
     */
    public function getInfo(Request $request)
    {
        // $request->user() returns MPresensi model from API guard
        $mPresensi = $request->user();
        
        // Try to get karyawan data from m_karyawan table
        $karyawan = null;
        
        // Method 1: Via relation (if karyawan_id exists)
        if ($mPresensi->karyawan_id) {
            $karyawan = $mPresensi->karyawan;
        }
        
        // Method 2: Find by email (if relation fails)
        if (!$karyawan && $mPresensi->email) {
            $karyawan = \App\Models\MKaryawan::where('email', $mPresensi->email)->first();
        }
        
        // Method 3: Find by name (last resort)
        if (!$karyawan && $mPresensi->name) {
            $karyawan = \App\Models\MKaryawan::where('nama_karyawan', 'LIKE', '%' . $mPresensi->name . '%')->first();
        }
        
        // If still no karyawan found, return default data from MPresensi
        if (!$karyawan) {
            return response()->json([
                'data' => [
                    'department' => $mPresensi->role ?? 'N/A',
                    'employment_status' => $this->mapEmploymentStatus($mPresensi->employment_type ?? 'N/A'),
                    'join_date' => $mPresensi->created_at ? $mPresensi->created_at->format('Y-m-d') : null,
                    'work_location' => 'N/A',
                ]
            ]);
        }
        
        // Parse join date (format: d/m/Y or 0/0/0)
        $joinDate = $this->parseDate($karyawan->tgl_masuk);
        
        // Get department name
        $department = $this->getDepartmentName($karyawan->dept_id);
        
        // Map employment status
        $employmentStatus = $this->mapEmploymentStatus($karyawan->status);
        
        return response()->json([
            'data' => [
                'department' => $department,
                'employment_status' => $employmentStatus,
                'join_date' => $joinDate,
                'work_location' => $karyawan->lokasi ?? 'N/A',
            ]
        ]);
    }
    
    /**
     * Parse date from d/m/Y format to Y-m-d
     */
    private function parseDate($dateString)
    {
        if (!$dateString || $dateString === '0/0/0' || empty(trim($dateString))) {
            return null;
        }
        
        // Try to parse d/m/Y format
        $parts = explode('/', $dateString);
        if (count($parts) === 3) {
            $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            $year = $parts[2];
            
            // Validate
            if ($year > 0 && $month > 0 && $day > 0) {
                return "{$year}-{$month}-{$day}";
            }
        }
        
        return null;
    }
    
    /**
     * Get department name from dept_id
     * TODO: Join with department master table if exists
     */
    private function getDepartmentName($deptId)
    {
        if (!$deptId) {
            return 'N/A';
        }
        
        // For now, return the dept_id
        // Later, you can join with m_department table if it exists
        return $deptId;
    }
    
    /**
     * Map employment status to readable format
     */
    private function mapEmploymentStatus($status)
    {
        if (!$status) {
            return 'N/A';
        }
        
        $statusMap = [
            'TETAP' => 'Tetap',
            'KONTRAK' => 'Kontrak',
            'BORONGAN' => 'Kontrak',
            'PROBATION' => 'Probation',
            'MAGANG' => 'Magang',
        ];
        
        $upperStatus = strtoupper($status);
        return $statusMap[$upperStatus] ?? $status;
    }
}
