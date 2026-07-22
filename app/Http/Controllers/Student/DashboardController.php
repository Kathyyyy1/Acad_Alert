<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;  

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get student record
        $student = DB::table('students')->where('email', $user->email)->first();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        // Get current period from request or default
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        
        // Get student's block and program info
        $studentInfo = $this->getStudentInfo($student->id);
        
        // Get risk history for trend chart
        $riskHistory = $this->getRiskHistory($student->id, $schoolYear);
        
        // Get current risk score
        $currentRisk = $this->getCurrentRisk($student->id, $currentPeriod, $schoolYear);
        
        // Get subject grades
        $subjectGrades = $this->getSubjectGrades($student->id, $currentPeriod, $schoolYear);
        
        // Get attendance breakdown
        $attendanceBreakdown = $this->getAttendanceBreakdown($student->id, $currentPeriod, $schoolYear);
        
        // Get alerts
        $alerts = $this->getAlerts($student->id, $currentPeriod, $schoolYear);
        $unreadAlerts = $alerts->where('is_acknowledged', false)->count();
        
        // Get intervention recommendations
        $recommendations = $this->getRecommendations($student->id);
        
        // Get counselor info
        $counselorInfo = $this->getCounselorInfo($student->id);
        
        // Calculate progress comparison (before vs after intervention)
        $progressComparison = $this->getProgressComparison($student->id, $schoolYear);
        
        // Get previous period risk (for comparison)
        $previousPeriod = $this->getPreviousPeriod($currentPeriod);
        $previousRisk = $this->getPreviousRisk($student->id, $previousPeriod, $schoolYear);
        
        // Calculate improvement
        $improvement = null;
        if ($currentRisk && $previousRisk) {
            $improvement = $previousRisk->risk_score - $currentRisk->risk_score;
        }
        
        // Get self-help resources
        $resources = $this->getSelfHelpResources($currentRisk->risk_level ?? 'Low');
        
        return view('student.dashboard', [
            'student' => $student,
            'studentInfo' => $studentInfo,
            'riskHistory' => $riskHistory,
            'currentRisk' => $currentRisk,
            'previousRisk' => $previousRisk,
            'improvement' => $improvement,
            'subjectGrades' => $subjectGrades,
            'attendanceBreakdown' => $attendanceBreakdown,
            'alerts' => $alerts,
            'unreadAlerts' => $unreadAlerts,
            'recommendations' => $recommendations,
            'counselorInfo' => $counselorInfo,
            'progressComparison' => $progressComparison,
            'resources' => $resources,
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'schoolYear' => $schoolYear,
        ]);
    }
    
    public function acknowledgeAlert(Request $request)
    {
        $request->validate([
            'flag_id' => 'required|integer|exists:flags,id',
        ]);

        $user = Auth::user();
        $student = DB::table('students')->where('email', $user->email)->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.'
            ], 404);
        }

        // Verify flag belongs to this student
        $flag = DB::table('flags')
            ->where('id', $request->flag_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$flag) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found.'
            ], 404);
        }

        if ($flag->is_acknowledged) {
            return response()->json([
                'success' => false,
                'message' => 'Alert already acknowledged.'
            ], 400);
        }

        // Check if acknowledgment already exists
        $exists = DB::table('alert_acknowledgments')
            ->where('student_id', $student->id)
            ->where('flag_id', $request->flag_id)
            ->exists();

        if ($exists) {
            // Update the existing acknowledgment timestamp
            DB::table('alert_acknowledgments')
                ->where('student_id', $student->id)
                ->where('flag_id', $request->flag_id)
                ->update([
                    'acknowledged_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            // Create new acknowledgment
            DB::table('alert_acknowledgments')->insert([
                'student_id' => $student->id,
                'flag_id' => $request->flag_id,
                'acknowledged_at' => now(),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update the flag
        DB::table('flags')
            ->where('id', $request->flag_id)
            ->update([
                'is_acknowledged' => true,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully.'
        ]);
    }

    // ============================================================
    // Protected Helper Methods
    // ============================================================

    protected function getStudentInfo(int $studentId)
    {
        return DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $studentId)
            ->select(
                'students.*',
                'blocks.name as block_name',
                'year_levels.name as year_level',
                'programs.code as program_code',
                'programs.name as program_name'
            )
            ->first();
    }

    protected function getRiskHistory(int $studentId, string $schoolYear)
    {
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('school_year', $schoolYear)
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
            ->get();
    }

    protected function getCurrentRisk(int $studentId, string $period, string $schoolYear)
    {
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->first();
    }

    protected function getPreviousRisk(int $studentId, ?string $period, string $schoolYear)
    {
        if (!$period) {
            return null;
        }
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->first();
    }

    protected function getSubjectGrades(int $studentId, string $period, string $schoolYear)
    {
        return DB::table('grades')
            ->join('subjects', 'grades.subject_id', '=', 'subjects.id')
            ->where('grades.student_id', $studentId)
            ->where('grades.grading_period', $period)
            ->where('grades.school_year', $schoolYear)
            ->select(
                'grades.*',
                'subjects.subject_code',
                'subjects.subject_name'
            )
            ->orderBy('grades.numerical_grade', 'asc')
            ->get();
    }

    protected function getAttendanceBreakdown(int $studentId, string $period, string $schoolYear)
    {
        return DB::table('attendance_summaries')
            ->join('subjects', 'attendance_summaries.subject_id', '=', 'subjects.id')
            ->where('attendance_summaries.student_id', $studentId)
            ->where('attendance_summaries.grading_period', $period)
            ->where('attendance_summaries.school_year', $schoolYear)
            ->select(
                'attendance_summaries.*',
                'subjects.subject_code',
                'subjects.subject_name'
            )
            ->orderBy('attendance_summaries.attendance_rate', 'asc')
            ->get();
    }

    /**
     * Get alerts for a student.
     * Fixed: Added type hints and Log import
     */
    protected function getAlerts(int $studentId, string $period, string $schoolYear)
    {
        // Get all flags for this student
        $flags = DB::table('flags')
            ->where('student_id', $studentId)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Debug log
        Log::info('Retrieved student alerts', [
            'student_id' => $studentId,
            'count' => $flags->count(),
            'flags' => $flags->pluck('flag_type')->toArray(),
        ]);
        
        // Map flag types to user-friendly messages
        $flagMessages = [
            'high_risk' => 'You are at High Risk. Please see your guidance counselor.',
            'consecutive_high_risk' => 'You have been at High Risk for multiple periods. Immediate attention needed.',
            'low_attendance' => 'Your attendance has dropped below 70%. Please see your instructor.',
            'failing_grade' => 'You have a failing grade in one or more subjects.',
            'attendance_warning' => 'Your attendance is approaching the warning threshold.',
            'attendance_drop' => 'Your attendance has dropped critically.',
            'counselor_update' => 'Your guidance counselor has updated your case.',
            'counselor_action' => 'Your guidance counselor has taken action on your case.',
            'status_update' => 'Your case status has been updated.',
            'priority_update' => 'Your case priority has been updated.',
            'case_resolved' => 'Your case has been resolved!',
            'case_reopened' => 'Your case has been reopened.',
        ];
        
        // Add messages to flags
        foreach ($flags as $flag) {
            $flag->message = $flagMessages[$flag->flag_type] ?? 'You have a new alert.';
        }
        
        return $flags;
    }

    protected function getRecommendations(int $studentId)
    {
        return DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->orderBy('generated_at', 'desc')
            ->first();
    }

    protected function getCounselorInfo(int $studentId)
    {
        // Get student's department
        $student = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $studentId)
            ->select('programs.department_id')
            ->first();
        
        if (!$student) {
            return null;
        }
        
        // Get counselor for this department
        $counselor = DB::table('counselors')
            ->join('users', 'counselors.user_id', '=', 'users.id')
            ->where('counselors.department_id', $student->department_id)
            ->orWhereNull('counselors.department_id')
            ->select(
                'counselors.*',
                'users.name as name',
                'users.email as email'
            )
            ->first();
        
        return $counselor;
    }

    protected function getProgressComparison(int $studentId, string $schoolYear)
    {
        // Get first and last risk scores
        $first = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('school_year', $schoolYear)
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
            ->first();
        
        $last = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->where('school_year', $schoolYear)
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals') desc")
            ->first();
        
        if (!$first || !$last) {
            return null;
        }
        
        return (object) [
            'first_period' => $first->grading_period,
            'first_score' => $first->risk_score,
            'first_level' => $first->risk_level,
            'last_period' => $last->grading_period,
            'last_score' => $last->risk_score,
            'last_level' => $last->risk_level,
            'improvement' => $first->risk_score - $last->risk_score,
        ];
    }

    protected function getPreviousPeriod(?string $current): ?string
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : null;
    }

    protected function getSelfHelpResources(string $riskLevel): array
    {
        $resources = [
            'Low' => [
                [
                    'title' => 'Academic Success Tips',
                    'description' => 'Learn effective study strategies and time management.',
                    'link' => '#',
                    'icon' => 'fas fa-lightbulb',
                ],
                [
                    'title' => 'Peer Tutoring Program',
                    'description' => 'Get help from fellow students in your courses.',
                    'link' => '#',
                    'icon' => 'fas fa-users',
                ],
                [
                    'title' => 'Writing Center',
                    'description' => 'Improve your writing skills with professional tutors.',
                    'link' => '#',
                    'icon' => 'fas fa-pen-fancy',
                ],
            ],
            'Moderate' => [
                [
                    'title' => '📚 Tutoring Services',
                    'description' => 'Programming tutoring: Tues/Thurs 2-4 PM, Room 301',
                    'link' => '#',
                    'icon' => 'fas fa-chalkboard-teacher',
                ],
                [
                    'title' => '📖 Study Skills Workshop',
                    'description' => 'Fridays 1-2 PM, Library Hall',
                    'link' => '#',
                    'icon' => 'fas fa-book',
                ],
                [
                    'title' => '👥 Peer Support Group',
                    'description' => 'Wednesdays 3-4 PM, Guidance Office',
                    'link' => '#',
                    'icon' => 'fas fa-hand-holding-heart',
                ],
                [
                    'title' => 'Time Management Workshop',
                    'description' => 'Learn to balance academics and personal life.',
                    'link' => '#',
                    'icon' => 'fas fa-clock',
                ],
            ],
            'High' => [
                [
                    'title' => '📚 Intensive Tutoring',
                    'description' => 'One-on-one tutoring available daily, 9 AM - 5 PM',
                    'link' => '#',
                    'icon' => 'fas fa-chalkboard-teacher',
                ],
                [
                    'title' => '📖 Academic Counseling',
                    'description' => 'Immediate counseling available at Guidance Office',
                    'link' => '#',
                    'icon' => 'fas fa-headset',
                ],
                [
                    'title' => '👥 Study Group',
                    'description' => 'Join a study group for collaborative learning',
                    'link' => '#',
                    'icon' => 'fas fa-users',
                ],
                [
                    'title' => '📊 Progress Monitoring',
                    'description' => 'Weekly check-ins with your academic adviser',
                    'link' => '#',
                    'icon' => 'fas fa-chart-line',
                ],
                [
                    'title' => '🏠 Parent-Teacher Conference',
                    'description' => 'Schedule a meeting with your parents and teachers',
                    'link' => '#',
                    'icon' => 'fas fa-user-friends',
                ],
            ],
        ];
        
        return $resources[$riskLevel] ?? $resources['Low'];
    }

    // ============================================================
    // Placeholder methods for separate pages
    // ============================================================

    public function grades(Request $request)
    {
        $user = Auth::user();
        $student = DB::table('students')->where('email', $user->email)->first();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        $studentInfo = $this->getStudentInfo($student->id);
        $subjectGrades = $this->getSubjectGrades($student->id, $currentPeriod, $schoolYear);
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        
        return view('student.grades', compact('student', 'studentInfo', 'subjectGrades', 'currentPeriod', 'schoolYear', 'periods'));
    }

    public function attendance(Request $request)
    {
        $user = Auth::user();
        $student = DB::table('students')->where('email', $user->email)->first();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $currentPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');
        $studentInfo = $this->getStudentInfo($student->id);
        $attendanceBreakdown = $this->getAttendanceBreakdown($student->id, $currentPeriod, $schoolYear);
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        
        // Get attendance summary
        $attendanceSummary = DB::table('attendance_summaries')
            ->where('student_id', $student->id)
            ->where('grading_period', $currentPeriod)
            ->where('school_year', $schoolYear)
            ->select(
                DB::raw('AVG(attendance_rate) as overall_attendance'),
                DB::raw('SUM(total_absences) as total_absences'),
                DB::raw('SUM(total_lates) as total_lates'),
                DB::raw('SUM(total_excused) as total_excused'),
                DB::raw('COUNT(*) as subject_count')
            )
            ->first();
        
        return view('student.attendance', compact(
            'student', 'studentInfo', 'attendanceBreakdown', 'attendanceSummary',
            'currentPeriod', 'schoolYear', 'periods'
        ));
    }

    public function counselor(Request $request)
    {
        $user = Auth::user();
        $student = DB::table('students')->where('email', $user->email)->first();
        if (!$student) {
            return redirect()->route('dashboard')->with('error', 'Student record not found.');
        }
        
        $studentInfo = $this->getStudentInfo($student->id);
        $counselorInfo = $this->getCounselorInfo($student->id);
        $currentRisk = $this->getCurrentRisk($student->id, 'Midterm', '2024-2025');
        
        return view('student.counselor', compact('student', 'studentInfo', 'counselorInfo', 'currentRisk'));
    }
}