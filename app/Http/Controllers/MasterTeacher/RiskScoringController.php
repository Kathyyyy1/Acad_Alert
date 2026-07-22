<?php

namespace App\Http\Controllers\MasterTeacher;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Services\RiskScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskScoringController extends Controller
{
    protected $riskService;

    public function __construct(RiskScoringService $riskService)
    {
        $this->riskService = $riskService;
    }

    /**
     * Run risk scoring for a block.
     */
    public function runScoring(Request $request, int $blockId)
    {
        // Verify block belongs to this Master Teacher's department
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $gradingPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');

        // Check if scoring already exists for this period
        $existing = DB::table('risk_scores')
            ->join('students', 'risk_scores.student_id', '=', 'students.id')
            ->where('students.block_id', $blockId)
            ->where('risk_scores.grading_period', $gradingPeriod)
            ->where('risk_scores.school_year', $schoolYear)
            ->count();

        if ($existing > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Risk scores already exist for this period. Please use the "Refresh" option if needed.',
                'existing' => true,
            ]);
        }

        // Process the block
        $result = $this->riskService->processBlock($blockId, $gradingPeriod, $schoolYear);

        // Generate flags for high risk students
        $this->generateFlags($result, $gradingPeriod, $schoolYear);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'processed' => $result['processed'] ?? 0,
            'fallback_count' => $result['fallback_count'] ?? 0,
            'students' => $result['students'] ?? [],
        ]);
    }

    /**
     * Get risk scoring status/progress.
     */
    public function getScoringStatus(Request $request, int $blockId)
    {
        $gradingPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');

        $total = DB::table('students')
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->count();

        $processed = DB::table('risk_scores')
            ->join('students', 'risk_scores.student_id', '=', 'students.id')
            ->where('students.block_id', $blockId)
            ->where('risk_scores.grading_period', $gradingPeriod)
            ->where('risk_scores.school_year', $schoolYear)
            ->count();

        return response()->json([
            'total' => $total,
            'processed' => $processed,
            'pending' => $total - $processed,
            'percentage' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
        ]);
    }

    /**
     * Delete existing risk scores for a block (refresh).
     */
    public function refreshScoring(Request $request, int $blockId)
    {
        // Verify block belongs to this Master Teacher's department
        if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this block.',
            ], 403);
        }

        $gradingPeriod = $request->input('period', 'Midterm');
        $schoolYear = $request->input('school_year', '2024-2025');

        // Delete existing risk scores for students in this block
        $deletedRiskScores = DB::table('risk_scores')
            ->whereIn('student_id', function($query) use ($blockId) {
                $query->select('id')
                      ->from('students')
                      ->where('block_id', $blockId);
            })
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();

        // Delete flags for this block
        $deletedFlags = DB::table('flags')
            ->whereIn('student_id', function($query) use ($blockId) {
                $query->select('id')
                      ->from('students')
                      ->where('block_id', $blockId);
            })
            ->where('grading_period', $gradingPeriod)
            ->where('school_year', $schoolYear)
            ->delete();

        // Also delete intervention recommendations for this block
        DB::table('intervention_recommendations')
            ->whereIn('student_id', function($query) use ($blockId) {
                $query->select('id')
                      ->from('students')
                      ->where('block_id', $blockId);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted $deletedRiskScores risk score records and $deletedFlags flags.",
            'deleted_risk_scores' => $deletedRiskScores,
            'deleted_flags' => $deletedFlags,
        ]);
    }

    /**
     * Generate flags for high risk students.
     */
    protected function generateFlags(array $result, string $period, string $schoolYear): void
    {
        if (!$result['success'] || empty($result['students'])) {
            return;
        }

        foreach ($result['students'] as $student) {
            if ($student['risk_level'] === 'High') {
                // Check if consecutive high risk
                $previousRisk = DB::table('risk_scores')
                    ->where('student_id', $student['student_id'])
                    ->where('grading_period', $this->getPreviousPeriod($period))
                    ->where('school_year', $schoolYear)
                    ->first();

                $consecutiveCount = 1;
                if ($previousRisk && $previousRisk->risk_level === 'High') {
                    $consecutiveCount = 2;
                    
                    // Check if already flagged for this period
                    $exists = DB::table('flags')
                        ->where('student_id', $student['student_id'])
                        ->where('grading_period', $period)
                        ->where('school_year', $schoolYear)
                        ->exists();

                    if (!$exists) {
                        DB::table('flags')->insert([
                            'student_id' => $student['student_id'],
                            'grading_period' => $period,
                            'school_year' => $schoolYear,
                            'semester' => '1st',
                            'flag_type' => 'consecutive_high_risk',
                            'severity' => 'critical',
                            'is_acknowledged' => false,
                            'consecutive_periods_count' => $consecutiveCount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    // Regular high risk flag
                    $exists = DB::table('flags')
                        ->where('student_id', $student['student_id'])
                        ->where('grading_period', $period)
                        ->where('school_year', $schoolYear)
                        ->where('flag_type', 'high_risk')
                        ->exists();

                    if (!$exists) {
                        DB::table('flags')->insert([
                            'student_id' => $student['student_id'],
                            'grading_period' => $period,
                            'school_year' => $schoolYear,
                            'semester' => '1st',
                            'flag_type' => 'high_risk',
                            'severity' => 'high',
                            'is_acknowledged' => false,
                            'consecutive_periods_count' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Get previous grading period.
     */
    protected function getPreviousPeriod(string $current): string
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $index = array_search($current, $periods);
        return $index > 0 ? $periods[$index - 1] : '';
    }
}