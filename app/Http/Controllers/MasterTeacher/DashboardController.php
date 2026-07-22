<?php

namespace App\Http\Controllers\MasterTeacher;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Department Dashboard 
     */
    public function department(Request $request)
    {
        $departmentId = DepartmentHelper::getDepartmentId($request);
        
        if (!$departmentId) {
            return redirect()->route('dashboard')->with('error', 'Department not found for this user.');
        }

        $department = DB::table('departments')->where('id', $departmentId)->first();
        if (!$department) {
            return redirect()->route('dashboard')->with('error', 'Department not found.');
        }

        $programs = DepartmentHelper::getDepartmentPrograms($request);
        
        // Get current period from request or default to Midterm
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        $programStats = [];
        $totalStudents = 0;
        $totalHighRisk = 0;
        $totalModerateRisk = 0;
        $totalLowRisk = 0;
        
        foreach ($programs as $program) {
            // Get total students for this program
            $studentCount = DB::table('students')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->where('year_levels.program_id', $program->id)
                ->where('students.status', 'Active')
                ->count();
            
            // Get risk distribution for this program
            $riskCounts = DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->where('year_levels.program_id', $program->id)
                ->where('risk_scores.grading_period', $currentPeriod)
                ->where('risk_scores.school_year', $schoolYear)
                ->where('students.status', 'Active')
                ->select('risk_scores.risk_level', DB::raw('count(*) as count'))
                ->groupBy('risk_scores.risk_level')
                ->get();
            
            $lowRisk = 0;
            $moderateRisk = 0;
            $highRisk = 0;
            
            foreach ($riskCounts as $risk) {
                if ($risk->risk_level === 'Low') $lowRisk = $risk->count;
                elseif ($risk->risk_level === 'Moderate') $moderateRisk = $risk->count;
                elseif ($risk->risk_level === 'High') $highRisk = $risk->count;
            }
            
            $totalStudents += $studentCount;
            $totalHighRisk += $highRisk;
            $totalModerateRisk += $moderateRisk;
            $totalLowRisk += $lowRisk;
            
            $programStats[] = (object) [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'student_count' => $studentCount,
                'low_risk' => $lowRisk,
                'moderate_risk' => $moderateRisk,
                'high_risk' => $highRisk,
                'high_risk_percentage' => $studentCount > 0 ? round(($highRisk / max($studentCount, 1)) * 100, 1) : 0,
            ];
        }
        
        // Generate alerts
        $alerts = [];
        foreach ($programStats as $stat) {
            if ($stat->high_risk_percentage > 15) {
                $alerts[] = [
                    'severity' => 'critical',
                    'message' => "{$stat->code} has {$stat->high_risk} High Risk students ({$stat->high_risk_percentage}%). Immediate attention needed.",
                ];
            } elseif ($stat->high_risk_percentage > 8) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => "{$stat->code} has {$stat->high_risk} High Risk students ({$stat->high_risk_percentage}%). Monitor closely.",
                ];
            }
        }
        
        // Get trend data across all periods
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $trendData = [];
        foreach ($periods as $period) {
            $highRiskCount = DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->where('risk_scores.grading_period', $period)
                ->where('risk_scores.school_year', $schoolYear)
                ->where('risk_scores.risk_level', 'High')
                ->where('students.status', 'Active')
                ->count();
            
            $totalInPeriod = DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('programs.department_id', $departmentId)
                ->where('risk_scores.grading_period', $period)
                ->where('risk_scores.school_year', $schoolYear)
                ->where('students.status', 'Active')
                ->count();
            
            $trendData[] = [
                'period' => $period,
                'high_risk_count' => $highRiskCount,
                'total_students' => $totalInPeriod > 0 ? $totalInPeriod : $totalStudents,
                'high_risk_percentage' => $totalInPeriod > 0 ? round(($highRiskCount / max($totalInPeriod, 1)) * 100, 1) : 0,
            ];
        }
        
        $data = [
            'departmentName' => $department->name,
            'departmentCode' => $department->code,
            'totalStudents' => $totalStudents,
            'highRiskCount' => $totalHighRisk,
            'moderateRiskCount' => $totalModerateRisk,
            'lowRiskCount' => $totalLowRisk,
            'needEscalation' => $totalHighRisk,
            'programs' => $programStats,
            'alerts' => $alerts,
            'trendData' => $trendData,
            'departmentId' => $departmentId,
            'currentPeriod' => $currentPeriod,
            'schoolYear' => $schoolYear,
        ];
        
        return view('master-teacher.department', $data);
    }
    
    /**
     * Blocks List - Shows all blocks for a program
     */
    public function blocks(Request $request, $programId)
    {
        if (!DepartmentHelper::isProgramInDepartment($request, $programId)) {
            return redirect()->route('teacher.department')->with('error', 'You do not have access to this program.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        $blocks = DB::table('blocks')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->where('year_levels.program_id', $programId)
            ->select('blocks.*', 'year_levels.year_number', 'year_levels.name as year_level_name')
            ->orderBy('year_levels.year_number')
            ->orderBy('blocks.block_number')
            ->get();
        
        $program = DB::table('programs')->where('id', $programId)->first();
        
        foreach ($blocks as $block) {
            // Get student count
            $block->total_students = DB::table('students')
                ->where('block_id', $block->id)
                ->where('status', 'Active')
                ->count();
            
            // Get risk distribution
            $riskCounts = DB::table('risk_scores')
                ->join('students', 'risk_scores.student_id', '=', 'students.id')
                ->where('students.block_id', $block->id)
                ->where('risk_scores.grading_period', $currentPeriod)
                ->where('risk_scores.school_year', $schoolYear)
                ->where('students.status', 'Active')
                ->select('risk_scores.risk_level', DB::raw('count(*) as count'))
                ->groupBy('risk_scores.risk_level')
                ->get();
            
            $block->low_risk = 0;
            $block->moderate_risk = 0;
            $block->high_risk = 0;
            
            foreach ($riskCounts as $risk) {
                if ($risk->risk_level === 'Low') $block->low_risk = $risk->count;
                elseif ($risk->risk_level === 'Moderate') $block->moderate_risk = $risk->count;
                elseif ($risk->risk_level === 'High') $block->high_risk = $risk->count;
            }
        }
        
        return view('master-teacher.blocks', compact('blocks', 'program', 'currentPeriod', 'schoolYear'));
    }
    
    /**
     * Prepare student data with pre-calculated display values
     */
    private function prepareStudentDisplayData($student)
    {
        // Calculate risk class
        $riskClass = match($student->current_risk) {
            'High' => 'badge-risk-high',
            'Moderate' => 'badge-risk-moderate',
            default => 'badge-risk-low'
        };
        
        // Calculate previous risk class
        $prevRiskClass = match($student->previous_risk) {
            'High' => 'badge-risk-high',
            'Moderate' => 'badge-risk-moderate',
            default => 'badge-risk-low'
        };
        
        // Determine grade color
        $gradeColor = '';
        if ($student->grade < 75) {
            $gradeColor = 'text-danger fw-bold';
        } elseif ($student->grade < 80) {
            $gradeColor = 'text-warning';
        }
        
        // Determine attendance color
        $attendanceColor = '';
        if ($student->attendance < 70) {
            $attendanceColor = 'text-danger fw-bold';
        } elseif ($student->attendance < 80) {
            $attendanceColor = 'text-warning';
        }
        
        // Determine trend
        $trendDisplay = '';
        $trendIcon = '';
        $trendClass = '';
        if ($student->trend === 'improving') {
            $trendDisplay = 'Improving';
            $trendIcon = 'fa-arrow-up';
            $trendClass = 'text-success';
        } elseif ($student->trend === 'worsening') {
            $trendDisplay = 'Worsening';
            $trendIcon = 'fa-arrow-down';
            $trendClass = 'text-danger';
        } else {
            $trendDisplay = 'Stable';
            $trendIcon = 'fa-minus';
            $trendClass = 'text-muted';
        }
        
        // Determine status
        $statusDisplay = '';
        $statusClass = '';
        $statusIcon = '';
        if ($student->has_open_case) {
            $statusDisplay = 'Open Case';
            $statusClass = 'bg-info';
            $statusIcon = 'fa-folder-open';
        } elseif ($student->is_critical) {
            $statusDisplay = 'Critical';
            $statusClass = 'bg-danger';
            $statusIcon = 'fa-exclamation-triangle';
        } else {
            $statusDisplay = '—';
            $statusClass = 'bg-secondary';
            $statusIcon = '';
        }
        
        return (object) [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'student_number' => $student->student_number,
            'grade' => $student->grade,
            'grade_formatted' => number_format($student->grade, 1),
            'grade_color' => $gradeColor,
            'attendance' => $student->attendance,
            'attendance_formatted' => number_format($student->attendance, 1),
            'attendance_color' => $attendanceColor,
            'risk_score' => $student->risk_score,
            'current_risk' => $student->current_risk,
            'current_risk_class' => $riskClass,
            'previous_risk' => $student->previous_risk,
            'previous_risk_class' => $prevRiskClass,
            'has_open_case' => $student->has_open_case,
            'is_critical' => $student->is_critical,
            'trend' => $student->trend,
            'trend_display' => $trendDisplay,
            'trend_icon' => $trendIcon,
            'trend_class' => $trendClass,
            'status_display' => $statusDisplay,
            'status_class' => $statusClass,
            'status_icon' => $statusIcon,
        ];
    }
    
    /**
     * Block Dashboard - Shows all students in a block with their risk data
     */
    public function block(Request $request, $blockId)
    {
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return redirect()->route('teacher.department')->with('error', 'You do not have access to this block.');
        }
        
        // Get current period from request
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        // Calculate previous period
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $currentIndex = array_search($currentPeriod, $periods);
        $previousPeriod = $currentIndex > 0 ? $periods[$currentIndex - 1] : $periods[0];
        
        // Get block details with relationships
        $block = DB::table('blocks')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('blocks.id', $blockId)
            ->select(
                'blocks.*',
                'year_levels.year_number',
                'year_levels.name as year_level_name',
                'programs.code as program_code',
                'programs.name as program_name',
                'programs.id as program_id'
            )
            ->first();
        
        if (!$block) {
            return redirect()->route('teacher.department')->with('error', 'Block not found.');
        }
        
        // Get all active students in this block
        $students = DB::table('students')
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        
        $studentIds = $students->pluck('id')->toArray();
        $studentData = [];
        
        // Initialize counts
        $lowRiskCount = 0;
        $moderateRiskCount = 0;
        $highRiskCount = 0;
        
        // If there are no students, return early
        if (empty($studentIds)) {
            $data = [
                'block' => $block,
                'blockName' => $block->name ?? 'Block',
                'blockId' => $blockId,
                'currentPeriod' => $currentPeriod,
                'previousPeriod' => $previousPeriod,
                'lowRiskCount' => 0,
                'moderateRiskCount' => 0,
                'highRiskCount' => 0,
                'students' => collect([]),
                'totalStudents' => 0,
            ];
            return view('master-teacher.block', $data);
        }
        
        // ========================================
        // BATCH QUERIES - ONE QUERY PER TABLE
        // ========================================
        
        // 1. Get current risk scores
        $riskScores = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');
        
        // 2. Get previous risk scores
        $previousRiskScores = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $previousPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->keyBy('student_id');
        
        // 3. Get average grades per student
        $grades = DB::table('grades')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->select('student_id', DB::raw('AVG(numerical_grade) as avg_grade'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');
        
        // 4. Get average attendance per student
        $attendance = DB::table('attendance_summaries')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->select('student_id', DB::raw('AVG(attendance_rate) as avg_attendance'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');
        
        // 5. Get open cases
        $openCases = DB::table('cases')
            ->whereIn('student_id', $studentIds)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->get()
            ->pluck('student_id')
            ->toArray();
        
        // 6. Get consecutive high risk flags
        $criticalFlags = DB::table('flags')
            ->whereIn('student_id', $studentIds)
            ->where('flag_type', 'consecutive_high_risk')
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->get()
            ->pluck('student_id')
            ->toArray();
        
        // Process each student
        foreach ($students as $student) {
            // Get current risk
            $currentRisk = $riskScores->get($student->id);
            
            // Get previous risk
            $previousRisk = $previousRiskScores->get($student->id);
            
            // Get average grade
            $gradeData = $grades->get($student->id);
            $avgGrade = $gradeData ? round($gradeData->avg_grade, 1) : null;
            
            // Get attendance rate
            $attendanceData = $attendance->get($student->id);
            $attendanceRate = $attendanceData ? round($attendanceData->avg_attendance, 1) : null;
            
            // Determine risk level
            $riskLevel = $currentRisk ? $currentRisk->risk_level : 'No Data';
            $riskScore = $currentRisk ? $currentRisk->risk_score : 0;
            $previousRiskLevel = $previousRisk ? $previousRisk->risk_level : 'No Data';
            
            // Count risk levels (only if data exists)
            if ($riskLevel === 'Low') $lowRiskCount++;
            elseif ($riskLevel === 'Moderate') $moderateRiskCount++;
            elseif ($riskLevel === 'High') $highRiskCount++;
            
            // Determine if has open case
            $hasOpenCase = in_array($student->id, $openCases);
            
            // Determine if critical (2+ consecutive high risk)
            $isCritical = in_array($student->id, $criticalFlags);
            
            // Determine trend
            $trend = 'stable';
            if ($previousRiskLevel !== 'No Data' && $riskLevel !== 'No Data') {
                $riskValues = ['Low' => 1, 'Moderate' => 2, 'High' => 3];
                $currentValue = $riskValues[$riskLevel] ?? 0;
                $previousValue = $riskValues[$previousRiskLevel] ?? 0;
                
                if ($currentValue > $previousValue) $trend = 'worsening';
                elseif ($currentValue < $previousValue) $trend = 'improving';
                else $trend = 'stable';
            }
            
            // Create base student object
            $baseStudent = (object) [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->student_number,
                'grade' => $avgGrade ?? 0,
                'attendance' => $attendanceRate ?? 0,
                'risk_score' => $riskScore,
                'current_risk' => $riskLevel,
                'previous_risk' => $previousRiskLevel,
                'has_open_case' => $hasOpenCase,
                'is_critical' => $isCritical,
                'trend' => $trend,
            ];
            
            // Prepare display data
            $studentData[] = $this->prepareStudentDisplayData($baseStudent);
        }
        
        // Sort students: Critical first, then High Risk, then by name
        usort($studentData, function($a, $b) {
            // Critical first
            if ($a->is_critical && !$b->is_critical) return -1;
            if (!$a->is_critical && $b->is_critical) return 1;
            
            // Then High Risk
            $riskOrder = ['High' => 0, 'Moderate' => 1, 'Low' => 2];
            $aOrder = $riskOrder[$a->current_risk] ?? 3;
            $bOrder = $riskOrder[$b->current_risk] ?? 3;
            if ($aOrder !== $bOrder) return $aOrder - $bOrder;
            
            // Then by name
            return strcmp($a->last_name, $b->last_name);
        });
        
        $data = [
            'block' => $block,
            'blockName' => $block->name ?? 'Block',
            'blockId' => $blockId,
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'lowRiskCount' => $lowRiskCount,
            'moderateRiskCount' => $moderateRiskCount,
            'highRiskCount' => $highRiskCount,
            'students' => collect($studentData),
            'totalStudents' => count($studentData),
            'schoolYear' => $schoolYear,
        ];
        
        return view('master-teacher.block', $data);
    }
}