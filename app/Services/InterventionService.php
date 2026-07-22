<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterventionService
{
    protected $aiStudio;
    protected $fallback;
    protected $progress = [];

    public function __construct()
    {
        $this->aiStudio = new AIStudioApiService();
        $this->fallback = new FallbackRiskScoringService();
    }

    /**
     * Generate recommendations for multiple students with progress tracking.
     */
    public function generateForMultiple(array $studentIds, string $gradingPeriod = 'Midterm'): array
    {
        $results = [];
        $successCount = 0;
        $failedCount = 0;
        $total = count($studentIds);
        $progress = [];

        foreach ($studentIds as $index => $studentId) {
            // Update progress
            $current = $index + 1;
            $percentage = round(($current / $total) * 100);
            
            $progress[] = [
                'student_id' => $studentId,
                'status' => 'processing',
                'current' => $current,
                'total' => $total,
                'percentage' => $percentage,
            ];

            $result = $this->generateForStudent($studentId, $gradingPeriod);
            $results[$studentId] = $result;
            
            if ($result['success']) {
                $successCount++;
                $progress[$index]['status'] = 'success';
            } else {
                $failedCount++;
                $progress[$index]['status'] = 'failed';
                $progress[$index]['error'] = $result['error'] ?? 'Unknown error';
            }
        }

        return [
            'total' => $total,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
            'progress' => $progress,
        ];
    }

    /**
     * Generate recommendations for all students in a block with progress tracking.
     */
    public function generateForBlock(int $blockId, string $gradingPeriod = 'Midterm'): array
    {
        $students = DB::table('students')
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->pluck('id')
            ->toArray();

        if (empty($students)) {
            return [
                'success' => false,
                'message' => 'No students found in this block.',
            ];
        }

        return $this->generateForMultiple($students, $gradingPeriod);
    }

    /**
     * Generate recommendations for a single student.
     */
    public function generateForStudent(int $studentId, string $gradingPeriod = 'Midterm'): array
    {
        try {
            // Get student data
            $student = DB::table('students')->where('id', $studentId)->first();
            if (!$student) {
                return ['success' => false, 'error' => 'Student not found.'];
            }

            // Get student details
            $studentInfo = $this->getStudentInfo($studentId);
            
            // Get current risk score
            $riskScore = DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', '2024-2025')
                ->first();

            if (!$riskScore) {
                return [
                    'success' => false,
                    'error' => 'No risk score found for this student. Please run risk scoring first.'
                ];
            }

            // Delete existing recommendations
            $this->deleteExistingRecommendations($studentId);

            // Prepare data for AI
            $riskFactors = json_decode($riskScore->risk_factors, true) ?? [];
            $avgGrade = DB::table('grades')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', '2024-2025')
                ->avg('numerical_grade') ?? 0;

            $attendanceRate = DB::table('attendance_summaries')
                ->where('student_id', $studentId)
                ->where('grading_period', $gradingPeriod)
                ->where('school_year', '2024-2025')
                ->avg('attendance_rate') ?? 0;

            $studentData = [
                'id' => $studentId,
                'name' => $student->first_name . ' ' . $student->last_name,
                'program' => $studentInfo->program_code ?? 'N/A',
                'risk_level' => $riskScore->risk_level,
                'risk_factors' => $riskFactors,
                'avg_grade' => round($avgGrade, 2),
                'attendance_rate' => round($attendanceRate, 2),
            ];

            // Get AI recommendations
            $recommendations = $this->aiStudio->generateInterventions($studentId, $studentData);

            // Save recommendations
            $this->saveRecommendations($studentId, $riskFactors, $recommendations);

            return [
                'success' => true,
                'message' => 'Recommendations generated successfully.',
                'recommendations' => $recommendations,
            ];

        } catch (\Exception $e) {
            Log::error('Intervention generation failed for student ' . $studentId, [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete existing recommendations for a student.
     */
    protected function deleteExistingRecommendations(int $studentId): void
    {
        // Get all recommendation IDs for this student
        $recommendationIds = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->pluck('id')
            ->toArray();

        if (empty($recommendationIds)) {
            return;
        }

        // Delete tracking entries first (foreign key constraint)
        DB::table('student_recommendation_tracking')
            ->whereIn('recommendation_id', $recommendationIds)
            ->delete();

        // Delete the recommendations
        DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->delete();
    }

    /**
     * Save recommendations to database.
     */
    protected function saveRecommendations(int $studentId, array $riskFactors, array $recommendations): void
    {
        DB::table('intervention_recommendations')->insert([
            'student_id' => $studentId,
            'risk_factors' => json_encode($riskFactors),
            'suggested_actions' => json_encode($recommendations),
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get recommendations for a student.
     */
    public function getStudentRecommendations(int $studentId): array
    {
        $recommendations = DB::table('intervention_recommendations')
            ->where('student_id', $studentId)
            ->orderBy('generated_at', 'desc')
            ->get();

        return $recommendations->toArray();
    }

    /**
     * Get tracking status for a student's recommendations.
     */
    public function getTrackingStatus(int $studentId): array
    {
        $tracking = DB::table('student_recommendation_tracking')
            ->join('intervention_recommendations', 'student_recommendation_tracking.recommendation_id', '=', 'intervention_recommendations.id')
            ->where('student_recommendation_tracking.student_id', $studentId)
            ->select(
                'student_recommendation_tracking.*',
                'intervention_recommendations.suggested_actions'
            )
            ->get();

        return $tracking->toArray();
    }

    /**
     * Mark a recommendation as completed.
     */
    public function markCompleted(int $studentId, int $recommendationId): array
    {
        $exists = DB::table('student_recommendation_tracking')
            ->where('student_id', $studentId)
            ->where('recommendation_id', $recommendationId)
            ->exists();

        if ($exists) {
            DB::table('student_recommendation_tracking')
                ->where('student_id', $studentId)
                ->where('recommendation_id', $recommendationId)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('student_recommendation_tracking')->insert([
                'student_id' => $studentId,
                'recommendation_id' => $recommendationId,
                'is_completed' => true,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Recommendation marked as completed.',
        ];
    }

    /**
     * Get student information.
     */
    protected function getStudentInfo(int $studentId)
    {
        return DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $studentId)
            ->select('students.*', 'programs.code as program_code')
            ->first();
    }
}