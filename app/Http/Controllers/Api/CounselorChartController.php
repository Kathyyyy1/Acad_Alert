<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CounselorChartController extends Controller
{
    /**
     * Get case priority distribution for doughnut chart.
     */
    public function priorityDistribution(Request $request)
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

            // Get counselor record
            $counselor = DB::table('counselors')->where('user_id', $user->id)->first();

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            // Get department filter from middleware
            $departmentId = $request->attributes->get('department_id');

            // Build query
            $query = DB::table('cases')
                ->join('students', 'cases.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('cases.counselor_id', $counselor->id)
                ->whereNotIn('cases.status', ['Resolved', 'Closed']);

            if ($departmentId) {
                $query->where('programs.department_id', $departmentId);
            }

            $distribution = $query->select('cases.priority', DB::raw('count(*) as count'))
                ->groupBy('cases.priority')
                ->get();

            $priorityData = [
                'Critical' => 0,
                'High' => 0,
                'Medium' => 0,
                'Low' => 0,
            ];

            foreach ($distribution as $item) {
                $priorityData[$item->priority] = $item->count;
            }

            $colors = [
                'Critical' => '#dc3545',
                'High' => '#ffc107',
                'Medium' => '#17a2b8',
                'Low' => '#6c757d',
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_keys($priorityData),
                    'datasets' => [[
                        'data' => array_values($priorityData),
                        'backgroundColor' => array_map(function($label) use ($colors) {
                            return $colors[$label] ?? '#6c757d';
                        }, array_keys($priorityData)),
                        'borderWidth' => 2,
                        'borderColor' => '#fff',
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@priorityDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch priority distribution: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get case status distribution for bar chart.
     */
    public function statusDistribution(Request $request)
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

            // Get counselor record
            $counselor = DB::table('counselors')->where('user_id', $user->id)->first();

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            // Get department filter from middleware
            $departmentId = $request->attributes->get('department_id');

            // Build query
            $query = DB::table('cases')
                ->join('students', 'cases.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('cases.counselor_id', $counselor->id);

            if ($departmentId) {
                $query->where('programs.department_id', $departmentId);
            }

            $distribution = $query->select('cases.status', DB::raw('count(*) as count'))
                ->groupBy('cases.status')
                ->get();

            $statuses = ['New', 'In Progress', 'Awaiting Parent', 'Awaiting Student', 'Referred', 'Resolved', 'Closed', 'Reopened'];
            $statusData = [];

            foreach ($statuses as $status) {
                $found = $distribution->firstWhere('status', $status);
                $statusData[$status] = $found ? $found->count : 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => array_keys($statusData),
                    'datasets' => [[
                        'label' => 'Cases',
                        'data' => array_values($statusData),
                        'backgroundColor' => '#4e73df',
                        'borderRadius' => 4,
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@statusDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status distribution: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get caseload trend for line chart.
     */
    public function caseloadTrend(Request $request)
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

            // Get counselor record
            $counselor = DB::table('counselors')->where('user_id', $user->id)->first();

            if (!$counselor) {
                Log::error('Counselor record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Counselor record not found.',
                ], 404);
            }

            // Get department filter from middleware
            $departmentId = $request->attributes->get('department_id');
            $weeks = (int) $request->input('weeks', 6);

            // Build query
            $query = DB::table('cases')
                ->join('students', 'cases.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('cases.counselor_id', $counselor->id)
                ->where('cases.escalated_at', '>=', now()->subWeeks($weeks));

            if ($departmentId) {
                $query->where('programs.department_id', $departmentId);
            }

            $trend = $query->select(
                    DB::raw('DATE(cases.escalated_at) as date'),
                    DB::raw('count(*) as count')
                )
                ->groupBy(DB::raw('DATE(cases.escalated_at)'))
                ->orderBy('date', 'asc')
                ->get();

            $dates = $trend->pluck('date')->toArray();
            $counts = $trend->pluck('count')->toArray();

            // Pad with empty dates if needed
            $allDates = [];
            $allCounts = [];
            $current = now()->subWeeks($weeks);
            $end = now();

            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $allDates[] = $dateStr;
                $index = array_search($dateStr, $dates);
                $allCounts[] = $index !== false ? $counts[$index] : 0;
                $current->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $allDates,
                    'datasets' => [[
                        'label' => 'New Cases',
                        'data' => $allCounts,
                        'borderColor' => '#4e73df',
                        'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => '#4e73df',
                    ]]
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@caseloadTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch caseload trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get student risk trend for line chart in case view.
     */
    public function studentRiskTrend(Request $request, int $studentId)
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

            $schoolYear = $request->input('school_year', '2024-2025');

            $riskHistory = DB::table('risk_scores')
                ->where('student_id', $studentId)
                ->where('school_year', $schoolYear)
                ->orderByRaw("FIELD(grading_period, 'Prelim', 'Midterm', 'Semifinal', 'Finals')")
                ->select('grading_period', 'risk_score', 'risk_level')
                ->get();

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
                'student_id' => $studentId,
            ]);
        } catch (\Exception $e) {
            Log::error('CounselorChartController@studentRiskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }
}