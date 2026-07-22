<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIStudioApiService
{
    protected $apiKey;
    protected $apiUrl;
    protected $timeout;
    protected $retryLimit;

    public function __construct()
    {
        $this->apiKey = config('services.aistudio.api_key');
        $this->apiUrl = config('services.aistudio.api_url');
        $this->timeout = config('services.aistudio.timeout', 30);
        $this->retryLimit = config('services.aistudio.retry_limit', 2);
    }

    /**
     * Call the AI Studio API for risk scoring.
     */
    public function getRiskScore(array $studentData): array
    {
        $prompt = $this->buildRiskPrompt($studentData);
        
        $response = $this->sendRequest($prompt);
        
        if ($response['success']) {
            return $this->parseRiskResponse($response['data'], $studentData);
        }
        
        // Return fallback data
        return $this->getFallbackRiskScore($studentData);
    }

    /**
     * Call the AI Studio API for intervention recommendations.
     */
    public function getInterventions(array $studentData, array $riskFactors): array
    {
        $prompt = $this->buildInterventionPrompt($studentData, $riskFactors);
        
        $response = $this->sendRequest($prompt);
        
        if ($response['success']) {
            return $this->parseInterventionResponse($response['data']);
        }
        
        return $this->getFallbackInterventions($riskFactors);
    }

    /**
     * Generate intervention recommendations for a single student.
     * This is a wrapper method for the InterventionService.
     */
    public function generateInterventions(int $studentId, array $studentData): array
    {
        // Build the prompt using student data
        $riskFactors = $studentData['risk_factors'] ?? [];
        $prompt = $this->buildInterventionPrompt($studentData, $riskFactors);
        
        $response = $this->sendRequest($prompt);
        
        if ($response['success']) {
            return $this->parseInterventionResponse($response['data']);
        }
        
        // Return fallback interventions
        return $this->getFallbackInterventions($riskFactors);
    }

    /**
     * Build the risk scoring prompt.
     */
    protected function buildRiskPrompt(array $data): string
    {
        return "You are an academic risk assessment system for Universidad de Dagupan. " .
               "Analyze this student data and return a JSON response only (no other text):\n\n" .
               "Student Data:\n" .
               "- Current average grade: {$data['avg_grade']}%\n" .
               "- Attendance rate: {$data['attendance_rate']}%\n" .
               "- Previous risk level: {$data['previous_risk']}\n" .
               "- Number of subjects below 75: {$data['failing_subjects']} out of {$data['total_subjects']}\n" .
               "- Number of absences: {$data['absences']}\n" .
               "- Number of lates: {$data['lates']}\n\n" .
               "Return JSON with this structure:\n" .
               "{\n" .
               '  "risk_score": integer (0-100),\n' .
               '  "risk_level": "Low" or "Moderate" or "High",\n' .
               '  "risk_factors": ["factor1", "factor2"],\n' .
               '  "explanation": "brief reason for the risk assessment"\n' .
               "}\n\n" .
               "Risk Level Guidelines:\n" .
               "- Low: 0-40\n" .
               "- Moderate: 41-70\n" .
               "- High: 71-100";
    }

    /**
     * Build the intervention prompt.
     * Updated with additional student data fields.
     */
    protected function buildInterventionPrompt(array $studentData, array $riskFactors): string
    {
        // Build the student data string with safe defaults
        $studentName = isset($studentData['name']) ? $studentData['name'] : 'Unknown';
        $studentProgram = isset($studentData['program']) ? $studentData['program'] : 'N/A';
        $studentRiskLevel = isset($studentData['risk_level']) ? $studentData['risk_level'] : 'Unknown';
        $riskFactorsString = !empty($riskFactors) ? implode(", ", $riskFactors) : 'None identified';
        $avgGrade = isset($studentData['avg_grade']) ? $studentData['avg_grade'] : 0;
        $attendanceRate = isset($studentData['attendance_rate']) ? $studentData['attendance_rate'] : 0;
        
        return "You are an academic intervention system for Universidad de Dagupan. " .
               "Based on these risk factors, suggest appropriate interventions.\n\n" .
               "Student: {$studentName}\n" .
               "Program: {$studentProgram}\n" .
               "Risk Level: {$studentRiskLevel}\n" .
               "Risk Factors: " . $riskFactorsString . "\n" .
               "Average Grade: {$avgGrade}%\n" .
               "Attendance Rate: {$attendanceRate}%\n\n" .
               "Return JSON with this structure:\n" .
               "{\n" .
               '  "recommendations": [\n' .
               '    {"action": "tutoring", "details": "description", "priority": "high/medium/low"},\n' .
               '    {"action": "counseling", "details": "description", "priority": "high/medium/low"},\n' .
               '    {"action": "parent_meeting", "details": "description", "priority": "high/medium/low"}\n' .
               "  ]\n" .
               "}\n\n" .
               "Available actions: tutoring, counseling, parent_meeting, academic_advising, " .
               "study_skills, attendance_monitoring, peer_tutoring, workshop, time_management, " .
               "stress_management, career_guidance, academic_coaching";
    }

    /**
     * Send request to AI Studio API.
     */
    protected function sendRequest(string $prompt, int $attempt = 1): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                return [
                    'success' => true,
                    'data' => $text,
                ];
            }

            Log::warning('AI Studio API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $this->retryLimit) {
                return $this->sendRequest($prompt, $attempt + 1);
            }

            return [
                'success' => false,
                'error' => 'API request failed after ' . $attempt . ' attempts',
            ];

        } catch (\Exception $e) {
            Log::error('AI Studio API exception', [
                'message' => $e->getMessage(),
                'attempt' => $attempt,
            ]);

            if ($attempt < $this->retryLimit) {
                return $this->sendRequest($prompt, $attempt + 1);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse the AI risk response.
     */
    protected function parseRiskResponse(string $response, array $studentData): array
    {
        // Extract JSON from response
        $json = $this->extractJson($response);
        
        if (!$json) {
            return $this->getFallbackRiskScore($studentData);
        }

        $data = json_decode($json, true);
        
        if (!$data || !isset($data['risk_score'])) {
            return $this->getFallbackRiskScore($studentData);
        }

        return [
            'success' => true,
            'risk_score' => (int) $data['risk_score'],
            'risk_level' => $data['risk_level'] ?? $this->determineRiskLevel($data['risk_score']),
            'risk_factors' => $data['risk_factors'] ?? [],
            'explanation' => $data['explanation'] ?? 'AI assessment complete.',
        ];
    }

    /**
     * Parse the AI intervention response.
     */
    protected function parseInterventionResponse(string $response): array
    {
        $json = $this->extractJson($response);
        
        if (!$json) {
            return $this->getFallbackInterventions([]);
        }

        $data = json_decode($json, true);
        
        if (!$data || !isset($data['recommendations'])) {
            return $this->getFallbackInterventions([]);
        }

        return $data['recommendations'];
    }

    /**
     * Extract JSON from AI response.
     */
    protected function extractJson(string $text): ?string
    {
        // Try to find JSON in the text
        preg_match('/\{[^{}]*\}/s', $text, $matches);
        
        if (empty($matches)) {
            // Try with nested braces
            preg_match('/\{.*\}/s', $text, $matches);
        }
        
        return $matches[0] ?? null;
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
     * Get fallback risk score (deterministic formula).
     */
    protected function getFallbackRiskScore(array $data): array
    {
        $gradeWeight = 0.6;
        $attendanceWeight = 0.4;
        
        $riskScore = round(
            ((100 - $data['avg_grade']) * $gradeWeight) +
            ((100 - $data['attendance_rate']) * $attendanceWeight)
        );
        
        // Ensure score is between 0-100
        $riskScore = max(0, min(100, $riskScore));
        
        return [
            'success' => false,
            'risk_score' => $riskScore,
            'risk_level' => $this->determineRiskLevel($riskScore),
            'risk_factors' => $this->generateFallbackFactors($data),
            'explanation' => 'Fallback scoring used (AI unavailable).',
            'method' => 'fallback',
        ];
    }

    /**
     * Generate fallback risk factors.
     */
    protected function generateFallbackFactors(array $data): array
    {
        $factors = [];
        
        if ($data['avg_grade'] < 75) {
            $factors[] = 'Low grades in major subjects';
        }
        if ($data['attendance_rate'] < 80) {
            $factors[] = 'Poor attendance record';
        }
        if ($data['attendance_rate'] < 70) {
            $factors[] = 'Critical attendance issues';
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
     * Get fallback interventions.
     */
    protected function getFallbackInterventions(array $riskFactors): array
    {
        $recommendations = [];
        
        $hasLowGrades = false;
        $hasAttendance = false;
        $hasMultipleFailing = false;
        
        foreach ($riskFactors as $factor) {
            $factorLower = strtolower($factor);
            if (strpos($factorLower, 'grade') !== false || strpos($factorLower, 'failing') !== false) {
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