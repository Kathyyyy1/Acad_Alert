<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentChartController extends Controller
{
    /**
     * Get student risk trend for line chart.
     */
    public function riskTrend(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Get student record
            $student = DB::table('students')->where('email', $user->email)->first();

            if (!$student) {
                Log::error('Student record not found for user: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            $schoolYear = $request->input('school_year', '2024-2025');

            $riskHistory = DB::table('risk_scores')
                ->where('student_id', $student->id)
                ->where('school_year', $schoolYear)
                ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
                ->select('grading_period', 'risk_score', 'risk_level')
                ->get();

            if ($riskHistory->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['No Data'],
                        'datasets' => [[
                            'label' => 'Risk Score',
                            'data' => [0],
                            'borderColor' => '#6c757d',
                            'backgroundColor' => 'rgba(108, 117, 125, 0.1)',
                            'fill' => true,
                            'tension' => 0.3,
                            'pointBackgroundColor' => '#6c757d',
                            'pointRadius' => 6,
                            'pointHoverRadius' => 8,
                        ]]
                    ],
                    'message' => 'No risk data available. Please complete at least one grading period.',
                ]);
            }

            $periods = $riskHistory->pluck('grading_period')->toArray();
            $scores = $riskHistory->pluck('risk_score')->toArray();
            $levels = $riskHistory->pluck('risk_level')->toArray();

            $pointColors = array_map(function($level) {
                return $level === 'High' ? '#dc3545' : ($level === 'Moderate' ? '#ffc107' : '#28a745');
            }, $levels);

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $periods,
                    'datasets' => [[
                        'label' => 'Risk Score',
                        'data' => $scores,
                        'borderColor' => '#4e73df',
                        'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => $pointColors,
                        'pointRadius' => 6,
                        'pointHoverRadius' => 8,
                    ]]
                ],
                'student_id' => $student->id,
            ]);
        } catch (\Exception $e) {
            Log::error('StudentChartController@riskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student grades for horizontal bar chart.
     */
    public function grades(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            // Get student record
            $student = DB::table('students')->where('email', $user->email)->first();

            if (!$student) {
                Log::error('Student record not found for user: ' . $user->email);
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $grades = DB::table('grades')
                ->join('subjects', 'grades.subject_id', '=', 'subjects.id')
                ->where('grades.student_id', $student->id)
                ->where('grades.grading_period', $period)
                ->where('grades.school_year', $schoolYear)
                ->select(
                    'subjects.subject_name',
                    'grades.numerical_grade'
                )
                ->orderBy('grades.numerical_grade', 'asc')
                ->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['No Data'],
                        'datasets' => [[
                            'label' => 'Grade %',
                            'data' => [0],
                            'backgroundColor' => ['#6c757d'],
                            'borderRadius' => 4,
                        ]]
                    ],
                    'message' => 'No grades available for this period.',
                ]);
            }

            $subjectNames = $grades->pluck('subject_name')->toArray();
            $gradeValues = $grades->pluck('numerical_grade')->toArray();

            // Truncate long subject names for display
            $subjectNames = array_map(function($name) {
                return strlen($name) > 20 ? substr($name, 0, 20) . '...' : $name;
            }, $subjectNames);

            $colors = array_map(function($grade) {
                return $grade >= 80 ? '#28a745' : ($grade >= 75 ? '#ffc107' : '#dc3545');
            }, $gradeValues);

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $subjectNames,
                    'datasets' => [[
                        'label' => 'Grade %',
                        'data' => $gradeValues,
                        'backgroundColor' => $colors,
                        'borderRadius' => 4,
                    ]]
                ],
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('StudentChartController@grades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grades: ' . $e->getMessage(),
            ], 500);
        }
    }
}