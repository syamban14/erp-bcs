<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Get list for employee Live Search
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        
        $query = \App\Models\MPresensi::where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->with(['karyawan.department']);
            
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ilike', '%' . $search . '%')
                  ->orWhere('email', 'ilike', '%' . $search . '%')
                  ->orWhereHas('karyawan', function($q2) use ($search) {
                      $q2->where('nama_karyawan', 'ilike', '%' . $search . '%')
                         ->orWhere('payroll_id', 'ilike', '%' . $search . '%');
                  });
            });
        }
        
        $employees = $query->limit(20)->get()->map(function($emp) {
            return [
                'id' => $emp->id,
                'name' => $emp->karyawan->nama_karyawan ?? $emp->name,
                'department' => $emp->karyawan->department->dept_name ?? ($emp->karyawan->dept_id ?? '-'),
            ];
        });
        
        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'List employees retrieved successfully'
            ],
            'data' => $employees
        ]);
    }

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
            $profileImageUrl = $mPresensi->photo 
                ? url('storage/' . $mPresensi->photo) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($mPresensi->name) . '&background=random';
                
            return response()->json([
                'data' => [
                    'department' => $mPresensi->role ?? 'N/A',
                    'employment_status' => $this->mapEmploymentStatus($mPresensi->employment_type ?? 'N/A'),
                    'join_date' => $mPresensi->created_at ? $mPresensi->created_at->format('Y-m-d') : null,
                    'work_location' => 'N/A',
                    'position' => 'Staff',
                    'profile_image_url' => $profileImageUrl,
                    'employee_id' => '-',
                    'name' => $mPresensi->name,
                    'phone' => $mPresensi->phone ?? '',
                    'address' => $mPresensi->address ?? '',
                    'supervisor' => null,
                    'role' => $mPresensi->role ?? 'user',
                    'is_approver' => in_array(strtolower($mPresensi->role ?? ''), ['supervisor', 'manager', 'hr', 'general_manager', 'direktur']),
                    'bank_account' => '',
                    'npwp' => '',
                    'bpjs_kesehatan' => '',
                    'bpjs_ketenagakerjaan' => '',
                ]
            ]);
        }
        
        // Eager load master data relationships
        $karyawan->load(['department', 'titleInfo']);
        
        // Parse join date (format: d/m/Y or 0/0/0)
        $joinDate = $this->parseDate($karyawan->tgl_masuk);
        
        // Get department name from relationship (fallback to dept_id if not found)
        $department = $karyawan->department?->dept_name ?? $karyawan->dept_id ?? 'N/A';
        
        // Get position/title name from relationship (fallback to title code or default)
        $position = $karyawan->titleInfo?->title ?? $karyawan->title ?? 'Staff';
        
        // Get work location from m_presensi office_location relationship (Via Pivot Multi-Geofencing)
        $locIds = \Illuminate\Support\Facades\DB::connection('pgsql')
            ->table('user_office_locations')
            ->where('user_id', $mPresensi->id)
            ->pluck('office_location_id');
            
        $locations = \App\Models\OfficeLocation::whereIn('id', $locIds)->pluck('name')->toArray();
        $workLocation = !empty($locations) 
            ? implode(', ', $locations) 
            : ($mPresensi->officeLocation?->name ?? 'N/A');
        
        // Map employment status
        $employmentStatus = $this->mapEmploymentStatus($karyawan->status);
        
        // Profile image URL
        $profileImageUrl = $mPresensi->photo 
            ? url('storage/' . $mPresensi->photo) 
            : 'https://ui-avatars.com/api/?name=' . urlencode($mPresensi->name) . '&background=random';
            
        // Get supervisor name
        $supervisorName = null;
        if ($karyawan->title) {
            $atasanDetail = \App\Models\MAtasan::where('title_bawahan', $karyawan->title)->first();
            if ($atasanDetail && $atasanDetail->title_atasan) {
                $supervisor = \App\Models\MKaryawan::where('title', $atasanDetail->title_atasan)->first();
                if ($supervisor) {
                    $supervisorName = $supervisor->nama_karyawan;
                }
            }
        }
        
        $isApprover = in_array(strtolower($mPresensi->role ?? ''), ['supervisor', 'manager', 'hr', 'general_manager', 'direktur']);
        
        return response()->json([
            'data' => [
                'department' => $department,
                'employment_status' => $employmentStatus,
                'join_date' => $joinDate,
                'work_location' => $workLocation,
                'position' => $position,
                'profile_image_url' => $profileImageUrl,
                'employee_id' => $karyawan->payroll_id ?? 'N/A',
                'name' => $mPresensi->name,
                'phone' => $mPresensi->phone ?? $karyawan->telp1 ?? '',
                'address' => $mPresensi->address ?? $karyawan->alamat_ktp ?? '',
                'supervisor' => $supervisorName,
                'role' => $mPresensi->role ?? 'user',
                'is_approver' => $isApprover,
                'bank_account' => $karyawan->no_account_bank ?? '',
                'npwp' => $karyawan->no_npwp ?? '',
                'bpjs_kesehatan' => $karyawan->no_bpjs_kesehatan ?? '',
                'bpjs_ketenagakerjaan' => $karyawan->no_bpjs_ketenagakerjaan ?? '',
            ]
        ]);
    }
    
    /**
     * Parse date from d/m/Y format to Y-m-d
     */
    private function parseDate($dateString)
    {
        if (!$dateString || str_contains($dateString, '0/0/0') || str_contains($dateString, '0000-00-00') || empty(trim($dateString))) {
            return null;
        }
        
        // If it's already Y-m-d format, just return it
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($dateString))) {
            return trim($dateString);
        }
        
        // Try to parse d/m/Y format (legacy compatibility)
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
