<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FallbackRiskScoringService
{

    public function calculateRiskScore(array $studentData): array
    {
        $gradeWeight = 0.6;
        $attendanceWeight = 0.4;
        
        $riskScore = round(
            ((100 - $studentData['avg_grade']) * $gradeWeight) +
            ((100 - $studentData['attendance_rate']) * $attendanceWeight)
        );
        
        // Ensure score is between 0-100
        $riskScore = max(0, min(100, $riskScore));
        
        return [
            'risk_score' => $riskScore,
            'risk_level' => $this->determineRiskLevel($riskScore),
            'risk_factors' => $this->generateRiskFactors($studentData),
            'explanation' => 'Score calculated using deterministic formula (60% grade, 40% attendance).',
        ];
    }

    /**
     * Determine risk level from score.
     */
    protected function determineRiskLevel(int $score): string
    {
        if ($score >= 71) return 'High';
        if ($score >= 41) return 'Moderate';
        return 'Low';
    }

    /**
     * Generate risk factors based on data.
     */
    protected function generateRiskFactors(array $data): array
    {
        $factors = [];
        
        if ($data['avg_grade'] < 75) {
            $factors[] = 'Low grades in major subjects';
        }
        if ($data['avg_grade'] < 70) {
            $factors[] = 'Very low grades - immediate attention needed';
        }
        if ($data['attendance_rate'] < 80) {
            $factors[] = 'Poor attendance record';
        }
        if ($data['attendance_rate'] < 70) {
            $factors[] = 'Critical attendance issues - risk of being dropped';
        }
        if ($data['failing_subjects'] > 1) {
            $factors[] = 'Failing multiple subjects';
        }
        if ($data['absences'] > 5) {
            $factors[] = 'High number of absences';
        }
        
        if (empty($factors)) {
            $factors[] = 'Academic performance needs monitoring';
        }
        
        return $factors;
    }

    /**
     * Generate intervention recommendations based on risk factors.
     */
    public function getInterventions(array $riskFactors): array
    {
        $recommendations = [];
        
        $hasLowGrades = false;
        $hasAttendance = false;
        $hasMultipleFailing = false;
        
        foreach ($riskFactors as $factor) {
            $factorLower = strtolower($factor);
            if (strpos($factorLower, 'grade') !== false) {
                $hasLowGrades = true;
            }
            if (strpos($factorLower, 'attendance') !== false) {
                $hasAttendance = true;
            }
            if (strpos($factorLower, 'failing multiple') !== false) {
                $hasMultipleFailing = true;
            }
        }
        
        if ($hasLowGrades) {
            $recommendations[] = [
                'action' => 'tutoring',
                'details' => 'Schedule one-on-one tutoring sessions for struggling subjects.',
                'priority' => 'high',
            ];
            $recommendations[] = [
                'action' => 'peer_tutoring',
                'details' => 'Recommend peer tutoring program for additional support.',
                'priority' => 'medium',
            ];
        }
        
        if ($hasAttendance) {
            $recommendations[] = [
                'action' => 'attendance_monitoring',
                'details' => 'Implement attendance monitoring and intervention plan.',
                'priority' => 'high',
            ];
        }
        
        if ($hasMultipleFailing) {
            $recommendations[] = [
                'action' => 'counseling',
                'details' => 'Schedule academic counseling to identify root causes.',
                'priority' => 'high',
            ];
            $recommendations[] = [
                'action' => 'parent_meeting',
                'details' => 'Schedule parent-teacher meeting to discuss progress.',
                'priority' => 'medium',
            ];
        }
        
        if (empty($recommendations)) {
            $recommendations[] = [
                'action' => 'academic_advising',
                'details' => 'Schedule academic advising session to discuss progress.',
                'priority' => 'medium',
            ];
            $recommendations[] = [
                'action' => 'study_skills',
                'details' => 'Recommend study skills workshop to improve learning strategies.',
                'priority' => 'low',
            ];
        }
        
        return $recommendations;
    }
}