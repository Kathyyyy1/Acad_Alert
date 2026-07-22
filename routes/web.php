<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\MasterTeacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Counselor\DashboardController as CounselorDashboardController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\MasterTeacher\RiskScoringController;
use App\Http\Controllers\MasterTeacher\EscalationController;
use App\Http\Controllers\MasterTeacher\InterventionController;
use App\Http\Controllers\Student\RecommendationController;
use App\Http\Controllers\Api\AdminChartController;
use App\Http\Controllers\Api\TeacherChartController;
use App\Http\Controllers\Api\CounselorChartController;
use App\Http\Controllers\Api\StudentChartController;

// ========================================
// Public Routes
// ========================================

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// ========================================
// Authentication Routes
// ========================================

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ========================================
// Helper Routes for Master Teacher
// ========================================

Route::middleware(['auth', 'role:master_teacher'])->group(function () {
    // Get student count for a block
    Route::get('/teacher/block/{blockId}/student-count', function ($blockId) {
        $count = DB::table('students')->where('block_id', $blockId)->where('status', 'Active')->count();
        return response()->json(['count' => $count]);
    })->name('teacher.block.studentCount');
    
    // Get students for a block (used in intervention modal for student selection)
    Route::get('/teacher/block/{blockId}/students', function ($blockId) {
        $students = DB::table('students')
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->select('id', 'first_name', 'last_name', 'student_number')
            ->get();
        return response()->json([
            'success' => true,
            'students' => $students,
        ]);
    })->name('teacher.block.students');
});

// ========================================
// Protected Routes
// ========================================

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        $user = Auth::user();
        
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'master_teacher') {
            return redirect()->route('teacher.department');
        } elseif ($user->role === 'guidance_counselor') {
            return redirect()->route('counselor.dashboard');
        } elseif ($user->role === 'student') {
            return redirect()->route('student.dashboard');
        }
        
        return redirect()->route('login');
    })->name('dashboard');
    
    // ============================================
    // CHART.JS API ENDPOINTS
    // ============================================
    
    // ======== ADMIN CHARTS ========
    Route::prefix('charts/admin')->group(function () {
        Route::get('/risk-by-department', [AdminChartController::class, 'riskByDepartment']);
        Route::get('/risk-distribution', [AdminChartController::class, 'riskDistribution']);
        Route::get('/risk-trend', [AdminChartController::class, 'riskTrend']);
    });
    
    // ======== MASTER TEACHER CHARTS ========
    Route::prefix('charts/teacher')->middleware(['auth', 'role:master_teacher'])->group(function () {
        Route::get('/department-risk', [TeacherChartController::class, 'riskByProgram']);
        Route::get('/department-trend', [TeacherChartController::class, 'departmentRiskTrend']);
        Route::get('/block-risk/{blockId}', [TeacherChartController::class, 'blockRiskDistribution']);
    });
    
    // ======== COUNSELOR CHARTS ========
    Route::prefix('charts/counselor')->middleware(['auth', 'role:guidance_counselor', 'department.isolation'])->group(function () {
        Route::get('/priority-distribution', [CounselorChartController::class, 'priorityDistribution']);
        Route::get('/status-distribution', [CounselorChartController::class, 'statusDistribution']);
        Route::get('/caseload-trend', [CounselorChartController::class, 'caseloadTrend']);
        Route::get('/student-risk/{studentId}', [CounselorChartController::class, 'studentRiskTrend']);
    });
    
    // ======== STUDENT CHARTS ========
    Route::prefix('charts/student')->middleware(['auth', 'role:student'])->group(function () {
        Route::get('/risk-trend', [StudentChartController::class, 'riskTrend']);
        Route::get('/grades', [StudentChartController::class, 'grades']);
    });
    
    // ======== ADMIN ROUTES ========
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // ============================================
        // USERS MANAGEMENT - FULL CRUD
        // ============================================
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('users.index');
        Route::get('/users/create', [AdminDashboardController::class, 'createUser'])->name('users.create');
        Route::post('/users', [AdminDashboardController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{id}/edit', [AdminDashboardController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{id}', [AdminDashboardController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{id}/delete', [AdminDashboardController::class, 'deleteUser'])->name('users.delete');
        
        // ============================================
        // AJAX ROUTES FOR DYNAMIC DROPDOWNS
        // ============================================
        // Department -> Programs
        Route::get('/get-programs/{departmentId}', [AdminDashboardController::class, 'getProgramsByDepartment'])->name('get.programs');
        // Program -> Year Levels
        Route::get('/get-year-levels/{programId}', [AdminDashboardController::class, 'getYearLevelsByProgram'])->name('get.year.levels');
        // Year Level -> Blocks
        Route::get('/get-blocks/{yearLevelId}', [AdminDashboardController::class, 'getBlocksByYearLevel'])->name('get.blocks');
        
        // ============================================
        // END OF USERS MANAGEMENT & AJAX ROUTES
        // ============================================
        
        // ============================================
        // ACADEMIC STRUCTURE MANAGEMENT - FULL CRUD
        // ============================================
        Route::prefix('academic')->name('academic.')->group(function () {
            // Main academic page
            Route::get('/', [AdminDashboardController::class, 'academic'])->name('index');
            
            // Department Routes
            Route::post('/department/store', [AdminDashboardController::class, 'storeDepartment'])->name('department.store');
            Route::get('/department/{id}/edit', [AdminDashboardController::class, 'editDepartment'])->name('department.edit');
            Route::put('/department/{id}', [AdminDashboardController::class, 'updateDepartment'])->name('department.update');
            Route::delete('/department/{id}/delete', [AdminDashboardController::class, 'deleteDepartment'])->name('department.delete');
            
            // Program Routes
            Route::post('/program/store', [AdminDashboardController::class, 'storeProgram'])->name('program.store');
            Route::get('/program/{id}/edit', [AdminDashboardController::class, 'editProgram'])->name('program.edit');
            Route::put('/program/{id}', [AdminDashboardController::class, 'updateProgram'])->name('program.update');
            Route::delete('/program/{id}/delete', [AdminDashboardController::class, 'deleteProgram'])->name('program.delete');
            
            // Subject Routes
            Route::post('/subject/store', [AdminDashboardController::class, 'storeSubject'])->name('subject.store');
            Route::get('/subject/{id}/edit', [AdminDashboardController::class, 'editSubject'])->name('subject.edit');
            Route::put('/subject/{id}', [AdminDashboardController::class, 'updateSubject'])->name('subject.update');
            Route::delete('/subject/{id}/delete', [AdminDashboardController::class, 'deleteSubject'])->name('subject.delete');
        });
        
        // Risk Configuration
        Route::get('/risk/config', [AdminDashboardController::class, 'riskConfig'])->name('risk.config');
        Route::post('/risk/config/save', [AdminDashboardController::class, 'saveRiskConfig'])->name('risk.save');
        Route::get('/risk/test-ai', [AdminDashboardController::class, 'testAIConnection'])->name('risk.test-ai');
        
        // ============================================
        // PAYMENT ROUTES
        // ============================================
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'payments'])->name('index');
            Route::get('/export', [AdminDashboardController::class, 'exportPayments'])->name('export');  // NEW
            Route::get('/{id}/view', [AdminDashboardController::class, 'viewPayment'])->name('view');
            Route::get('/{id}/info', [AdminDashboardController::class, 'getPaymentInfo'])->name('info');
            // FIXED: Added where('id', '[0-9]+') constraint to ensure integer parameter
            Route::post('/{id}/record', [AdminDashboardController::class, 'recordPayment'])->name('record')->where('id', '[0-9]+');
        });
        
        // Audit Logs
        Route::get('/audit/logs', [AdminDashboardController::class, 'auditLogs'])->name('audit.logs');
        
        // School Year
        Route::get('/schoolyear', [AdminDashboardController::class, 'schoolYear'])->name('schoolyear.index');
        Route::post('/schoolyear/create', [AdminDashboardController::class, 'createSchoolYear'])->name('schoolyear.create');
        Route::post('/schoolyear/archive/{id}', [AdminDashboardController::class, 'archiveSchoolYear'])->name('schoolyear.archive');
        Route::post('/schoolyear/rollover', [AdminDashboardController::class, 'rolloverSchoolYear'])->name('schoolyear.rollover');
        
        // System Health
        Route::get('/system/health', [AdminDashboardController::class, 'systemHealth'])->name('system.health');
    });
    
    // ======== MASTER TEACHER ROUTES ========
    Route::prefix('teacher')->name('teacher.')->middleware(['role:master_teacher', 'department.isolation'])->group(function () {
        Route::get('/department', [TeacherDashboardController::class, 'department'])->name('department');
        Route::get('/blocks/{programId}', [TeacherDashboardController::class, 'blocks'])->name('blocks');
        Route::get('/block/{blockId}', [TeacherDashboardController::class, 'block'])->name('block');
        
        // ============================================
        // RECOMMENDATION MANAGEMENT ROUTES (Dedicated Page)
        // ============================================
        Route::get('/recommendations', [InterventionController::class, 'index'])->name('recommendations');
        Route::post('/recommendations/generate-block', [InterventionController::class, 'generateForBlock'])->name('recommendations.generate.block');
        Route::post('/recommendations/generate-selected', [InterventionController::class, 'generateForSelected'])->name('recommendations.generate.selected');
        Route::get('/recommendations/{id}/edit', [InterventionController::class, 'editRecommendation'])->name('recommendation.edit');
        Route::put('/recommendations/{id}', [InterventionController::class, 'updateRecommendation'])->name('recommendation.update');
        Route::delete('/recommendations/{id}/delete', [InterventionController::class, 'deleteRecommendation'])->name('recommendation.delete');
        Route::get('/recommendations/{id}/view', [InterventionController::class, 'viewRecommendation'])->name('recommendation.view');

        // ============================================
        // RISK SCORING ROUTES
        // ============================================
        Route::post('/block/{blockId}/run-scoring', [RiskScoringController::class, 'runScoring'])->name('runScoring');
        Route::post('/block/{blockId}/refresh-scoring', [RiskScoringController::class, 'refreshScoring'])->name('refreshScoring');
        Route::get('/block/{blockId}/scoring-status', [RiskScoringController::class, 'getScoringStatus'])->name('scoringStatus');
        
        // ============================================
        // ESCALATION ROUTES
        // ============================================
        Route::post('/bulk-escalate', [EscalationController::class, 'bulkEscalate'])->name('bulkEscalate');
        Route::post('/check-escalation-status', [EscalationController::class, 'checkEscalationStatus'])->name('checkEscalationStatus');
        Route::post('/reset-escalation', [EscalationController::class, 'resetEscalation'])->name('resetEscalation');
        Route::post('/bulk-reset-escalation', [EscalationController::class, 'bulkResetEscalation'])->name('bulkResetEscalation');
        Route::post('/can-reset-escalation', [EscalationController::class, 'canResetEscalation'])->name('canResetEscalation');
        Route::get('/block/{blockId}/escalated-students', [EscalationController::class, 'getEscalatedStudentsInBlock'])->name('escalatedStudents');
    });
    
    // ======== COUNSELOR ROUTES ========
    Route::prefix('counselor')->name('counselor.')->middleware(['role:guidance_counselor', 'department.isolation'])->group(function () {
        Route::get('/dashboard', [CounselorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/cases', [CounselorDashboardController::class, 'index'])->name('cases');
        Route::get('/case/{caseId}', [CounselorDashboardController::class, 'show'])->name('case');
        
        // Session Management
        Route::post('/case/{caseId}/session', [CounselorDashboardController::class, 'storeSession'])->name('session.store');
        
        // Case Actions
        Route::post('/case/{caseId}/resolve', [CounselorDashboardController::class, 'resolve'])->name('resolve');
        Route::post('/case/{caseId}/reopen', [CounselorDashboardController::class, 'reopen'])->name('reopen');
        Route::post('/case/{caseId}/update-priority', [CounselorDashboardController::class, 'updatePriority'])->name('priority.update');
        Route::post('/case/{caseId}/update-status', [CounselorDashboardController::class, 'updateStatus'])->name('status.update');
    });
    
    // ======== STUDENT ROUTES ========
    Route::prefix('student')->name('student.')->middleware(['role:student'])->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/grades', [StudentDashboardController::class, 'grades'])->name('grades');
        Route::get('/attendance', [StudentDashboardController::class, 'attendance'])->name('attendance');
        Route::get('/counselor', [StudentDashboardController::class, 'counselor'])->name('counselor');
        
        // Alert Acknowledgment
        Route::post('/acknowledge-alert', [StudentDashboardController::class, 'acknowledgeAlert'])->name('acknowledgeAlert');
        
        // ============================================
        // STUDENT RECOMMENDATION ROUTES
        // ============================================
        Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations');
        Route::post('/recommendation/complete', [RecommendationController::class, 'markCompleted'])->name('recommendation.complete');
        Route::get('/recommendations/pending-count', [RecommendationController::class, 'getPendingCount'])->name('recommendations.pending');
    });
    
    // ======== PROFILE ROUTES ========
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');
    
    Route::get('/settings', function () {
        return view('settings');
    })->name('settings');
});