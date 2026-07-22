<?php

namespace App\Http\Controllers\MasterTeacher;

use App\Http\Controllers\Controller;
use App\Helpers\DepartmentHelper;
use App\Services\InterventionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterventionController extends Controller
{
    /**
     * @var InterventionService
     */
    protected $interventionService;

    public function __construct(InterventionService $interventionService)
    {
        $this->interventionService = $interventionService;
    }

    /**
     * Show the recommendation management page.
     */
    public function index(Request $request)
    {
        $blockId = $request->input('block_id');
        $programId = $request->input('program_id');
        
        // Get all blocks for the teacher's department
        $departmentId = DepartmentHelper::getDepartmentId($request);
        $blocks = DB::table('blocks')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('programs.department_id', $departmentId)
            ->select('blocks.*', 'year_levels.year_number', 'programs.code as program_code')
            ->orderBy('programs.code')
            ->orderBy('year_levels.year_number')
            ->orderBy('blocks.block_number')
            ->get();
        
        $selectedBlock = null;
        $students = [];
        $recommendations = [];
        $sortedStudents = [];
        
        if ($blockId) {
            // Verify block belongs to this Master Teacher's department
            if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
                return redirect()->route('teacher.department')->with('error', 'You do not have access to this block.');
            }
            
            $selectedBlock = DB::table('blocks')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->where('blocks.id', $blockId)
                ->select('blocks.*', 'year_levels.year_number', 'programs.code as program_code', 'programs.name as program_name')
                ->first();
            
            // Get students in this block
            $students = DB::table('students')
                ->where('block_id', $blockId)
                ->where('status', 'Active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();
            
            // Get existing recommendations for these students
            $studentIds = $students->pluck('id')->toArray();
            if (!empty($studentIds)) {
                $recommendations = DB::table('intervention_recommendations')
                    ->whereIn('student_id', $studentIds)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy('student_id');
            }
            
            // SORT STUDENTS: Students with recommendations FIRST
            $sortedStudents = $students->sortByDesc(function ($student) use ($recommendations) {
                // Check if student has recommendations
                $hasRec = isset($recommendations[$student->id]) && $recommendations[$student->id]->count() > 0;
                
                // Get the latest recommendation date if exists
                if ($hasRec) {
                    $latestRec = $recommendations[$student->id]->first();
                    // Return timestamp for sorting (newest first)
                    return strtotime($latestRec->created_at);
                }
                
                // Students without recommendations go to the bottom
                return 0;
            })->values();
        }
        
        return view('master-teacher.recommendations', [
            'blocks' => $blocks,
            'selectedBlock' => $selectedBlock,
            'students' => $sortedStudents,
            'recommendations' => $recommendations,
            'blockId' => $blockId,
        ]);
    }

    /**
 * Generate recommendations for a block.
 */
public function generateForBlock(Request $request)
{
    $blockId = $request->input('block_id');
    $gradingPeriod = $request->input('period', 'Midterm');
    
    if (!$blockId) {
        return response()->json([
            'success' => false,
            'message' => 'Please select a block.',
        ], 400);
    }

    // Verify block belongs to this Master Teacher's department
    if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this block.',
        ], 403);
    }

    try {
        $result = $this->interventionService->generateForBlock($blockId, $gradingPeriod);
        
        return response()->json([
            'success' => true,
            'success_count' => $result['success_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'total' => $result['total'] ?? 0,
            'message' => "Recommendations generated for {$result['success_count']} student(s).",
            'results' => $result['results'] ?? [],
            'progress' => $result['progress'] ?? [],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to generate recommendations', [
            'block_id' => $blockId,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate recommendations: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Generate recommendations for selected students.
 */
public function generateForSelected(Request $request)
{
    $request->validate([
        'student_ids' => 'required|array|min:1',
        'student_ids.*' => 'integer|exists:students,id',
        'block_id' => 'required|integer|exists:blocks,id',
        'period' => 'nullable|string|in:Prelim,Midterm,Semifinal,Finals',
    ]);

    $blockId = $request->block_id;
    $studentIds = $request->student_ids;
    $gradingPeriod = $request->input('period', 'Midterm');

    // Verify block belongs to this Master Teacher's department
    if (!DepartmentHelper::isBlockInDepartment($request, $blockId)) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this block.',
        ], 403);
    }

    try {
        $result = $this->interventionService->generateForMultiple($studentIds, $gradingPeriod);
        
        return response()->json([
            'success' => true,
            'success_count' => $result['success_count'] ?? 0,
            'failed_count' => $result['failed_count'] ?? 0,
            'total' => $result['total'] ?? 0,
            'message' => "Recommendations generated for {$result['success_count']} student(s).",
            'results' => $result['results'] ?? [],
            'progress' => $result['progress'] ?? [],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to generate selected recommendations', [
            'block_id' => $blockId,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate recommendations: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Show the edit recommendation form.
     */
    public function editRecommendation(Request $request, int $recommendationId)
    {
        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return redirect()->back()->with('error', 'Recommendation not found.');
        }
        
        // Verify this recommendation belongs to a student in the teacher's department
        $student = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $recommendation->student_id)
            ->select('programs.department_id')
            ->first();
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if (!$student || $student->department_id != $departmentId) {
            return redirect()->back()->with('error', 'You do not have access to this recommendation.');
        }
        
        $student = DB::table('students')->where('id', $recommendation->student_id)->first();
        $riskFactors = json_decode($recommendation->risk_factors, true);
        $suggestedActions = json_decode($recommendation->suggested_actions, true);
        
        return view('master-teacher.recommendation-edit', [
            'recommendation' => $recommendation,
            'student' => $student,
            'riskFactors' => $riskFactors,
            'suggestedActions' => $suggestedActions,
        ]);
    }

    /**
     * Update a recommendation.
     */
    public function updateRecommendation(Request $request, int $recommendationId)
    {
        $request->validate([
            'suggested_actions' => 'required|string|min:3',
        ]);

        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return redirect()->back()->with('error', 'Recommendation not found.');
        }
        
        // Verify this recommendation belongs to a student in the teacher's department
        $student = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $recommendation->student_id)
            ->select('programs.department_id')
            ->first();
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if (!$student || $student->department_id != $departmentId) {
            return redirect()->back()->with('error', 'You do not have access to this recommendation.');
        }

        // Parse the suggested actions - support both JSON and plain text
        $actions = $request->suggested_actions;
        
        // Try to parse as JSON, if fails, wrap as simple action
        $parsedActions = json_decode($actions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Not valid JSON, create simple action structure
            $parsedActions = [
                [
                    'action' => 'custom',
                    'details' => $actions,
                    'priority' => 'medium'
                ]
            ];
        }

        DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->update([
                'suggested_actions' => json_encode($parsedActions),
                'updated_at' => now(),
            ]);

        Log::info('Recommendation updated', [
            'recommendation_id' => $recommendationId,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('teacher.recommendations', ['block_id' => $request->block_id])
            ->with('success', 'Recommendation updated successfully.');
    }

    /**
 * Delete a recommendation.
 */
public function deleteRecommendation(Request $request, int $recommendationId)
{
    $recommendation = DB::table('intervention_recommendations')
        ->where('id', $recommendationId)
        ->first();
    
    if (!$recommendation) {
        return response()->json([
            'success' => false,
            'message' => 'Recommendation not found.',
        ], 404);
    }
    
    // Verify ownership
    $student = DB::table('students')
        ->join('blocks', 'students.block_id', '=', 'blocks.id')
        ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
        ->join('programs', 'year_levels.program_id', '=', 'programs.id')
        ->where('students.id', $recommendation->student_id)
        ->select('programs.department_id')
        ->first();
    
    $departmentId = DepartmentHelper::getDepartmentId($request);
    if (!$student || $student->department_id != $departmentId) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this recommendation.',
        ], 403);
    }

    // Delete tracking entries first
    DB::table('student_recommendation_tracking')
        ->where('recommendation_id', $recommendationId)
        ->delete();

    // Delete the recommendation
    DB::table('intervention_recommendations')
        ->where('id', $recommendationId)
        ->delete();

    Log::info('Recommendation deleted', [
        'recommendation_id' => $recommendationId,
        'user_id' => auth()->id(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Recommendation deleted successfully.',
    ]);
}

    /**
     * View a recommendation (AJAX).
     */
    public function viewRecommendation(Request $request, int $recommendationId)
    {
        $recommendation = DB::table('intervention_recommendations')
            ->where('id', $recommendationId)
            ->first();
        
        if (!$recommendation) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation not found.',
            ], 404);
        }
        
        // Verify this recommendation belongs to a student in the teacher's department
        $student = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $recommendation->student_id)
            ->select('students.*', 'programs.department_id')
            ->first();
        
        $departmentId = DepartmentHelper::getDepartmentId($request);
        if (!$student || $student->department_id != $departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this recommendation.',
            ], 403);
        }
        
        $riskFactors = json_decode($recommendation->risk_factors, true) ?? [];
        $suggestedActions = json_decode($recommendation->suggested_actions, true) ?? [];
        
        return response()->json([
            'success' => true,
            'student' => [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->student_number,
            ],
            'risk_factors' => $riskFactors,
            'suggested_actions' => $suggestedActions,
            'generated_at' => \Carbon\Carbon::parse($recommendation->generated_at)->format('F d, Y h:i A'),
        ]);
    }
}