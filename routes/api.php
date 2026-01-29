<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\ShiftController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

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
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::get('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    
    // Schedules
    Route::get('/schedules/today', [\App\Http\Controllers\Api\ScheduleController::class, 'today']);
    
    Route::get('/user', function (Request $request) {
        return $request->user()->load('karyawan');
    });

    // Leaves
    Route::get('/leaves', [\App\Http\Controllers\Api\LeaveController::class, 'index']);
    Route::post('/leaves', [\App\Http\Controllers\Api\LeaveController::class, 'store']);
    
    // Permissions
    Route::get('/permissions', [\App\Http\Controllers\Api\PermissionController::class, 'index']);
    Route::post('/permissions', [\App\Http\Controllers\Api\PermissionController::class, 'store']);
    
    // Attendance Corrections
    Route::get('/corrections', [\App\Http\Controllers\Api\CorrectionController::class, 'index']);
    Route::post('/corrections', [\App\Http\Controllers\Api\CorrectionController::class, 'store']);
    
    // Monthly Recap
    Route::get('/recap', [\App\Http\Controllers\Api\RecapController::class, 'index']);
    
    // Leave Quota
    Route::get('/leave-quota', [\App\Http\Controllers\Api\LeaveQuotaController::class, 'index']);
    Route::get('/leave-quota/history', [\App\Http\Controllers\Api\LeaveQuotaController::class, 'history']);
    
    // Employee Info
    Route::get('/employee/info', [\App\Http\Controllers\Api\EmployeeController::class, 'getInfo']);
    
    // Outstation Requests
    Route::post('/outstation-requests', [\App\Http\Controllers\Api\OutstationRequestController::class, 'store']);
    Route::get('/outstation-requests', [\App\Http\Controllers\Api\OutstationRequestController::class, 'index']);
    
    // Overtime Requests
    Route::post('/overtime-requests', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'store']);
    Route::get('/overtime-requests', [\App\Http\Controllers\Api\OvertimeRequestController::class, 'index']);
    
    // Salary Slips
    Route::get('/salary-slips', [\App\Http\Controllers\Api\SalarySlipController::class, 'index']);
    Route::get('/salary-slips/{id}', [\App\Http\Controllers\Api\SalarySlipController::class, 'show']);
});
