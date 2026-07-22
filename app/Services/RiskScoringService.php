<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiskScoringService
{
    protected $aiStudio;
    protected $fallback;

    public function __construct()
    {
        $this->aiStudio = new AIStudioApiService();
        $this->fallback = new FallbackRiskScoringService();
    }

    /**
     * Process risk scoring for a block of students.
     */
    public function processBlock(int $blockId, string $gradingPeriod, string $schoolYear): array
    {
        $results = [];
        
        // Get all students in the block
        $students = DB::table('students')
            ->where('block_id', $blockId)
            ->where('status', 'Active')
            ->get();
        
        if ($students->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No students found in this block.',
                'processed' => 0,
                'students' => [],
            ];
        }

        $studentIds = $students->pluck('id')->toArray();
        
        // Get batch data for all students
        $grades = $this->getGrades($studentIds, $gradingPeriod, $schoolYear);
        $attendance = $this->getAttendance($studentIds, $gradingPeriod, $schoolYear);
        $previousRisks = $this->getPreviousRisks($studentIds, $gradingPeriod, $schoolYear);
        $failingSubjects = $this->getFailingSubjects($studentIds, $gradingPeriod, $schoolYear);
        
        $processedCount = 0;
        $failedCount = 0;
        $fallbackCount = 0;
        $studentResults = [];

        foreach ($students as $student) {
            $studentData = [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'student_number' => $student->student_number,
                'avg_grade' => $grades[$student->id] ?? 0,
                'attendance_rate' => $attendance[$student->id] ?? 0,
                'previous_risk' => $previousRisks[$student->id] ?? 'Low',
                'failing_subjects' => $failingSubjects[$student->id] ?? 0,
                'total_subjects' => 4,
                'absences' => 0,
                'lates' => 0,
                'program' => '',
                'risk_level' => '',
            ];
            
            // Try AI first
            $aiResult = $this->tryAIScoring($studentData);
            
            if ($aiResult['success']) {
                $studentResults[] = [
                    'student_id' => $student->id,
                    'risk_score' => $aiResult['risk_score'],
                    'risk_level' => $aiResult['risk_level'],
                    'risk_factors' => json_encode($aiResult['risk_factors']),
                    'scoring_method' => 'ai',
                    'explanation' => $aiResult['explanation'],
                ];
                $processedCount++;
            } else {
                // Use fallback
                $fallbackResult = $this->fallback->calculateRiskScore($studentData);
                $studentResults[] = [
                    'student_id' => $student->id,
                    'risk_score' => $fallbackResult['risk_score'],
                    'risk_level' => $fallbackResult['risk_level'],
                    'risk_factors' => json_encode($fallbackResult['risk_factors']),
                    'scoring_method' => 'fallback',
                    'explanation' => $fallbackResult['explanation'],
                ];
                $fallbackCount++;
                $processedCount++;
            }
        }

        // Save all risk scores
        $this->saveRiskScores($studentResults, $gradingPeriod, $schoolYear);

        return [
            'success' => true,
            'message' => 'Risk scoring completed.',
            'processed' => $processedCount,
            'failed' => $failedCount,
            'fallback_count' => $fallbackCount,
            'students' => $studentResults,
        ];
    }

    /**
     * Try AI scoring with retry.
     */
    protected function tryAIScoring(array $studentData): array
    {
        try {
            $result = $this->aiStudio->getRiskScore($studentData);
            return $result;
        } catch (\Exception $e) {
            Log::error('AI scoring failed for student ' . $studentData['id'], [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get grades for students.
     */
    protected function getGrades(array $studentIds, string $period, string $schoolYear): array
    {
        $grades = DB::table('grades')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->select('student_id', DB::raw('AVG(numerical_grade) as avg_grade'))
            ->groupBy('student_id')
            ->get();

        return $grades->pluck('avg_grade', 'student_id')->toArray();
    }

    /**
     * Get attendance for students.
     */
    protected function getAttendance(array $studentIds, string $period, string $schoolYear): array
    {
        $attendance = DB::table('attendance_summaries')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->select('student_id', DB::raw('AVG(attendance_rate) as avg_attendance'))
            ->groupBy('student_id')
            ->get();

        return $attendance->pluck('avg_attendance', 'student_id')->toArray();
    }

    /**
     * Get previous risk scores.
     */
    protected function getPreviousRisks(array $studentIds, string $currentPeriod, string $schoolYear): array
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $currentIndex = array_search($currentPeriod, $periods);
        $previousPeriod = $currentIndex > 0 ? $periods[$currentIndex - 1] : null;
        
        if (!$previousPeriod) {
            return [];
        }

        $risks = DB::table('risk_scores')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $previousPeriod)
            ->where('school_year', $schoolYear)
            ->select('student_id', 'risk_level')
            ->get();

        return $risks->pluck('risk_level', 'student_id')->toArray();
    }

    /**
     * Get failing subjects count.
     */
    protected function getFailingSubjects(array $studentIds, string $period, string $schoolYear): array
    {
        $grades = DB::table('grades')
            ->whereIn('student_id', $studentIds)
            ->where('grading_period', $period)
            ->where('school_year', $schoolYear)
            ->where('numerical_grade', '<', 75)
            ->select('student_id', DB::raw('COUNT(*) as failing_count'))
            ->groupBy('student_id')
            ->get();

        return $grades->pluck('failing_count', 'student_id')->toArray();
    }

    /**
     * Save risk scores to database.
     */
    protected function saveRiskScores(array $results, string $period, string $schoolYear): void
    {
        $now = now();
        
        foreach ($results as $result) {
            DB::table('risk_scores')->insert([
                'student_id' => $result['student_id'],
                'grading_period' => $period,
                'school_year' => $schoolYear,
                'semester' => '1st',
                'risk_score' => $result['risk_score'],
                'risk_level' => $result['risk_level'],
                'risk_factors' => $result['risk_factors'],
                'scoring_method' => $result['scoring_method'],
                'processing_status' => 'completed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Get risk scores for a student.
     */
    public function getStudentRiskHistory(int $studentId): array
    {
        return DB::table('risk_scores')
            ->where('student_id', $studentId)
            ->orderBy('grading_period')
            ->get()
            ->toArray();
    }

    /**
     * Get risk scores for a block.
     */
    public function getBlockRiskScores(int $blockId, string $period, string $schoolYear): array
    {
        return DB::table('risk_scores')
            ->join('students', 'risk_scores.student_id', '=', 'students.id')
            ->where('students.block_id', $blockId)
            ->where('risk_scores.grading_period', $period)
            ->where('risk_scores.school_year', $schoolYear)
            ->select('risk_scores.*', 'students.first_name', 'students.last_name', 'students.student_number')
            ->get()
            ->toArray();
    }
}