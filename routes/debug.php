<?php

// Debug script untuk test employee info
use Illuminate\Support\Facades\Route;

Route::get('/test-employee-debug', function() {
    try {
        $user = App\Models\MPresensi::first();
        
        if (!$user) {
            return response()->json(['error' => 'No user found']);
        }
        
        // Test relasi karyawan
        $karyawan = $user->karyawan;
        
        if (!$karyawan) {
            return response()->json([
                'error' => 'Karyawan not found',
                'user_id' => $user->id,
                'karyawan_id' => $user->karyawan_id
            ]);
        }
        
        // Test payroll_id
        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'karyawan_id' => $user->karyawan_id,
            'karyawan_data' => [
                'id' => $karyawan->id,
                'payroll_id' => $karyawan->payroll_id,
                'nama' => $karyawan->nama_karyawan,
                'dept_id' => $karyawan->dept_id,
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});
