<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\PresenceController;
use App\Http\Controllers\Api\v1\ShiftController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']); // Public: no auth required


// Mobile Security (Public)
Route::post('/biometric/login', [App\Http\Controllers\Api\v1\BiometricController::class, 'login']);
Route::post('/pin/verify', [App\Http\Controllers\Api\v1\PinController::class, 'verify']);

// Test route - TEMPORARY
Route::get('/test-employee', function() {
    try {
        // Get user with karyawan_id not null
        $user = App\Models\MPresensi::whereNotNull('karyawan_id')->first();
        if (!$user) return response()->json(['error' => 'No user with karyawan_id found']);
        
        $karyawan = $user->karyawan;
        if (!$karyawan) return response()->json(['error' => 'No karyawan', 'karyawan_id' => $user->karyawan_id]);
        
        return response()->json([
            'success' => true,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'karyawan_id' => $karyawan->id,
            'payroll_id' => $karyawan->payroll_id ?? 'NULL',
            'dept_id' => $karyawan->dept_id ?? 'NULL',
            'title' => $karyawan->title ?? 'NULL',
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Presence
    Route::get('/presence', [PresenceController::class, 'index']);
    Route::post('/presence', [PresenceController::class, 'store']);
    
    // Shift Schedule
    Route::get('/my-shift', [ShiftController::class, 'index']);
    Route::get('/my-shift/today', [ShiftController::class, 'today']);
    Route::get('/my-shift/week', [ShiftController::class, 'week']);
    
    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\v1\NotificationController::class, 'index']);
    Route::get('/notifications/{id}/read', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAsRead']);
    Route::get('/notifications/read-all', [\App\Http\Controllers\Api\v1\NotificationController::class, 'markAllAsRead']);
    
    // Schedules
    Route::get('/schedules/today', [\App\Http\Controllers\Api\v1\ScheduleController::class, 'today']);
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load('karyawan');
    });

    // Leaves
    Route::get('/leaves', [\App\Http\Controllers\Api\v1\LeaveController::class, 'index']);
    Route::post('/leaves', [\App\Http\Controllers\Api\v1\LeaveController::class, 'store']);
    
    // Permissions
    Route::get('/permissions', [\App\Http\Controllers\Api\v1\PermissionController::class, 'index']);
    Route::post('/permissions', [\App\Http\Controllers\Api\v1\PermissionController::class, 'store']);
    
    // Attendance Corrections
    Route::get('/corrections', [\App\Http\Controllers\Api\v1\CorrectionController::class, 'index']);
    Route::post('/corrections', [\App\Http\Controllers\Api\v1\CorrectionController::class, 'store']);
    
    // Monthly Recap
    Route::get('/recap', [\App\Http\Controllers\Api\v1\RecapController::class, 'index']);
    
    // Leave Quota
    Route::get('/leave-quota', [\App\Http\Controllers\Api\v1\LeaveQuotaController::class, 'index']);
    Route::get('/leave-quota/history', [\App\Http\Controllers\Api\v1\LeaveQuotaController::class, 'history']);
    
    // Employee Info
    Route::get('/employee/info', [\App\Http\Controllers\Api\v1\EmployeeController::class, 'getInfo']);
    
    // Profile Update
    Route::post('/employee/update', [\App\Http\Controllers\Api\v1\ProfileController::class, 'update']);
    
    // Change Password
    Route::post('/change-password', [\App\Http\Controllers\Api\v1\ProfileController::class, 'changePassword']);
    
    // Outstation Requests
    Route::post('/outstation-requests', [\App\Http\Controllers\Api\v1\OutstationRequestController::class, 'store']);
    Route::get('/outstation-requests', [\App\Http\Controllers\Api\v1\OutstationRequestController::class, 'index']);
    
    // Overtime Requests
    Route::post('/overtime-requests', [\App\Http\Controllers\Api\v1\OvertimeRequestController::class, 'store']);
    Route::get('/overtime-requests', [\App\Http\Controllers\Api\v1\OvertimeRequestController::class, 'index']);
    
    // Fatigue Tests (K3)
    Route::post('/fatigue-tests', [\App\Http\Controllers\Api\v1\FatigueTestController::class, 'store']);
    Route::get('/fatigue-tests/today', [\App\Http\Controllers\Api\v1\FatigueTestController::class, 'todayStatus']);
    
    // Salary Slips
    Route::get('/salary-slips', [\App\Http\Controllers\Api\v1\SalarySlipController::class, 'index']);
    Route::get('/salary-slips/{id}', [\App\Http\Controllers\Api\v1\SalarySlipController::class, 'show']);

    // Loans (Employee)
    Route::get('/loans/summary', [App\Http\Controllers\Api\v1\LoanController::class, 'summary']);
    Route::post('/loans/simulate', [App\Http\Controllers\Api\v1\LoanController::class, 'simulate']);
    Route::get('/loans', [App\Http\Controllers\Api\v1\LoanController::class, 'index']);
    Route::post('/loans', [App\Http\Controllers\Api\v1\LoanController::class, 'store']);
    Route::get('/loans/{id}', [App\Http\Controllers\Api\v1\LoanController::class, 'show']);

    // Mobile Security (Protected)
    Route::post('/biometric/register', [App\Http\Controllers\Api\v1\BiometricController::class, 'register']);
    Route::get('/biometric/status', [App\Http\Controllers\Api\v1\BiometricController::class, 'status']);
    Route::post('/pin/register', [App\Http\Controllers\Api\v1\PinController::class, 'register']);
    Route::post('/pin/reset', [App\Http\Controllers\Api\v1\PinController::class, 'resetPin']);

    // Supervisor Approval (Mobile)
    Route::get('/approvals', [App\Http\Controllers\Api\v1\ApprovalController::class, 'index']);
    Route::get('/approvals/{id}', [App\Http\Controllers\Api\v1\ApprovalController::class, 'show']);
    Route::post('/approvals/{id}/status', [App\Http\Controllers\Api\v1\ApprovalController::class, 'updateStatus']);

    // Announcements & Birthdays
    Route::get('/announcements', [App\Http\Controllers\Api\v1\AnnouncementController::class, 'index']);
    Route::post('/announcements/read', [App\Http\Controllers\Api\v1\AnnouncementController::class, 'markAsRead']);
    Route::post('/announcements/greet', [App\Http\Controllers\Api\v1\AnnouncementController::class, 'greet']);

    // File Proxy — streaming file upload langsung dari storage (tanpa bergantung symlink/nginx)
    // Atasan bisa akses file bukti karyawan via endpoint ini menggunakan token mereka
    Route::get('/files/{path}', [\App\Http\Controllers\Api\v1\FileController::class, 'serve'])
        ->where('path', '.*');
});

    // Admin Routes
    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        // Fatigue Tests Admin
        Route::get('/fatigue-tests/today', [\App\Http\Controllers\Api\v1\Admin\FatigueTestController::class, 'todaySummary']);
        Route::get('/fatigue-tests/employee/{employee_id}', [\App\Http\Controllers\Api\v1\Admin\FatigueTestController::class, 'employeeHistory']);
        
        // Loans Admin
        Route::get('/loans', [App\Http\Controllers\Api\v1\Admin\LoanController::class, 'index']);
        Route::put('/loans/{id}/approve', [App\Http\Controllers\Api\v1\Admin\LoanController::class, 'approve']);
        Route::put('/loans/{id}/reject', [App\Http\Controllers\Api\v1\Admin\LoanController::class, 'reject']);
        Route::put('/loans/{id}/disburse', [App\Http\Controllers\Api\v1\Admin\LoanController::class, 'disburse']);
        Route::get('/loans/{id}/installments', [App\Http\Controllers\Api\v1\Admin\LoanController::class, 'installments']);
    });

});

// Export Rekap Absensi — autentikasi via query ?token= (untuk Flutter url_launcher)
Route::get('/v1/recaps/export', [\App\Http\Controllers\Api\v1\RecapController::class, 'export'])
    ->middleware('query.token');

// Export Slip Gaji — autentikasi via query ?token=
Route::get('/v1/salaries/{id}/export', [\App\Http\Controllers\Api\v1\SalarySlipController::class, 'export'])
    ->middleware('query.token');

require __DIR__.'/debug.php';
