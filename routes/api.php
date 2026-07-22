<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminChartController;
use App\Http\Controllers\Api\TeacherChartController;
use App\Http\Controllers\Api\CounselorChartController;
use App\Http\Controllers\Api\StudentChartController;


Route::middleware('auth')->group(function () {
    
    // ======== ADMIN CHARTS ========
    Route::prefix('charts/admin')->group(function () {
        Route::get('/risk-by-department', [AdminChartController::class, 'riskByDepartment']);
        Route::get('/risk-distribution', [AdminChartController::class, 'riskDistribution']);
        Route::get('/risk-trend', [AdminChartController::class, 'riskTrend']);
    });
    
    // ======== MASTER TEACHER CHARTS ========
    Route::prefix('charts/teacher')->middleware(['role:master_teacher'])->group(function () {
        Route::get('/department-risk', [TeacherChartController::class, 'riskByProgram']);
        Route::get('/department-trend', [TeacherChartController::class, 'departmentRiskTrend']);
        Route::get('/block-risk/{blockId}', [TeacherChartController::class, 'blockRiskDistribution']);
    });
    
    // ======== COUNSELOR CHARTS ========
    Route::prefix('charts/counselor')->middleware(['role:guidance_counselor'])->group(function () {
        Route::get('/priority-distribution', [CounselorChartController::class, 'priorityDistribution']);
        Route::get('/status-distribution', [CounselorChartController::class, 'statusDistribution']);
        Route::get('/caseload-trend', [CounselorChartController::class, 'caseloadTrend']);
        Route::get('/student-risk/{studentId}', [CounselorChartController::class, 'studentRiskTrend']);
    });
    
    // ======== STUDENT CHARTS ========
    Route::prefix('charts/student')->middleware(['role:student'])->group(function () {
        Route::get('/risk-trend', [StudentChartController::class, 'riskTrend']);
        Route::get('/grades', [StudentChartController::class, 'grades']);
    });
});

// Fallback route for chart API errors
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Chart API endpoint not found.',
    ], 404);
});