<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherChartController extends Controller
{
    /**
     * Get risk by program for department dashboard.
     */
    public function riskByProgram(Request $request)
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

            // Get department ID from the user's master_teacher record
            $masterTeacher = DB::table('master_teachers')
                ->where('user_id', $user->id)
                ->first();

            if (!$masterTeacher) {
                Log::error('Master Teacher record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Master Teacher record not found.',
                ], 404);
            }

            $departmentId = $masterTeacher->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Master Teacher: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Master Teacher.',
                ], 404);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            Log::info('TeacherChartController: Fetching risk by program for department: ' . $departmentId);

            $data = DB::table('programs')
                ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
                ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
                ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
                ->leftJoin('risk_scores', function($join) use ($period, $schoolYear) {
                    $join->on('students.id', '=', 'risk_scores.student_id')
                         ->where('risk_scores.grading_period', $period)
                         ->where('risk_scores.school_year', $schoolYear);
                })
                ->where('programs.department_id', $departmentId)
                ->select(
                    'programs.code as program',
                    DB::raw('COUNT(DISTINCT students.id) as total'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
                )
                ->groupBy('programs.code')
                ->orderBy('programs.code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'department_id' => $departmentId,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('TeacherChartController@riskByProgram: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk by program data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get department risk trend for line chart.
     */
    public function departmentRiskTrend(Request $request)
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

            // Get department ID from the user's master_teacher record
            $masterTeacher = DB::table('master_teachers')
                ->where('user_id', $user->id)
                ->first();

            if (!$masterTeacher) {
                Log::error('Master Teacher record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Master Teacher record not found.',
                ], 404);
            }

            $departmentId = $masterTeacher->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Master Teacher: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Master Teacher.',
                ], 404);
            }

            $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
            $schoolYear = $request->input('school_year', '2024-2025');
            $trend = [];

            Log::info('TeacherChartController: Fetching department risk trend for department: ' . $departmentId);

            foreach ($periods as $period) {
                $high = DB::table('risk_scores')
                    ->join('students', 'risk_scores.student_id', '=', 'students.id')
                    ->join('blocks', 'students.block_id', '=', 'blocks.id')
                    ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                    ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                    ->where('programs.department_id', $departmentId)
                    ->where('risk_scores.grading_period', $period)
                    ->where('risk_scores.school_year', $schoolYear)
                    ->where('risk_scores.risk_level', 'High')
                    ->count();

                $total = DB::table('risk_scores')
                    ->join('students', 'risk_scores.student_id', '=', 'students.id')
                    ->join('blocks', 'students.block_id', '=', 'blocks.id')
                    ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                    ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                    ->where('programs.department_id', $departmentId)
                    ->where('risk_scores.grading_period', $period)
                    ->where('risk_scores.school_year', $schoolYear)
                    ->count();

                $percentage = $total > 0 ? round(($high / $total) * 100, 1) : 0;

                $trend[] = [
                    'period' => $period,
                    'high_risk_count' => $high,
                    'total_students' => $total,
                    'high_risk_percentage' => $percentage,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $periods,
                    'datasets' => [[
                        'label' => 'High Risk %',
                        'data' => array_column($trend, 'high_risk_percentage'),
                        'borderColor' => '#dc3545',
                        'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                        'pointBackgroundColor' => '#dc3545',
                    ]]
                ],
                'department_id' => $departmentId,
            ]);
        } catch (\Exception $e) {
            Log::error('TeacherChartController@departmentRiskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch department risk trend: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get block risk distribution for doughnut chart.
     */
    public function blockRiskDistribution(Request $request, int $blockId)
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

            // Get department ID from the user's master_teacher record
            $masterTeacher = DB::table('master_teachers')
                ->where('user_id', $user->id)
                ->first();

            if (!$masterTeacher) {
                Log::error('Master Teacher record not found for user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Master Teacher record not found.',
                ], 404);
            }

            $departmentId = $masterTeacher->department_id;
            
            if (!$departmentId) {
                Log::error('Department ID not found for Master Teacher: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Department not assigned to this Master Teacher.',
                ], 404);
            }

            // Verify block belongs to this Master Teacher's department
            $block = DB::table('blocks')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('blocks.id', $blockId)
                ->where('programs.department_id', $departmentId)
                ->first();

            if (!$block) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this block.',
                ], 403);
            }

            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $students = DB::table('students')
                ->where('block_id', $blockId)
                ->where('status', 'Active')
                ->pluck('id')
                ->toArray();

            if (empty($students)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'labels' => ['Low Risk', 'Moderate Risk', 'High Risk'],
                        'datasets' => [[
                            'data' => [0, 0, 0],
                            'backgroundColor' => ['#28a745', '#ffc107', '#dc3545'],
                            'borderWidth' => 2,
                            'borderColor' => '#fff',
                        ]]
                    ],
                    'message' => 'No students in this block.',
                ]);
            }

            $low = DB::table('risk_scores')
                ->whereIn('student_id', $students)
                ->where('risk_level', 'Low')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $moderate = DB::table('risk_scores')
                ->whereIn('student_id', $students)
                ->where('risk_level', 'Moderate')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $high = DB::table('risk_scores')
                ->whereIn('student_id', $students)
                ->where('risk_level', 'High')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => ['Low Risk', 'Moderate Risk', 'High Risk'],
                    'datasets' => [[
                        'data' => [$low, $moderate, $high],
                        'backgroundColor' => ['#28a745', '#ffc107', '#dc3545'],
                        'borderWidth' => 2,
                        'borderColor' => '#fff',
                    ]]
                ],
                'block_id' => $blockId,
                'period' => $period,
            ]);
        } catch (\Exception $e) {
            Log::error('TeacherChartController@blockRiskDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch block risk distribution: ' . $e->getMessage(),
            ], 500);
        }
    }
}