<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminActionController;

Route::get('/', function () {
    return redirect('/admin');
});

// Approval Center - Simple UI for approve/reject
Route::get('/approval-center', function () {
    return view('approval-center');
});

// Approval Center - Data endpoints (no auth required for admin web)
Route::get('/approval-center/permissions', [App\Http\Controllers\ApprovalCenterController::class, 'getPermissions']);
Route::get('/approval-center/corrections', [App\Http\Controllers\ApprovalCenterController::class, 'getCorrections']);
Route::get('/approval-center/leaves', [App\Http\Controllers\ApprovalCenterController::class, 'getLeaves']);

// Admin action endpoints (simple API for approve/reject)
Route::prefix('admin-api')->group(function () {
    Route::post('/permissions/{id}/approve', [AdminActionController::class, 'approvePermission']);
    Route::post('/permissions/{id}/reject', [AdminActionController::class, 'rejectPermission']);
    Route::post('/corrections/{id}/approve', [AdminActionController::class, 'approveCorrection']);
    Route::post('/corrections/{id}/reject', [AdminActionController::class, 'rejectCorrection']);
    Route::post('/leaves/{id}/approve', [AdminActionController::class, 'approveLeave']);
    Route::post('/leaves/{id}/reject', [AdminActionController::class, 'rejectLeave']);
});
