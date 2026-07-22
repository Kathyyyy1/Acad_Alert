<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminChartController extends Controller
{
    /**
     * Get risk by department for horizontal bar chart.
     */
    public function riskByDepartment(Request $request)
    {
        try {
            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $data = DB::table('departments')
                ->leftJoin('programs', 'departments.id', '=', 'programs.department_id')
                ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
                ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
                ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
                ->leftJoin('risk_scores', function($join) use ($period, $schoolYear) {
                    $join->on('students.id', '=', 'risk_scores.student_id')
                         ->where('risk_scores.grading_period', $period)
                         ->where('risk_scores.school_year', $schoolYear);
                })
                ->select(
                    'departments.code as department',
                    DB::raw('COUNT(DISTINCT students.id) as total'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                    DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
                )
                ->groupBy('departments.code')
                ->orderBy('departments.code')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'period' => $period,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskByDepartment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk by department data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get risk distribution for doughnut chart.
     */
    public function riskDistribution(Request $request)
    {
        try {
            $period = $request->input('period', 'Midterm');
            $schoolYear = $request->input('school_year', '2024-2025');

            $low = DB::table('risk_scores')
                ->where('risk_level', 'Low')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $moderate = DB::table('risk_scores')
                ->where('risk_level', 'Moderate')
                ->where('grading_period', $period)
                ->where('school_year', $schoolYear)
                ->count();

            $high = DB::table('risk_scores')
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
                'period' => $period,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskDistribution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk distribution data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get institution risk trend for line chart.
     */
    public function riskTrend(Request $request)
    {
        try {
            $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
            $schoolYear = $request->input('school_year', '2024-2025');

            $trend = [];

            foreach ($periods as $period) {
                $high = DB::table('risk_scores')
                    ->where('risk_level', 'High')
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
                    ->count();

                $total = DB::table('risk_scores')
                    ->where('grading_period', $period)
                    ->where('school_year', $schoolYear)
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
                'raw_data' => $trend,
                'school_year' => $schoolYear,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminChartController@riskTrend: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch risk trend data: ' . $e->getMessage(),
            ], 500);
        }
    }
}