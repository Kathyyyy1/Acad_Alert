<?php

namespace App\Http\Controllers\MasterTeacher;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EscalationController extends Controller
{
    /**
     * Bulk escalate selected students to counselor.
     */
    public function bulkEscalate(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'notes' => 'nullable|string|max:1000',
            'block_id' => 'required|integer|exists:blocks,id',
            'period' => 'required|string|in:Prelim,Midterm,Semifinal,Finals',
        ]);

        $user = Auth::user();
        $studentIds = $request->student_ids;
        $notes = $request->notes;
        $blockId = $request->block_id;
        $gradingPeriod = $request->period;
        $schoolYear = '2024-2025';

        // Verify block belongs to this Master Teacher's department
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        // Get Master Teacher record
        $masterTeacher = DB::table('master_teachers')
            ->where('user_id', $user->id)
            ->first();

        if (!$masterTeacher) {
            return response()->json([
                'success' => false,
                'message' => 'Master Teacher record not found.',
            ], 404);
        }

        // Get counselor for this department
        $counselor = DB::table('counselors')
            ->where('department_id', $masterTeacher->department_id)
            ->first();

        if (!$counselor) {
            return response()->json([
                'success' => false,
                'message' => 'No counselor assigned to this department.',
            ], 404);
        }

        $results = [];
        $successCount = 0;
        $alreadyEscalatedCount = 0;

        foreach ($studentIds as $studentId) {
            // Check if student already has an open case
            $existingCase = DB::table('cases')
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->first();

            if ($existingCase) {
                $alreadyEscalatedCount++;
                $results[] = [
                    'student_id' => $studentId,
                    'status' => 'already_escalated',
                    'case_id' => $existingCase->id,
                    'message' => 'Student already has an open case.',
                ];
                continue;
            }

            // Get current risk score and level
            $riskScore = DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->first();

            // Determine priority based on risk level and consecutive flags
            $priority = 'Medium';
            if ($riskScore) {
                if ($riskScore->risk_level === 'High') {
                    $consecutiveCount = DB::table('flags')
                        ->where('student_id', $studentId)
                        ->where('flag_type', 'consecutive_high_risk')
                        ->where('grading_period', $gradingPeriod)
                        ->where('school_year', $schoolYear)
                        ->value('consecutive_periods_count') ?? 0;

                    if ($consecutiveCount >= 2) {
                        $priority = 'Critical';
                    } else {
                        $priority = 'High';
                    }
                } elseif ($riskScore->risk_level === 'Moderate') {
                    $previousPeriod = $this->getPreviousPeriod($gradingPeriod);
                    $previousRisk = DB::table('risk_scores')
                        ->where('student_id', $studentId)
                        ->where('grading_period', $previousPeriod)
                        ->where('school_year', $schoolYear)
                        ->first();

                    if ($previousRisk && $previousRisk->risk_level === 'Moderate') {
                        $priority = 'Medium';
                    } else {
                        $priority = 'Low';
                    }
                } else {
                    $priority = 'Low';
                }
            }

            // Create case
            $caseId = DB::table('cases')->insertGetId([
                'student_id' => $studentId,
                'counselor_id' => $counselor->id,
                'escalated_by' => $user->id,
                'escalated_at' => now(),
                'school_year' => $schoolYear,
                'semester' => '1st',
                'priority' => $priority,
                'status' => 'New',
                'risk_level_at_escalation' => $riskScore->risk_level ?? 'Low',
                'risk_score_at_escalation' => $riskScore->risk_score ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create escalation record with notes
            DB::table('escalations')->insert([
                'student_id' => $studentId,
                'escalated_by' => $user->id,
                'case_id' => $caseId,
                'school_year' => $schoolYear,
                'semester' => '1st',
                'grading_period' => $gradingPeriod,
                'notes' => $notes,
                'escalated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update flag to mark as escalated
            DB::table('flags')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->where('flag_type', 'high_risk')
                ->update([
                    'escalated_to_counselor_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('flags')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', $schoolYear)
                ->where('flag_type', 'consecutive_high_risk')
                ->update([
                    'escalated_to_counselor_at' => now(),
                    'updated_at' => now(),
                ]);

            $successCount++;
            $results[] = [
                'student_id' => $studentId,
                'status' => 'success',
                'case_id' => $caseId,
                'priority' => $priority,
                'message' => 'Student escalated successfully.',
            ];
        }

        return response()->json([
            'success' => true,
            'total' => count($studentIds),
            'escalated' => $successCount,
            'already_escalated' => $alreadyEscalatedCount,
            'results' => $results,
            'message' => "$successCount students escalated successfully. $alreadyEscalatedCount already had open cases.",
        ]);
    }

    /**
     * Check if students are already escalated.
     */
    public function checkEscalationStatus(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $studentIds = $request->student_ids;
        $statuses = [];

        foreach ($studentIds as $studentId) {
            $existingCase = DB::table('cases')
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->first();

            $statuses[$studentId] = [
                'is_escalated' => $existingCase !== null,
                'case_id' => $existingCase->id ?? null,
                'status' => $existingCase->status ?? null,
                'priority' => $existingCase->priority ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Reset escalation for a student (undo accidental escalation).
     */
    public function resetEscalation(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $studentId = $request->student_id;
        $reason = $request->reason ?? 'No reason provided';

        if (!DepartmentHelper::isStudentInDepartment($request, $studentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this student.',
            ], 403);
        }

        $case = DB::table('cases')
            ->where('student_id', $studentId)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'No open case found for this student.',
            ], 404);
        }

        if (!in_array($case->status, ['New', 'In Progress'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reset a case that is already ' . $case->status . '.',
            ], 400);
        }

        $escalation = DB::table('escalations')
            ->where('student_id', $studentId)
            ->where('case_id', $case->id)
            ->first();

        DB::beginTransaction();

        try {
            if ($escalation) {
                DB::table('escalations')
                    ->where('id', $escalation->id)
                    ->delete();
            }

            DB::table('case_sessions')
                ->where('case_id', $case->id)
                ->delete();

            DB::table('cases')
                ->where('id', $case->id)
                ->delete();

            DB::table('flags')
                ->where('student_id', $studentId)
                ->update([
                    'escalated_to_counselor_at' => null,
                    'updated_at' => now(),
                ]);

            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'action' => 'ESCALATION_RESET',
                'model_type' => 'App\\Models\\Student',
                'model_id' => $studentId,
                'new_values' => json_encode([
                    'reason' => $reason,
                    'case_id' => $case->id,
                    'reset_at' => now(),
                ]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Escalation reset successfully for student.',
                'student_id' => $studentId,
                'reason' => $reason,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset escalation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk reset escalations for multiple students.
     */
    public function bulkResetEscalation(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $studentIds = $request->student_ids;
        $reason = $request->reason ?? 'Bulk reset - no reason provided';

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($studentIds as $studentId) {
            try {
                // Create a new request using app() helper - avoids constructor issue
                $subRequest = app(Request::class);
                $subRequest->merge([
                    'student_id' => $studentId,
                    'reason' => $reason . ' (Bulk reset)',
                ]);
                
                $response = $this->resetEscalation($subRequest);
                $data = $response->getData();

                if ($data->success) {
                    $successCount++;
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'success',
                        'message' => $data->message,
                    ];
                } else {
                    $failedCount++;
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'failed',
                        'message' => $data->message ?? 'Unknown error',
                    ];
                }
            } catch (\Exception $e) {
                $failedCount++;
                $results[] = [
                    'student_id' => $studentId,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total' => count($studentIds),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'message' => "$successCount students reset successfully, $failedCount failed.",
        ]);
    }

    /**
     * Check if a student's escalation can be reset.
     */
    public function canResetEscalation(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
        ]);

        $studentId = $request->student_id;

        $case = DB::table('cases')
            ->where('student_id', $studentId)
            ->whereNotIn('status', ['Resolved', 'Closed'])
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'can_reset' => false,
                'message' => 'No open case found.',
            ]);
        }

        $canReset = in_array($case->status, ['New', 'In Progress']);

        return response()->json([
            'success' => true,
            'can_reset' => $canReset,
            'case_id' => $case->id,
            'status' => $case->status,
            'message' => $canReset 
                ? 'Escalation can be reset.' 
                : 'Case is already ' . $case->status . '. Cannot reset.',
        ]);
    }

    /**
     * Get all escalated students in a block.
     */
    public function getEscalatedStudentsInBlock(Request $request, int $blockId)
    {
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $students = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->where('students.block_id', $blockId)
            ->whereNotIn('cases.status', ['Resolved', 'Closed'])
            ->whereIn('cases.status', ['New', 'In Progress'])
            ->pluck('students.id')
            ->toArray();

        return response()->json([
            'success' => true,
            'students' => $students,
            'count' => count($students),
        ]);
    }

    /**
     * Get previous grading period.
     */
    protected function getPreviousPeriod(string $current): string
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : 'Prelim';
    }
}