<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Http\Middleware\DepartmentIsolationMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log; 

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get counselor ID and department ID from request (set by middleware)
        $counselor = DepartmentIsolationMiddleware::getCounselor($request);
        $departmentId = DepartmentIsolationMiddleware::getDepartmentId($request);
        
        if (!$counselor) {
            $counselor = DB::table('counselors')->where('user_id', $user->id)->first();
            if (!$counselor) {
                return redirect()->route('dashboard')->with('error', 'Counselor record not found.');
            }
        }
        
        // Get filters from request
        $priorityFilter = $request->input('priority', 'all');
        $statusFilter = $request->input('status', 'all');
        $departmentFilter = $request->input('department', 'all');
        $searchFilter = $request->input('search', '');
        
        // Build query for cases - FILTER BY COUNSELOR'S DEPARTMENT
        $query = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('users', 'cases.escalated_by', '=', 'users.id')
            ->leftJoin('counselors', 'cases.counselor_id', '=', 'counselors.id')
            ->leftJoin('departments', 'counselors.department_id', '=', 'departments.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselor->id)
            ->select(
                'cases.*',
                'students.first_name as student_first_name',
                'students.last_name as student_last_name',
                'students.student_number',
                'users.name as escalated_by_name',
                'departments.name as department_name',
                'programs.code as program_code'
            );
        
        // APPLY DEPARTMENT FILTER - Only show cases from the counselor's assigned department
        if ($departmentId) {
            $query->where('programs.department_id', $departmentId);
        }
        
        // Apply filters
        if ($priorityFilter !== 'all') {
            $query->where('cases.priority', $priorityFilter);
        }
        
        if ($statusFilter !== 'all') {
            $query->where('cases.status', str_replace('_', ' ', $statusFilter));
        }
        
        if ($departmentFilter !== 'all') {
            $query->where('departments.code', $departmentFilter);
        }
        
        if (!empty($searchFilter)) {
            $query->where(function($q) use ($searchFilter) {
                $q->where('students.first_name', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.last_name', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.student_number', 'LIKE', "%{$searchFilter}%");
            });
        }
        
        // Get cases
        $cases = $query->orderByRaw("FIELD(cases.priority, 'Critical', 'High', 'Medium', 'Low')")
                       ->orderBy('cases.updated_at', 'desc')
                       ->get();
        
        // Get statistics - FILTER BY DEPARTMENT
        $stats = $this->getStatistics($counselor->id, $departmentId);
        
        // Get status distribution for chart - FILTER BY DEPARTMENT
        $statusDistribution = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselor->id)
            ->when($departmentId, function($q) use ($departmentId) {
                return $q->where('programs.department_id', $departmentId);
            })
            ->select('cases.status', DB::raw('count(*) as count'))
            ->groupBy('cases.status')
            ->get();
        
        // Get priority distribution for chart - FILTER BY DEPARTMENT
        $priorityDistribution = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselor->id)
            ->when($departmentId, function($q) use ($departmentId) {
                return $q->where('programs.department_id', $departmentId);
            })
            ->select('cases.priority', DB::raw('count(*) as count'))
            ->groupBy('cases.priority')
            ->get();
        
        // Get caseload trend (last 6 weeks) - FILTER BY DEPARTMENT
        $caseloadTrend = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselor->id)
            ->where('cases.escalated_at', '>=', now()->subWeeks(6))
            ->when($departmentId, function($q) use ($departmentId) {
                return $q->where('programs.department_id', $departmentId);
            })
            ->select(DB::raw('DATE(cases.escalated_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(cases.escalated_at)'))
            ->orderBy('date', 'asc')
            ->get();
        
        // Process cases for display
        $processedCases = $this->processCases($cases);
        
        return view('counselor.dashboard', [
            'cases' => $processedCases,
            'openCases' => $stats['open'] ?? 0,
            'criticalCases' => $stats['critical'] ?? 0,
            'awaitingParent' => $stats['awaiting_parent'] ?? 0,
            'avgResponseTime' => $stats['avg_response_time'] ?? '2.3',
            'statusDistribution' => $statusDistribution,
            'priorityDistribution' => $priorityDistribution,
            'caseloadTrend' => $caseloadTrend,
            'counselor' => $counselor,
        ]);
    }
    
    public function show(Request $request, $caseId)
    {
        // Check if case belongs to counselor's department
        $departmentId = DepartmentIsolationMiddleware::getDepartmentId($request);
        
        // Get case details
        $query = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('users', 'cases.escalated_by', '=', 'users.id')
            ->leftJoin('counselors', 'cases.counselor_id', '=', 'counselors.id')
            ->leftJoin('departments', 'counselors.department_id', '=', 'departments.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.id', $caseId)
            ->select(
                'cases.*',
                'students.id as student_id',
                'students.first_name as student_first_name',
                'students.last_name as student_last_name',
                'students.student_number',
                'students.email as student_email',
                'users.name as escalated_by_name',
                'departments.name as department_name',
                'programs.code as program_code'
            );
        
        // Apply department filter if counselor has a department
        if ($departmentId) {
            $query->where('programs.department_id', $departmentId);
        }
        
        $case = $query->first();
        
        if (!$case) {
            return redirect()->route('counselor.dashboard')->with('error', 'Case not found or you do not have access to it.');
        }
        
        // Get student details
        $student = $this->getStudentDetails($case->student_id);
        
        // Get session history
        $sessions = DB::table('case_sessions')
            ->where('case_id', $caseId)
            ->orderBy('session_date', 'desc')
            ->get();
        
        // Get parent information
        $parents = DB::table('parents')
            ->where('student_id', $case->student_id)
            ->get();
        
        // Get risk history for effectiveness tracking
        $riskHistory = DB::table('risk_scores')
            ->where('student_id', $case->student_id)
            ->where('school_year', '2024-2025')
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
            ->get();
        
        // Calculate improvement
        $improvement = $this->calculateImprovement($riskHistory, $case->risk_score_at_escalation);
        
        // Get next period
        $nextPeriod = $this->getNextPeriod();
        
        // Get escalation notes
        $escalation = DB::table('escalations')
            ->where('student_id', $case->student_id)
            ->where('case_id', $caseId)
            ->first();
        
        return view('counselor.case', [
            'case' => $case,
            'student' => $student,
            'parents' => $parents,
            'sessions' => $sessions,
            'riskHistory' => $riskHistory,
            'improvement' => $improvement,
            'nextPeriod' => $nextPeriod,
            'escalationNotes' => $escalation->notes ?? null,
            'currentRisk' => $riskHistory->last()->risk_level ?? 'N/A',
        ]);
    }
    
    public function storeSession(Request $request, $caseId)
{
    $request->validate([
        'session_date' => 'required|date',
        'session_type' => 'required|in:In-person,Phone,Virtual,Parent Meeting',
        'notes' => 'required|string|min:5',
        'action_taken' => 'nullable|string',
        'follow_up_date' => 'nullable|date|after:session_date',
        'status_after_session' => 'required|in:New,In Progress,Awaiting Parent,Awaiting Student,Referred,Resolved,Closed,Reopened',
    ]);

    // Get case to get student_id
    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    // Insert session
    DB::table('case_sessions')->insert([
        'case_id' => $caseId,
        'session_date' => $request->session_date,
        'session_type' => $request->session_type,
        'notes' => $request->notes,
        'action_taken' => $request->action_taken,
        'follow_up_date' => $request->follow_up_date,
        'status_after_session' => $request->status_after_session,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Update case status
    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'status' => $request->status_after_session,
            'updated_at' => now(),
        ]);

    // ============================================================
    // CREATE STUDENT ALERT - NEW SESSION LOGGED
    // ============================================================
    $this->createStudentAlert(
        $case->student_id,
        'counselor_update',
        'info',
        'Your guidance counselor has logged a new session: ' . $request->session_type . ' on ' . date('M d, Y', strtotime($request->session_date)),
        $request->grading_period ?? 'Midterm'
    );

    // Also create a more specific alert based on the action
    if (!empty($request->action_taken)) {
        $this->createStudentAlert(
            $case->student_id,
            'counselor_action',
            'info',
            'Action taken: ' . $request->action_taken,
            $request->grading_period ?? 'Midterm'
        );
    }

    return redirect()->back()->with('success', 'Session logged successfully.');
}
    
    /**
     * Mark a case as resolved.
     * FIXED: Updates existing case instead of creating a new one.
     */
    public function resolve(Request $request, $caseId)
{
    $request->validate([
        'resolved_reason' => 'required|string|min:10',
    ]);

    // Get case to get student_id
    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    // Check if case is already resolved or closed
    if (in_array($case->status, ['Resolved', 'Closed'])) {
        return redirect()->back()->with('warning', 'This case is already resolved or closed.');
    }

    // UPDATE the existing case
    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'status' => 'Resolved',
            'resolved_at' => now(),
            'resolved_reason' => $request->resolved_reason,
            'updated_at' => now(),
        ]);

    // Log a session note for the resolution
    DB::table('case_sessions')->insert([
        'case_id' => $caseId,
        'session_date' => now(),
        'session_type' => 'Virtual',
        'notes' => 'Case resolved. Reason: ' . $request->resolved_reason,
        'action_taken' => 'Case marked as resolved',
        'status_after_session' => 'Resolved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // ============================================================
    // CREATE STUDENT ALERT - CASE RESOLVED
    // ============================================================
    $this->createStudentAlert(
        $case->student_id,
        'case_resolved',
        'success',
        '🎉 Your case has been resolved! Reason: ' . $request->resolved_reason,
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->back()->with('success', 'Case marked as resolved successfully.');
}
    
    /**
     * Reopen a resolved or closed case.
     * FIXED: Properly closes old case and creates a new reopened case.
     */
    public function reopen(Request $request, $caseId)
{
    $request->validate([
        'reopen_reason' => 'required|string|min:10',
    ]);

    // Get existing case
    $existingCase = DB::table('cases')->where('id', $caseId)->first();
    if (!$existingCase) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    // Check if case is already reopened
    if ($existingCase->status === 'Reopened') {
        return redirect()->back()->with('warning', 'This case is already reopened.');
    }

    // Check if case is resolved or closed before reopening
    if (!in_array($existingCase->status, ['Resolved', 'Closed'])) {
        return redirect()->back()->with('warning', 'Only resolved or closed cases can be reopened.');
    }

    // Mark current case as Closed
    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'status' => 'Closed',
            'updated_at' => now(),
        ]);

    // Create NEW case as reopened
    $newCaseId = DB::table('cases')->insertGetId([
        'student_id' => $existingCase->student_id,
        'counselor_id' => $existingCase->counselor_id,
        'escalated_by' => $existingCase->escalated_by,
        'escalated_at' => now(),
        'school_year' => $existingCase->school_year,
        'semester' => $existingCase->semester,
        'priority' => $this->recalculatePriority($existingCase->student_id),
        'status' => 'Reopened',
        'risk_level_at_escalation' => $this->getCurrentRiskLevel($existingCase->student_id),
        'risk_score_at_escalation' => $this->getCurrentRiskScore($existingCase->student_id),
        'reopened_from_case_id' => $caseId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Log the reopening session
    DB::table('case_sessions')->insert([
        'case_id' => $newCaseId,
        'session_date' => now(),
        'session_type' => 'Virtual',
        'notes' => 'Case reopened. Reason: ' . $request->reopen_reason,
        'action_taken' => 'Case reopened for further intervention',
        'status_after_session' => 'Reopened',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // ============================================================
    // CREATE STUDENT ALERT - CASE REOPENED
    // ============================================================
    $this->createStudentAlert(
        $existingCase->student_id,
        'case_reopened',
        'warning',
        'Your case has been reopened. Reason: ' . $request->reopen_reason,
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->route('counselor.case', $newCaseId)->with('success', 'Case reopened successfully.');
}
    
    public function updatePriority(Request $request, $caseId)
{
    $request->validate([
        'priority' => 'required|in:Critical,High,Medium,Low',
    ]);

    // Get case to get student_id
    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    $oldPriority = $case->priority;
    $newPriority = $request->priority;

    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'priority' => $newPriority,
            'updated_at' => now(),
        ]);

    // ============================================================
    // CREATE STUDENT ALERT - PRIORITY CHANGED
    // ============================================================
    $severity = match($newPriority) {
        'Critical' => 'critical',
        'High' => 'high',
        'Medium' => 'medium',
        'Low' => 'low'
    };

    $this->createStudentAlert(
        $case->student_id,
        'priority_update',
        $severity,
        'Your case priority has been updated from "' . $oldPriority . '" to "' . $newPriority . '".',
        $request->grading_period ?? 'Midterm'
    );

    return redirect()->back()->with('success', 'Priority updated successfully.');
}
    
    public function updateStatus(Request $request, $caseId)
{
    $request->validate([
        'status' => 'required|in:New,In Progress,Awaiting Parent,Awaiting Student,Referred,Resolved,Closed,Reopened',
    ]);

    // Get case to get student_id
    $case = DB::table('cases')->where('id', $caseId)->first();
    if (!$case) {
        return redirect()->back()->with('error', 'Case not found.');
    }

    $oldStatus = $case->status;
    $newStatus = $request->status;

    DB::table('cases')
        ->where('id', $caseId)
        ->update([
            'status' => $newStatus,
            'updated_at' => now(),
        ]);

    // ============================================================
    // CREATE STUDENT ALERT - STATUS CHANGED
    // ============================================================
    $severity = match($newStatus) {
        'Resolved' => 'success',
        'Reopened' => 'warning',
        'Awaiting Parent', 'Awaiting Student' => 'medium',
        default => 'info'
    };

    $this->createStudentAlert(
        $case->student_id,
        'status_update',
        $severity,
        'Your case status has been updated from "' . $oldStatus . '" to "' . $newStatus . '".',
        $request->grading_period ?? 'Midterm'
    );

    // Special alert for Resolved status
    if ($newStatus === 'Resolved') {
        $this->createStudentAlert(
            $case->student_id,
            'case_resolved',
            'success',
            '🎉 Your case has been marked as Resolved! Please continue to monitor your progress.',
            $request->grading_period ?? 'Midterm'
        );
    }

    // Special alert for Reopened status
    if ($newStatus === 'Reopened') {
        $this->createStudentAlert(
            $case->student_id,
            'case_reopened',
            'warning',
            'Your case has been reopened. Please check with your guidance counselor for next steps.',
            $request->grading_period ?? 'Midterm'
        );
    }

    return redirect()->back()->with('success', 'Status updated successfully.');
}
    
    protected function getStatistics($counselorId, $departmentId = null)
    {
        $stats = [];
        
        // Build base query with department filter
        $baseQuery = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselorId);
        
        // Apply department filter if provided
        if ($departmentId) {
            $baseQuery->where('programs.department_id', $departmentId);
        }
        
        // Open cases
        $stats['open'] = (clone $baseQuery)
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->count();
        
        // Critical cases
        $stats['critical'] = (clone $baseQuery)
            ->where('cases.priority', 'Critical')
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->count();
        
        // Awaiting parent
        $stats['awaiting_parent'] = (clone $baseQuery)
            ->where('cases.status', 'Awaiting Parent')
            ->count();
        
        // Average response time (placeholder)
        $stats['avg_response_time'] = '2.3';
        
        return $stats;
    }
    
    protected function processCases($cases)
    {
        $processed = [];
        
        foreach ($cases as $case) {
            // Get last session date
            $lastSession = DB::table('case_sessions')
                ->where('case_id', $case->id)
                ->orderBy('session_date', 'desc')
                ->first();
            
            // Get follow-up date
            $followUp = DB::table('case_sessions')
                ->where('case_id', $case->id)
                ->where('follow_up_date', '>=', now())
                ->orderBy('follow_up_date', 'asc')
                ->first();
            
            // Calculate improvement
            $improvement = $this->calculateCaseImprovement($case->student_id);
            
            $processed[] = (object) [
                'id' => $case->id,
                'student_name' => $case->student_first_name . ' ' . $case->student_last_name,
                'program' => $case->program_code ?? 'N/A',
                'priority' => $case->priority,
                'status' => $case->status,
                'risk_level' => $case->risk_level_at_escalation,
                'last_session_date' => $lastSession ? date('M d, Y', strtotime($lastSession->session_date)) : 'No sessions',
                'follow_up_date' => $followUp ? date('M d, Y', strtotime($followUp->follow_up_date)) : null,
                'improvement' => $improvement,
                'escalated_at' => $case->escalated_at,
            ];
        }
        
        return collect($processed);
    }
    
    protected function getStudentDetails($studentId)
    {
        $student = DB::table('students')
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
        
        // Get parent info
        $parent = DB::table('parents')
            ->where('student_id', $studentId)
            ->where('is_primary_contact', true)
            ->first();
        
        if ($parent) {
            $student->parent_name = $parent->full_name;
            $student->parent_contact = $parent->contact_number;
            $student->parent_email = $parent->email;
        }
        
        return $student;
    }
    
    protected function calculateImprovement($riskHistory, $initialScore)
    {
        if ($riskHistory->isEmpty()) {
            return null;
        }
        
        $latest = $riskHistory->last();
        $latestScore = $latest->risk_score ?? 0;
        
        return $initialScore - $latestScore;
    }
    
    protected function calculateCaseImprovement($studentId)
    {
        $scores = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
            ->get();
        
        if ($scores->count() < 2) {
            return null;
        }
        
        $first = $scores->first();
        $last = $scores->last();
        
        return $first->risk_score - $last->risk_score;
    }
    
    protected function getNextPeriod()
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $current = 'Midterm';
        $index = array_search($current, $periods);
        return $index < count($periods) - 1 ? $periods[$index + 1] : 'Finals';
    }
    
    protected function getCurrentRiskLevel($studentId)
    {
        $risk = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->first();
        return $risk->risk_level ?? 'Low';
    }
    
    protected function getCurrentRiskScore($studentId)
    {
        $risk = DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->first();
        return $risk->risk_score ?? 0;
    }
    
    protected function recalculatePriority($studentId)
    {
        $riskLevel = $this->getCurrentRiskLevel($studentId);
        if ($riskLevel === 'High') return 'High';
        if ($riskLevel === 'Moderate') return 'Medium';
        return 'Low';
    }

    // ============================================================
    // NEW METHODS: Student Alert Creation
    // ============================================================

    /**
 * Create an alert (flag) for a student.
 */
/**
     * Create an alert (flag) for a student.
     */
    protected function createStudentAlert(int $studentId, string $flagType, string $severity, ?string $message = null, ?string $period = null): void
    {
        $schoolYear = '2024-2025';
        $gradingPeriod = $period ?? 'Midterm';
        
        // Map flag types to valid ENUM values
        $validFlagTypes = [
            'counselor_update' => 'counselor_update',
            'counselor_action' => 'counselor_action',
            'status_update' => 'status_update',
            'priority_update' => 'priority_update',
            'case_resolved' => 'case_resolved',
            'case_reopened' => 'case_reopened',
        ];
        
        // Map severity to valid ENUM values
        $validSeverities = [
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            'success' => 'low',
            'info' => 'low',
            'warning' => 'medium',
            'error' => 'high',
        ];
        
        $finalFlagType = $validFlagTypes[$flagType] ?? 'counselor_update';
        $finalSeverity = $validSeverities[$severity] ?? 'medium';
        
        // Log the alert creation attempt
        Log::info('Creating student alert', [
            'student_id' => $studentId,
            'flag_type' => $finalFlagType,
            'severity' => $finalSeverity,
            'message' => $message,
            'period' => $gradingPeriod,
        ]);
        
        try {
            // Check if a similar alert already exists for this period
            $exists = DB::table('flags')
                ->where('student_id', $studentId)
                ->where('flag_type', $finalFlagType)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->exists();
            
            if ($exists) {
                // Update existing flag instead of creating duplicate
                DB::table('flags')
                    ->where('student_id', $studentId)
                    ->where('flag_type', $finalFlagType)
                    ->where('grading_period', $gradingPeriod)
                    ->where('school_year', $schoolYear)
                    ->update([
                        'is_acknowledged' => false,
                        'updated_at' => now(),
                    ]);
                
                Log::info('Updated existing alert', ['student_id' => $studentId, 'flag_type' => $finalFlagType]);
                return;
            }
            
            // Create new flag
            DB::table('flags')->insert([
                'student_id' => $studentId,
                'grading_period' => $gradingPeriod,
                'school_year' => $schoolYear,
                'semester' => '1st',
                'flag_type' => $finalFlagType,
                'severity' => $finalSeverity,
                'is_acknowledged' => false,
                'consecutive_periods_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info('Created new alert', ['student_id' => $studentId, 'flag_type' => $finalFlagType]);
            
            // Also log to audit
            $this->logActivity('STUDENT_ALERT_CREATED', "Alert created for student $studentId: $flagType ($severity)");
            
        } catch (\Exception $e) {
            Log::error('Failed to create student alert', [
                'student_id' => $studentId,
                'flag_type' => $finalFlagType,
                'severity' => $finalSeverity,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log activity to audit logs.
     */
    protected function logActivity(string $action, string $details): void
    { 
        DB::table('audit_logs')->insert([
            
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Counselor',
            'new_values' => json_encode(['details' => $details]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
