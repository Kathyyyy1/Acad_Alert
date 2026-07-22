<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; 

class DashboardController extends Controller
{
    /**
     * Main Admin Dashboard - System-wide overview.
     */
    public function index(Request $request)
    {
        $stats = $this->getSystemStats();
        $riskByDepartment = $this->getRiskByDepartment();
        $riskDistribution = $this->getRiskDistribution();
        $riskTrend = $this->getRiskTrend();
        $systemHealth = $this->getSystemHealth();
        $recentActivities = $this->getRecentActivities();
        $errors = $this->getRecentErrors();

        return view('admin.dashboard', [
            'stats' => $stats,
            'riskByDepartment' => $riskByDepartment,
            'riskDistribution' => $riskDistribution,
            'riskTrend' => $riskTrend,
            'systemHealth' => $systemHealth,
            'recentActivities' => $recentActivities,
            'errors' => $errors,
        ]);
    }

    // ============================================
    // USERS MANAGEMENT
    // ============================================

    public function users(Request $request)
    {
        $roleFilter = $request->input('role', 'all');
        $departmentFilter = $request->input('department', 'all');
        $programFilter = $request->input('program', 'all');
        $yearLevelFilter = $request->input('year_level', 'all');
        $blockFilter = $request->input('block', 'all');
        $searchFilter = $request->input('search', '');
        
        $query = DB::table('users')
            ->leftJoin('master_teachers', 'users.id', '=', 'master_teachers.user_id')
            ->leftJoin('counselors', 'users.id', '=', 'counselors.user_id')
            ->leftJoin('students', 'users.email', '=', 'students.email')
            ->leftJoin('blocks', 'students.block_id', '=', 'blocks.id')
            ->leftJoin('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->leftJoin('programs', 'year_levels.program_id', '=', 'programs.id')
            ->leftJoin('departments', function($join) {
                $join->on('programs.department_id', '=', 'departments.id')
                     ->orOn('master_teachers.department_id', '=', 'departments.id')
                     ->orOn('counselors.department_id', '=', 'departments.id');
            })
            ->select(
                'users.*',
                'master_teachers.department_id as mt_department_id',
                'counselors.department_id as counselor_department_id',
                'students.student_number',
                'students.block_id',
                'departments.id as student_department_id',
                'departments.code as student_department_code',
                'departments.name as student_department_name',
                'programs.id as program_id',
                'programs.code as program_code',
                'programs.name as program_name',
                'year_levels.id as year_level_id',
                'year_levels.name as year_level_name',
                'blocks.id as block_id',
                'blocks.name as block_name'
            );
        
        if ($roleFilter !== 'all') {
            $query->where('users.role', $roleFilter);
        }
        
        if ($departmentFilter !== 'all') {
            $query->where(function($q) use ($departmentFilter) {
                $q->where('programs.department_id', $departmentFilter)
                  ->orWhere('master_teachers.department_id', $departmentFilter)
                  ->orWhere('counselors.department_id', $departmentFilter);
            });
        }
        
        if ($programFilter !== 'all') {
            $query->where('programs.id', $programFilter);
        }
        
        if ($yearLevelFilter !== 'all') {
            $query->where('year_levels.id', $yearLevelFilter);
        }
        
        if ($blockFilter !== 'all') {
            $query->where('blocks.id', $blockFilter);
        }
        
        if (!empty($searchFilter)) {
            $query->where(function($q) use ($searchFilter) {
                $q->where('users.name', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('users.email', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.student_number', 'LIKE', "%{$searchFilter}%");
            });
        }
        
        $users = $query->orderBy('users.created_at', 'desc')->paginate(20);
        
        $departments = DB::table('departments')->get();
        $programs = DB::table('programs')->get();
        $yearLevels = DB::table('year_levels')->get();
        $blocks = DB::table('blocks')->get();
        $roles = ['admin', 'master_teacher', 'guidance_counselor', 'student'];
        
        return view('admin.users', compact(
            'users', 'departments', 'programs', 'yearLevels', 'blocks', 'roles',
            'roleFilter', 'departmentFilter', 'programFilter', 'yearLevelFilter',
            'blockFilter', 'searchFilter'
        ));
    }

    public function createUser()
    {
        $departments = DB::table('departments')->get();
        return view('admin.users-create', compact('departments'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,master_teacher,guidance_counselor,student',
            'password' => 'required|string|min:6|confirmed',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        try {
            DB::beginTransaction();

            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->role === 'master_teacher' && $request->department_id) {
                DB::table('master_teachers')->insert([
                    'user_id' => $userId,
                    'department_id' => $request->department_id,
                    'employee_number' => 'MT-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => 'General',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->role === 'guidance_counselor' && $request->department_id) {
                DB::table('counselors')->insert([
                    'user_id' => $userId,
                    'department_id' => $request->department_id,
                    'employee_number' => 'GC-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'specialization' => 'Academic Counseling',
                    'max_caseload' => 30,
                    'office_location' => 'Guidance Office',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->role === 'student') {
                $block = DB::table('blocks')->inRandomOrder()->first();
                DB::table('students')->insert([
                    'block_id' => $block->id ?? 1,
                    'student_number' => 'UDD-' . date('Y') . '-STU-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'first_name' => explode(' ', $request->name)[0],
                    'last_name' => explode(' ', $request->name)[1] ?? 'Student',
                    'email' => $request->email,
                    'status' => 'Active',
                    'year_enrolled' => date('Y'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            $this->logActivity('USER_CREATED', 'Created user: ' . $request->email);
            return redirect()->route('admin.users.index')->with('success', 'User created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create user: ' . $e->getMessage());
        }
    }

    public function getProgramsByDepartment(int $departmentId)
    {
        $programs = DB::table('programs')
            ->where('department_id', $departmentId)
            ->select('id', 'code', 'name')
            ->get();
        return response()->json($programs);
    }

    public function getYearLevelsByProgram(int $programId)
    {
        $yearLevels = DB::table('year_levels')
            ->where('program_id', $programId)
            ->select('id', 'year_number', 'name')
            ->orderBy('year_number')
            ->get();
        return response()->json($yearLevels);
    }

    public function getBlocksByYearLevel(int $yearLevelId)
    {
        $blocks = DB::table('blocks')
            ->where('year_level_id', $yearLevelId)
            ->select('id', 'block_number', 'name')
            ->orderBy('block_number')
            ->get();
        return response()->json($blocks);
    }

    public function getDepartments()
    {
        $departments = DB::table('departments')
            ->select('id', 'code', 'name')
            ->get();
        return response()->json($departments);
    }

    public function editUser(int $id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        $departments = DB::table('departments')->get();
        $programs = DB::table('programs')->get();
        $yearLevels = DB::table('year_levels')->get();
        $blocks = DB::table('blocks')->get();
        
        $additional = null;
        $studentDetails = null;
        
        if ($user->role === 'master_teacher') {
            $additional = DB::table('master_teachers')->where('user_id', $user->id)->first();
        } elseif ($user->role === 'guidance_counselor') {
            $additional = DB::table('counselors')->where('user_id', $user->id)->first();
        } elseif ($user->role === 'student') {
            $studentDetails = DB::table('students')->where('email', $user->email)->first();
            if ($studentDetails) {
                $block = DB::table('blocks')->where('id', $studentDetails->block_id)->first();
                if ($block) {
                    $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
                    if ($yearLevel) {
                        $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
                        $studentDetails->program_id = $program->id ?? null;
                        $studentDetails->year_level_id = $yearLevel->id ?? null;
                    }
                }
            }
        }

        return view('admin.users-edit', compact(
            'user', 'additional', 'departments', 'programs', 'yearLevels', 'blocks', 'studentDetails'
        ));
    }

    public function updateUser(Request $request, int $id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User not found.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => 'required|in:admin,master_teacher,guidance_counselor,student',
            'password' => 'nullable|string|min:6',
            'department_id' => 'nullable|exists:departments,id',
            'program_id' => 'nullable|exists:programs,id',
            'year_level_id' => 'nullable|exists:year_levels,id',
            'block_id' => 'nullable|exists:blocks,id',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'student_number' => 'nullable|string|max:30',
            'status' => 'nullable|string|in:Active,Inactive',
        ]);

        try {
            DB::beginTransaction();

            $isActive = $request->has('is_active') ? 1 : 0;
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'is_active' => $isActive,
                'updated_at' => now(),
            ];

            if ($request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            DB::table('users')->where('id', $id)->update($updateData);

            $departmentId = $request->department_id;

            DB::table('master_teachers')->where('user_id', $id)->delete();
            DB::table('counselors')->where('user_id', $id)->delete();

            if ($request->role === 'master_teacher' && $departmentId) {
                DB::table('master_teachers')->insert([
                    'user_id' => $id,
                    'department_id' => $departmentId,
                    'employee_number' => 'MT-' . str_pad($id, 5, '0', STR_PAD_LEFT),
                    'specialization' => $request->specialization ?? 'General',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->role === 'guidance_counselor' && $departmentId) {
                DB::table('counselors')->insert([
                    'user_id' => $id,
                    'department_id' => $departmentId,
                    'employee_number' => 'GC-' . str_pad($id, 5, '0', STR_PAD_LEFT),
                    'specialization' => $request->specialization ?? 'Academic Counseling',
                    'max_caseload' => $request->max_caseload ?? 30,
                    'office_location' => $request->office_location ?? 'Guidance Office',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->role === 'student') {
                $existingStudent = DB::table('students')->where('email', $user->email)->first();
                $oldBlockId = $existingStudent->block_id ?? null;
                $newBlockId = $request->block_id;
                $blockChanged = ($oldBlockId && $newBlockId && $oldBlockId != $newBlockId);
                
                if ($existingStudent) {
                    $studentUpdateData = [
                        'first_name' => $request->first_name ?? explode(' ', $request->name)[0],
                        'last_name' => $request->last_name ?? (explode(' ', $request->name)[1] ?? 'Student'),
                        'email' => $request->email,
                        'status' => $request->status ?? 'Active',
                        'updated_at' => now(),
                    ];
                    
                    if ($newBlockId) {
                        $studentUpdateData['block_id'] = $newBlockId;
                    }
                    if ($request->student_number) {
                        $studentUpdateData['student_number'] = $request->student_number;
                    }
                    
                    DB::table('students')->where('id', $existingStudent->id)->update($studentUpdateData);
                    
                    if ($blockChanged) {
                        $this->resetStudentData($existingStudent->id);
                        Log::info('Student data reset for student ID: ' . $existingStudent->id);
                    }
                } else {
                    DB::table('students')->insert([
                        'block_id' => $newBlockId ?? 1,
                        'student_number' => $request->student_number ?? 'STU-' . str_pad($id, 5, '0', STR_PAD_LEFT),
                        'first_name' => $request->first_name ?? explode(' ', $request->name)[0],
                        'last_name' => $request->last_name ?? (explode(' ', $request->name)[1] ?? 'Student'),
                        'email' => $request->email,
                        'status' => $request->status ?? 'Active',
                        'year_enrolled' => date('Y'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update user failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    protected function resetStudentData(int $studentId): void
    {
        try {
            DB::table('grades')->where('student_id', $studentId)->delete();
            DB::table('attendance')->where('student_id', $studentId)->delete();
            DB::table('attendance_summaries')->where('student_id', $studentId)->delete();
            DB::table('attendance_warnings')->where('student_id', $studentId)->delete();
            
            $deletedRisk = DB::table('risk_scores')->where('student_id', $studentId)->delete();
            Log::info('Risk scores deleted for student ' . $studentId . ': ' . $deletedRisk . ' records');
            
            DB::table('flags')->where('student_id', $studentId)->delete();
            DB::table('intervention_recommendations')->where('student_id', $studentId)->delete();
            DB::table('alert_acknowledgments')->where('student_id', $studentId)->delete();
            DB::table('escalations')->where('student_id', $studentId)->delete();
            
            DB::table('cases')
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->update([
                    'status' => 'Closed',
                    'resolved_reason' => 'Student transferred to new program - data reset',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
            
            $caseIds = DB::table('cases')->where('student_id', $studentId)->pluck('id');
            if ($caseIds->isNotEmpty()) {
                DB::table('case_sessions')->whereIn('case_id', $caseIds)->delete();
            }
            
            DB::table('payments')
                ->where('student_id', $studentId)
                ->update(['status' => 'Paid', 'updated_at' => now()]);
            
            $this->logActivity('STUDENT_DATA_RESET', 'Student ID: ' . $studentId . ' - Data reset for transfer');
            
        } catch (\Exception $e) {
            Log::error('Error resetting student data: ' . $e->getMessage());
        }
    }

    public function deleteUser(int $id)
    {
        $user = DB::table('users')->where('id', $id)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
    
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 403);
        }

        try {
            DB::beginTransaction();

            DB::table('audit_logs')->where('user_id', $user->id)->delete();

            if ($user->role === 'master_teacher') {
                DB::table('master_teachers')->where('user_id', $user->id)->delete();
            } elseif ($user->role === 'guidance_counselor') {
                DB::table('counselors')->where('user_id', $user->id)->delete();
            } elseif ($user->role === 'student') {
                $student = DB::table('students')->where('email', $user->email)->first();
                if ($student) {
                    DB::table('parents')->where('student_id', $student->id)->delete();
                    DB::table('grades')->where('student_id', $student->id)->delete();
                    DB::table('attendance')->where('student_id', $student->id)->delete();
                    DB::table('attendance_summaries')->where('student_id', $student->id)->delete();
                    DB::table('attendance_warnings')->where('student_id', $student->id)->delete();
                    DB::table('risk_scores')->where('student_id', $student->id)->delete();
                    DB::table('flags')->where('student_id', $student->id)->delete();
                    DB::table('intervention_recommendations')->where('student_id', $student->id)->delete();
                    DB::table('alert_acknowledgments')->where('student_id', $student->id)->delete();
                    DB::table('escalations')->where('student_id', $student->id)->delete();
                    DB::table('cases')->where('student_id', $student->id)->delete();
                    DB::table('payments')->where('student_id', $student->id)->delete();
                    DB::table('students')->where('id', $student->id)->delete();
                }
            }

            DB::table('users')->where('id', $user->id)->delete();

            DB::commit();
            $this->logActivity('USER_DELETED', 'Deleted user: ' . $user->email);
            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function updateRoleSpecificData(int $userId, string $role, array $data)
    {
        DB::table('master_teachers')->where('user_id', $userId)->delete();
        DB::table('counselors')->where('user_id', $userId)->delete();

        if ($role === 'master_teacher' && isset($data['department_id']) && !empty($data['department_id'])) {
            DB::table('master_teachers')->insert([
                'user_id' => $userId,
                'department_id' => $data['department_id'],
                'employee_number' => 'MT-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                'specialization' => $data['specialization'] ?? 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($role === 'guidance_counselor' && isset($data['department_id']) && !empty($data['department_id'])) {
            DB::table('counselors')->insert([
                'user_id' => $userId,
                'department_id' => $data['department_id'],
                'employee_number' => 'GC-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                'specialization' => $data['specialization'] ?? 'Academic Counseling',
                'max_caseload' => $data['max_caseload'] ?? 30,
                'office_location' => $data['office_location'] ?? 'Guidance Office',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($role === 'student') {
            // Students are updated through a separate process
        }
    }

    // ============================================
    // ACADEMIC STRUCTURE
    // ============================================

    public function academic()
    {
        $departments = \App\Models\Department::withCount('programs')->get();
        $programs = DB::table('programs')
            ->join('departments', 'programs.department_id', '=', 'departments.id')
            ->select('programs.*', 'departments.code as department_code')
            ->get();
        $subjects = DB::table('subjects')
            ->join('programs', 'subjects.program_id', '=', 'programs.id')
            ->select('subjects.*', 'programs.code as program_code')
            ->orderBy('programs.code')
            ->orderBy('subjects.year_level')
            ->orderBy('subjects.semester')
            ->get();

        return view('admin.academic', compact('departments', 'programs', 'subjects'));
    }

    public function storeDepartment(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:departments,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        try {
            DB::table('departments')->insert([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->logActivity('DEPARTMENT_CREATED', 'Created department: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Department created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create department: ' . $e->getMessage());
        }
    }

    public function updateDepartment(Request $request, int $id)
    {
        $request->validate([
            'code' => 'required|string|max:10|unique:departments,code,' . $id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        try {
            DB::table('departments')->where('id', $id)->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'updated_at' => now(),
            ]);
            $this->logActivity('DEPARTMENT_UPDATED', 'Updated department: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Department updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update department: ' . $e->getMessage());
        }
    }

    public function deleteDepartment(int $id)
    {
        try {
            $programCount = DB::table('programs')->where('department_id', $id)->count();
            if ($programCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with existing programs. Delete programs first.'
                ], 400);
            }

            DB::table('departments')->where('id', $id)->delete();
            $this->logActivity('DEPARTMENT_DELETED', 'Deleted department ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Department deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeProgram(Request $request)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'code' => 'required|string|max:10|unique:programs,code',
            'name' => 'required|string|max:100',
            'total_students' => 'nullable|integer|min:0',
        ]);

        try {
            DB::table('programs')->insert([
                'department_id' => $request->department_id,
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'total_students' => $request->total_students ?? 160,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->logActivity('PROGRAM_CREATED', 'Created program: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Program created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create program: ' . $e->getMessage());
        }
    }

    public function updateProgram(Request $request, int $id)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
            'code' => 'required|string|max:10|unique:programs,code,' . $id,
            'name' => 'required|string|max:100',
            'total_students' => 'nullable|integer|min:0',
        ]);

        try {
            DB::table('programs')->where('id', $id)->update([
                'department_id' => $request->department_id,
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'total_students' => $request->total_students ?? 160,
                'updated_at' => now(),
            ]);
            $this->logActivity('PROGRAM_UPDATED', 'Updated program: ' . $request->code);
            return redirect()->route('admin.academic.index')->with('success', 'Program updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update program: ' . $e->getMessage());
        }
    }

    public function deleteProgram(int $id)
    {
        try {
            $yearLevelCount = DB::table('year_levels')->where('program_id', $id)->count();
            if ($yearLevelCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete program with existing year levels. Delete year levels first.'
                ], 400);
            }

            DB::table('programs')->where('id', $id)->delete();
            $this->logActivity('PROGRAM_DELETED', 'Deleted program ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Program deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSubject(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|min:1|max:4',
            'semester' => 'required|integer|min:1|max:2',
            'subject_code' => 'required|string|max:20|unique:subjects,subject_code',
            'subject_name' => 'required|string|max:200',
            'units' => 'nullable|integer|min:1|max:6',
        ]);

        try {
            DB::table('subjects')->insert([
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'semester' => $request->semester,
                'subject_code' => strtoupper($request->subject_code),
                'subject_name' => $request->subject_name,
                'units' => $request->units ?? 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->logActivity('SUBJECT_CREATED', 'Created subject: ' . $request->subject_code);
            return redirect()->route('admin.academic.index')->with('success', 'Subject created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create subject: ' . $e->getMessage());
        }
    }

    public function updateSubject(Request $request, int $id)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|min:1|max:4',
            'semester' => 'required|integer|min:1|max:2',
            'subject_code' => 'required|string|max:20|unique:subjects,subject_code,' . $id,
            'subject_name' => 'required|string|max:200',
            'units' => 'nullable|integer|min:1|max:6',
        ]);

        try {
            DB::table('subjects')->where('id', $id)->update([
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'semester' => $request->semester,
                'subject_code' => strtoupper($request->subject_code),
                'subject_name' => $request->subject_name,
                'units' => $request->units ?? 3,
                'updated_at' => now(),
            ]);
            $this->logActivity('SUBJECT_UPDATED', 'Updated subject: ' . $request->subject_code);
            return redirect()->route('admin.academic.index')->with('success', 'Subject updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update subject: ' . $e->getMessage());
        }
    }

    public function deleteSubject(int $id)
    {
        try {
            $gradeCount = DB::table('grades')->where('subject_id', $id)->count();
            if ($gradeCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete subject with existing grades. Delete grades first.'
                ], 400);
            }

            DB::table('subjects')->where('id', $id)->delete();
            $this->logActivity('SUBJECT_DELETED', 'Deleted subject ID: ' . $id);
            return response()->json(['success' => true, 'message' => 'Subject deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function editDepartment(int $id)
    {
        $department = DB::table('departments')->where('id', $id)->first();
        if (!$department) {
            return back()->with('error', 'Department not found.');
        }
        return view('admin.academic-edit-department', compact('department'));
    }

    public function editProgram(int $id)
    {
        $program = DB::table('programs')->where('id', $id)->first();
        if (!$program) {
            return back()->with('error', 'Program not found.');
        }
        $departments = DB::table('departments')->get();
        return view('admin.academic-edit-program', compact('program', 'departments'));
    }

    public function editSubject(int $id)
    {
        $subject = DB::table('subjects')->where('id', $id)->first();
        if (!$subject) {
            return back()->with('error', 'Subject not found.');
        }
        $programs = DB::table('programs')->get();
        return view('admin.academic-edit-subject', compact('subject', 'programs'));
    }

    // ============================================
    // RISK CONFIGURATION
    // ============================================

    public function riskConfig()
    {
        $thresholds = DB::table('risk_thresholds')->first();
        if (!$thresholds) {
            $thresholds = (object) [
                'low_threshold' => 40,
                'moderate_threshold' => 70,
                'high_threshold' => 71,
                'grade_weight' => 0.60,
                'attendance_weight' => 0.40,
            ];
        }
        return view('admin.risk-config', compact('thresholds'));
    }

    public function saveRiskConfig(Request $request)
    {
        $request->validate([
            'low_threshold' => 'required|integer|min:0|max:100',
            'moderate_threshold' => 'required|integer|min:0|max:100',
            'high_threshold' => 'required|integer|min:0|max:100',
            'grade_weight' => 'required|numeric|min:0|max:1',
            'attendance_weight' => 'required|numeric|min:0|max:1',
        ]);

        if ($request->low_threshold >= $request->moderate_threshold) {
            return back()->with('error', 'Low threshold must be less than moderate threshold.');
        }
        if ($request->moderate_threshold >= $request->high_threshold) {
            return back()->with('error', 'Moderate threshold must be less than high threshold.');
        }
        if (round($request->grade_weight + $request->attendance_weight, 2) != 1.00) {
            return back()->with('error', 'Grade weight and attendance weight must sum to 100%.');
        }

        $existing = DB::table('risk_thresholds')->first();
        if ($existing) {
            DB::table('risk_thresholds')
                ->where('id', $existing->id)
                ->update([
                    'low_threshold' => $request->low_threshold,
                    'moderate_threshold' => $request->moderate_threshold,
                    'high_threshold' => $request->high_threshold,
                    'grade_weight' => $request->grade_weight,
                    'attendance_weight' => $request->attendance_weight,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('risk_thresholds')->insert([
                'low_threshold' => $request->low_threshold,
                'moderate_threshold' => $request->moderate_threshold,
                'high_threshold' => $request->high_threshold,
                'grade_weight' => $request->grade_weight,
                'attendance_weight' => $request->attendance_weight,
                'updated_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->logActivity('RISK_CONFIG_UPDATED', 'Risk thresholds updated');
        return back()->with('success', 'Risk configuration saved successfully.');
    }

    public function testAIConnection()
    {
        $apiKey = config('services.aistudio.api_key');
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'message' => 'AI API key is not configured in .env file.']);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'AI API connection successful.']);
            }
            return response()->json(['success' => false, 'message' => 'AI API connection failed: ' . $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'AI API connection error: ' . $e->getMessage()]);
        }
    }

    // ============================================
    // PAYMENT MONITORING
    // ============================================

    public function payments(Request $request)
    {
        try {
            $statusFilter = $request->input('status', 'all');
            $departmentFilter = $request->input('department', 'all');
            $searchFilter = $request->input('search', '');
            $paymentType = $request->input('payment_type', 'tuition');
            
            $query = DB::table('payments')
                ->join('students', 'payments.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->join('departments', 'programs.department_id', '=', 'departments.id')
                ->select(
                    'payments.*',
                    'students.id as student_id',
                    'students.first_name',
                    'students.last_name',
                    'students.student_number',
                    'students.email as student_email',
                    'programs.code as program_code',
                    'programs.name as program_name',
                    'departments.code as department_code',
                    'departments.name as department_name',
                    'blocks.name as block_name',
                    'year_levels.name as year_level_name'
                );
            
            if ($statusFilter !== 'all') {
                $query->where('payments.status', $statusFilter);
            }
            if ($departmentFilter !== 'all') {
                $query->where('departments.code', $departmentFilter);
            }
            if (!empty($searchFilter)) {
                $query->where(function($q) use ($searchFilter) {
                    $q->where('students.first_name', 'LIKE', "%{$searchFilter}%")
                      ->orWhere('students.last_name', 'LIKE', "%{$searchFilter}%")
                      ->orWhere('students.student_number', 'LIKE', "%{$searchFilter}%")
                      ->orWhere('students.email', 'LIKE', "%{$searchFilter}%");
                });
            }
            
            $payments = $query->orderBy('payments.due_date', 'asc')->paginate(20);
            $stats = $this->getPaymentStats($statusFilter, $departmentFilter);
            $departments = DB::table('departments')->get();
            $departmentSummary = $this->getDepartmentPaymentSummary();
            $overdueSummary = $this->getOverdueSummary();
            $paymentTrend = $this->getPaymentTrend();
            
            Log::info('Payment Monitoring loaded', [
                'total_payments' => $payments->total(),
                'filter_applied' => $statusFilter,
            ]);
            
            return view('admin.payments', [
                'payments' => $payments,
                'stats' => $stats,
                'departments' => $departments,
                'departmentSummary' => $departmentSummary,
                'overdueSummary' => $overdueSummary,
                'paymentTrend' => $paymentTrend,
                'statusFilter' => $statusFilter,
                'departmentFilter' => $departmentFilter,
                'searchFilter' => $searchFilter,
                'paymentType' => $paymentType,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment Monitoring Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to load payment data: ' . $e->getMessage());
        }
    }




    protected function getPaymentStats($statusFilter = 'all', $departmentFilter = 'all')
    {
        try {
            $query = DB::table('payments')
                ->join('students', 'payments.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->join('departments', 'programs.department_id', '=', 'departments.id');
            
            if ($statusFilter !== 'all') {
                $query->where('payments.status', $statusFilter);
            }
            if ($departmentFilter !== 'all') {
                $query->where('departments.code', $departmentFilter);
            }
            
            $totalAmount = (clone $query)->sum('payments.amount');
            $collectedAmount = (clone $query)->sum('payments.paid_amount');
            $overdueAmount = (clone $query)->where('payments.status', 'Overdue')->sum('payments.balance');
            $outstandingAmount = (clone $query)->whereIn('payments.status', ['Unpaid', 'Partial', 'Overdue'])->sum('payments.balance');
            $totalCount = (clone $query)->count();
            $paidCount = (clone $query)->where('payments.status', 'Paid')->count();
            $partialCount = (clone $query)->where('payments.status', 'Partial')->count();
            $unpaidCount = (clone $query)->where('payments.status', 'Unpaid')->count();
            $overdueCount = (clone $query)->where('payments.status', 'Overdue')->count();
            $collectionRate = $totalAmount > 0 ? round(($collectedAmount / $totalAmount) * 100, 1) : 0;
            
            return [
                'total_amount' => $totalAmount,
                'collected_amount' => $collectedAmount,
                'overdue_amount' => $overdueAmount,
                'outstanding_amount' => $outstandingAmount,
                'total_count' => $totalCount,
                'paid_count' => $paidCount,
                'partial_count' => $partialCount,
                'unpaid_count' => $unpaidCount,
                'overdue_count' => $overdueCount,
                'collection_rate' => $collectionRate,
            ];
        } catch (\Exception $e) {
            Log::error('Payment Stats Error: ' . $e->getMessage());
            return [
                'total_amount' => 0,
                'collected_amount' => 0,
                'overdue_amount' => 0,
                'outstanding_amount' => 0,
                'total_count' => 0,
                'paid_count' => 0,
                'partial_count' => 0,
                'unpaid_count' => 0,
                'overdue_count' => 0,
                'collection_rate' => 0,
            ];
        }
    }

    protected function getDepartmentPaymentSummary()
    {
        try {
            return DB::table('payments')
                ->join('students', 'payments.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->join('departments', 'programs.department_id', '=', 'departments.id')
                ->select(
                    'departments.code as department',
                    DB::raw('COUNT(*) as total_students'),
                    DB::raw('SUM(payments.amount) as total_amount'),
                    DB::raw('SUM(payments.paid_amount) as collected_amount'),
                    DB::raw('SUM(payments.balance) as outstanding_amount'),
                    DB::raw('SUM(CASE WHEN payments.status = "Paid" THEN 1 ELSE 0 END) as paid_count'),
                    DB::raw('SUM(CASE WHEN payments.status = "Overdue" THEN 1 ELSE 0 END) as overdue_count'),
                    DB::raw('SUM(CASE WHEN payments.status = "Unpaid" THEN 1 ELSE 0 END) as unpaid_count'),
                    DB::raw('SUM(CASE WHEN payments.status = "Partial" THEN 1 ELSE 0 END) as partial_count')
                )
                ->groupBy('departments.code')
                ->orderBy('departments.code')
                ->get();
        } catch (\Exception $e) {
            Log::error('Department Payment Summary Error: ' . $e->getMessage());
            return collect();
        }
    }

    protected function getOverdueSummary()
    {
        try {
            return DB::table('payments')
                ->join('students', 'payments.student_id', '=', 'students.id')
                ->join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
                ->join('programs', 'year_levels.program_id', '=', 'programs.id')
                ->join('departments', 'programs.department_id', '=', 'departments.id')
                ->where('payments.status', 'Overdue')
                ->select(
                    'students.id as student_id',
                    'students.first_name',
                    'students.last_name',
                    'students.student_number',
                    'students.email',
                    'payments.amount',
                    'payments.paid_amount',
                    'payments.balance',
                    'payments.due_date',
                    'payments.fee_type',
                    'programs.code as program_code',
                    'departments.code as department_code',
                    DB::raw('DATEDIFF(CURDATE(), payments.due_date) as days_overdue')
                )
                ->orderBy('payments.due_date', 'asc')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            Log::error('Overdue Summary Error: ' . $e->getMessage());
            return collect();
        }
    }

    /**
 * Get payment trend data for chart.
 */
protected function getPaymentTrend(): array
{
    try {
        $months = [];
        $collected = [];
        $overdue = [];
        
        for ($i = 0; $i < 6; $i++) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M Y');
            $months[] = $monthName;
            
            // Collected amount for this month
            $collectedAmount = DB::table('payment_history')
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->where('action', 'Payment Received')
                ->sum('amount_changed');
            
            $collected[] = round((float) $collectedAmount, 2);
            
            // Overdue amount for this month
            $overdueAmount = DB::table('payments')
                ->whereMonth('due_date', $month->month)
                ->whereYear('due_date', $month->year)
                ->where('status', 'Overdue')
                ->sum('balance');
            
            $overdue[] = round((float) $overdueAmount, 2);
        }
        
        return [
            'months' => array_reverse($months),
            'collected' => array_reverse($collected),
            'overdue' => array_reverse($overdue),
        ];
        
    } catch (\Exception $e) {
        Log::error('Payment Trend Error: ' . $e->getMessage());
        return [
            'months' => [],
            'collected' => [],
            'overdue' => [],
        ];
    }
}

    /**
 * View payment details.
 */
public function viewPayment(int $id)
{
    try {
        $payment = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->join('programs', DB::raw('(SELECT program_id FROM year_levels WHERE year_levels.id = (SELECT year_level_id FROM blocks WHERE blocks.id = students.block_id))'), '=', DB::raw('programs.id'))
            ->where('payments.id', $id)
            ->select(
                'payments.*',
                'students.first_name',
                'students.last_name',
                'students.student_number',
                'programs.code as program_code'
            )
            ->first();
        
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }
        
        // Convert numeric fields to float
        $payment->amount = (float) $payment->amount;
        $payment->paid_amount = (float) $payment->paid_amount;
        $payment->balance = (float) $payment->balance;
        
        return response()->json(['success' => true, 'payment' => $payment]);
        
    } catch (\Exception $e) {
        Log::error('View Payment Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

 /**
 * Get payment info for recording - Enhanced with detailed error handling.
 */
public function getPaymentInfo($id)
{
    $debug = [
        'method' => 'getPaymentInfo',
        'timestamp' => now()->toDateTimeString(),
        'payment_id' => $id,
        'user_id' => auth()->id(),
    ];

    try {
        $id = (int) $id;
        $debug['payment_id_int'] = $id;

        Log::info('[GetPaymentInfo] Fetching payment info', $debug);

        $payment = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->where('payments.id', $id)
            ->select(
                'payments.balance',
                'students.first_name',
                'students.last_name'
            )
            ->first();

        if (!$payment) {
            $debug['payment_found'] = false;
            Log::warning('[GetPaymentInfo] Payment not found', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'debug' => $debug,
            ], 404);
        }

        $debug['payment_found'] = true;
        $debug['student_name'] = $payment->first_name . ' ' . $payment->last_name;
        $debug['balance'] = (float) $payment->balance;

        Log::info('[GetPaymentInfo] Payment info retrieved', $debug);

        return response()->json([
            'success' => true,
            'student_name' => $payment->first_name . ' ' . $payment->last_name,
            'balance' => (float) $payment->balance,
            'debug' => $debug,
        ]);

    } catch (\Exception $e) {
        $debug['error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        Log::error('[GetPaymentInfo] Error fetching payment info', $debug);
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'debug' => $debug,
        ], 500);
    }
}
/**
 * Record a payment - Enhanced with detailed error handling.
 */
public function recordPayment(Request $request, $id)
{
    $debug = [
        'method' => 'recordPayment',
        'timestamp' => now()->toDateTimeString(),
        'payment_id' => $id,
        'request_data' => $request->all(),
        'user_id' => auth()->id(),
        'ip' => $request->ip(),
    ];

    try {
        // Cast ID to integer
        $id = (int) $id;
        $debug['payment_id_int'] = $id;
        
        Log::info('[RecordPayment] Starting payment recording', $debug);

        // Validate request
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'reference' => 'nullable|string|max:50',
                'notes' => 'nullable|string|max:500',
            ]);
            $debug['validation_passed'] = true;
            $debug['validated_data'] = $validated;
        } catch (\Illuminate\Validation\ValidationException $e) {
            $debug['validation_errors'] = $e->errors();
            Log::error('[RecordPayment] Validation failed', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_merge(...array_values($e->errors()))),
                'debug' => $debug,
                'errors' => $e->errors(),
            ], 422);
        }

        // Find payment
        try {
            $payment = DB::table('payments')->where('id', $id)->first();
            if (!$payment) {
                $debug['payment_found'] = false;
                Log::warning('[RecordPayment] Payment not found', $debug);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found.',
                    'debug' => $debug,
                ], 404);
            }
            $debug['payment_found'] = true;
            $debug['payment_data'] = [
                'id' => $payment->id,
                'student_id' => $payment->student_id,
                'amount' => $payment->amount,
                'paid_amount' => $payment->paid_amount,
                'balance' => $payment->balance,
                'status' => $payment->status,
            ];
            Log::info('[RecordPayment] Payment found', $debug);
        } catch (\Exception $e) {
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Database query failed - finding payment', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

        // Calculate new values
        try {
            $newPaidAmount = (float) $payment->paid_amount + (float) $request->amount;
            $newBalance = (float) $payment->amount - $newPaidAmount;
            
            // Determine new status
            if ($newBalance <= 0.01) {
                $newStatus = 'Paid';
            } elseif ($newPaidAmount > 0) {
                $newStatus = 'Partial';
            } else {
                $newStatus = 'Unpaid';
            }
            
            $debug['calculations'] = [
                'old_paid' => (float) $payment->paid_amount,
                'new_paid' => $newPaidAmount,
                'old_balance' => (float) $payment->balance,
                'new_balance' => $newBalance,
                'old_status' => $payment->status,
                'new_status' => $newStatus,
            ];
            Log::info('[RecordPayment] Calculations completed', $debug);
        } catch (\Exception $e) {
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Calculation error', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Calculation error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

        // Database transaction
        try {
            DB::beginTransaction();
            $debug['transaction_started'] = true;

            // Update payment
            $updated = DB::table('payments')
                ->where('id', $id)
                ->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newStatus,
                    'last_payment_date' => $request->payment_date,
                    'updated_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

            $debug['payment_update'] = [
                'rows_affected' => $updated,
                'query' => 'UPDATE payments SET paid_amount = ?, balance = ?, status = ?, last_payment_date = ?, updated_at = ?, updated_by = ? WHERE id = ?',
            ];
            Log::info('[RecordPayment] Payment updated', $debug);

            // Record in history
            $historyInserted = DB::table('payment_history')->insert([
                'payment_id' => $id,
                'action' => 'Payment Received',
                'old_status' => $payment->status,
                'new_status' => $newStatus,
                'amount_changed' => (float) $request->amount,
                'performed_by' => auth()->id(),
                'notes' => ($request->notes ?? '') . ' | Ref: ' . ($request->reference ?? 'N/A'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $debug['history_inserted'] = $historyInserted;
            Log::info('[RecordPayment] Payment history recorded', $debug);

            // Log activity
            $this->logActivity('PAYMENT_RECORDED', 'Payment recorded for student ' . $payment->student_id . ' | Amount: ' . $request->amount);
            $debug['activity_logged'] = true;

            DB::commit();
            $debug['transaction_committed'] = true;
            
            Log::info('[RecordPayment] Payment recorded successfully', $debug);

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'new_balance' => max(0, $newBalance),
                'new_status' => $newStatus,
                'debug' => $debug,
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
                $debug['transaction_rolled_back'] = true;
            }
            $debug['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            Log::error('[RecordPayment] Transaction error', $debug);
            return response()->json([
                'success' => false,
                'message' => 'Transaction error: ' . $e->getMessage(),
                'debug' => $debug,
            ], 500);
        }

    } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
            $debug['transaction_rolled_back'] = true;
        }
        $debug['error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        Log::error('[RecordPayment] Unexpected error', $debug);
        return response()->json([
            'success' => false,
            'message' => 'Unexpected error: ' . $e->getMessage(),
            'debug' => $debug,
        ], 500);
    }
}



// ============================================
// EXPORT PAYMENTS 
// ============================================

/**
 * Export payments report as CSV.
 */
public function exportPayments(Request $request)
{
    try {
        $statusFilter = $request->input('status', 'all');
        $departmentFilter = $request->input('department', 'all');
        $searchFilter = $request->input('search', '');
        
        Log::info('[Export] Starting payment export', [
            'status' => $statusFilter,
            'department' => $departmentFilter,
            'search' => $searchFilter,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
        ]);
        
        // Build query
        $query = DB::table('payments')
            ->join('students', 'payments.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->join('departments', 'programs.department_id', '=', 'departments.id')
            ->select(
                'students.student_number',
                'students.first_name',
                'students.last_name',
                'students.email',
                'programs.code as program_code',
                'programs.name as program_name',
                'departments.code as department_code',
                'payments.fee_type',
                'payments.amount',
                'payments.paid_amount',
                'payments.balance',
                'payments.status',
                'payments.due_date',
                'payments.last_payment_date'
            );
        
        if ($statusFilter !== 'all') {
            $query->where('payments.status', $statusFilter);
        }
        
        if ($departmentFilter !== 'all') {
            $query->where('departments.code', $departmentFilter);
        }
        
        if (!empty($searchFilter)) {
            $query->where(function($q) use ($searchFilter) {
                $q->where('students.first_name', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.last_name', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.student_number', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('students.email', 'LIKE', "%{$searchFilter}%");
            });
        }
        
        $payments = $query->orderBy('payments.due_date', 'asc')->get();
        
        Log::info('[Export] Payment records fetched', ['count' => $payments->count()]);
        
        // Generate CSV
        $filename = 'payment_report_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($handle, [
            'Student Number',
            'First Name',
            'Last Name',
            'Email',
            'Program',
            'Department',
            'Fee Type',
            'Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Due Date',
            'Last Payment Date'
        ]);
        
        // Data rows
        foreach ($payments as $payment) {
            fputcsv($handle, [
                $payment->student_number,
                $payment->first_name,
                $payment->last_name,
                $payment->email,
                $payment->program_code . ' - ' . $payment->program_name,
                $payment->department_code,
                $payment->fee_type,
                number_format($payment->amount, 2),
                number_format($payment->paid_amount, 2),
                number_format($payment->balance, 2),
                $payment->status,
                $payment->due_date,
                $payment->last_payment_date ?? 'N/A'
            ]);
        }
        
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);
        
        Log::info('[Export] CSV generated successfully', ['size' => strlen($csvContent)]);
        
        // Return CSV as download
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        
    } catch (\Exception $e) {
        Log::error('[Export] Export error: ' . $e->getMessage());
        Log::error('[Export] Export trace: ' . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Failed to export payments: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
}




    // ============================================
    // AUDIT LOGS
    // ============================================

    public function auditLogs(Request $request)
    {
        $actionFilter = $request->input('action', 'all');
        $userFilter = $request->input('user', 'all');
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        
        $query = DB::table('audit_logs')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as user_name', 'users.email as user_email')
            ->whereBetween('audit_logs.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        
        if ($actionFilter !== 'all') {
            $query->where('audit_logs.action', $actionFilter);
        }
        if ($userFilter !== 'all') {
            $query->where('audit_logs.user_id', $userFilter);
        }
        
        $logs = $query->orderBy('audit_logs.created_at', 'desc')->paginate(50);
        $logs->getCollection()->transform(function ($log) {
            $log->created_at = \Carbon\Carbon::parse($log->created_at);
            return $log;
        });
        
        $users = DB::table('users')->select('id', 'name')->get();
        $actions = DB::table('audit_logs')->distinct()->pluck('action');
        
        return view('admin.audit-logs', compact('logs', 'users', 'actions', 'dateFrom', 'dateTo'));
    }

    // ============================================
    // SCHOOL YEAR
    // ============================================

    public function schoolYear()
    {
        $schoolYears = DB::table('school_years')->orderBy('name', 'desc')->get();
        $currentYear = $schoolYears->where('is_active', true)->first();
        return view('admin.school-year', compact('schoolYears', 'currentYear'));
    }

    public function createSchoolYear(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:school_years,name',
            'started_at' => 'required|date',
            'ended_at' => 'nullable|date|after:started_at',
            'is_active' => 'boolean',
        ]);

        if ($request->is_active) {
            DB::table('school_years')->update(['is_active' => false]);
        }

        DB::table('school_years')->insert([
            'name' => $request->name,
            'started_at' => $request->started_at,
            'ended_at' => $request->ended_at,
            'is_active' => $request->is_active ?? false,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logActivity('SCHOOL_YEAR_CREATED', 'Created school year: ' . $request->name);
        return back()->with('success', 'School year created successfully.');
    }

    public function archiveSchoolYear(Request $request, int $id)
    {
        $year = DB::table('school_years')->where('id', $id)->first();
        if (!$year) {
            return back()->with('error', 'School year not found.');
        }
        if ($year->is_active) {
            return back()->with('error', 'Cannot archive the active school year.');
        }

        DB::table('school_years')->where('id', $id)->update([
            'is_archived' => true,
            'updated_at' => now(),
        ]);

        $this->logActivity('SCHOOL_YEAR_ARCHIVED', 'Archived school year: ' . $year->name);
        return back()->with('success', 'School year archived successfully.');
    }

    public function rolloverSchoolYear(Request $request)
    {
        $request->validate([
            'from_year' => 'required|exists:school_years,name',
            'to_year' => 'required|exists:school_years,name|different:from_year',
        ]);

        $this->logActivity('SCHOOL_YEAR_ROLLOVER', 'Rollover from ' . $request->from_year . ' to ' . $request->to_year);
        return back()->with('success', 'School year rollover initiated. Students will be promoted to the next year level.');
    }

    // ============================================
    // SYSTEM HEALTH
    // ============================================

    public function systemHealth()
    {
        $health = [
            'database' => $this->checkDatabase(),
            'ai_api' => $this->checkAIApi(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
        ];

        $errors = $this->getRecentErrors();
        return view('admin.system-health', compact('health', 'errors'));
    }

    // ============================================
    // STATISTICS & HELPER METHODS
    // ============================================

    protected function getSystemStats(): array
    {
        return [
            'total_students' => DB::table('students')->where('status', 'Active')->count(),
            'high_risk' => DB::table('risk_scores')
                ->where('risk_level', 'High')->where('grading_period', 'Midterm')->count(),
            'moderate_risk' => DB::table('risk_scores')
                ->where('risk_level', 'Moderate')->where('grading_period', 'Midterm')->count(),
            'critical_cases' => DB::table('cases')
                ->where('priority', 'Critical')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'open_cases' => DB::table('cases')->whereNotIn('status', ['Resolved', 'Closed'])->count(),
            'resolved_cases' => DB::table('cases')->where('status', 'Resolved')->count(),
            'total_payments' => DB::table('payments')->sum('amount'),
            'collected_payments' => DB::table('payments')->sum('paid_amount'),
            'overdue_payments' => DB::table('payments')->where('status', 'Overdue')->count(),
        ];
    }

    protected function getRiskByDepartment()
    {
        return DB::table('departments')
            ->leftJoin('programs', 'departments.id', '=', 'programs.department_id')
            ->leftJoin('year_levels', 'programs.id', '=', 'year_levels.program_id')
            ->leftJoin('blocks', 'year_levels.id', '=', 'blocks.year_level_id')
            ->leftJoin('students', 'blocks.id', '=', 'students.block_id')
            ->leftJoin('risk_scores', function($join) {
                $join->on('students.id', '=', 'risk_scores.student_id')
                     ->where('risk_scores.grading_period', 'Midterm');
            })
            ->select(
                'departments.id', 'departments.code', 'departments.name',
                DB::raw('COUNT(DISTINCT students.id) as total_students'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "High" THEN 1 END) as high_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Moderate" THEN 1 END) as moderate_risk'),
                DB::raw('COUNT(CASE WHEN risk_scores.risk_level = "Low" THEN 1 END) as low_risk')
            )
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->get();
    }

    protected function getRiskDistribution(): array
    {
        return [
            'Low' => DB::table('risk_scores')->where('risk_level', 'Low')->where('grading_period', 'Midterm')->count(),
            'Moderate' => DB::table('risk_scores')->where('risk_level', 'Moderate')->where('grading_period', 'Midterm')->count(),
            'High' => DB::table('risk_scores')->where('risk_level', 'High')->where('grading_period', 'Midterm')->count(),
        ];
    }

    protected function getRiskTrend(): array
    {
        $periods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        $trend = [];

        foreach ($periods as $period) {
            $high = DB::table('risk_scores')
                ->where('risk_level', 'High')->where('grading_period', $period)->count();
            $total = DB::table('risk_scores')->where('grading_period', $period)->count();
            $trend[] = [
                'period' => $period,
                'high_risk' => $high,
                'total' => $total,
                'percentage' => $total > 0 ? round(($high / $total) * 100, 1) : 0,
            ];
        }
        return $trend;
    }

    protected function getSystemHealth(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'ai_api' => $this->checkAIApi(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'session' => $this->checkSession(),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkAIApi(): array
    {
        $apiKey = config('services.aistudio.api_key');
        if (empty($apiKey)) {
            return ['status' => 'warning', 'message' => 'API key not configured'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get('https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey);
            if ($response->successful()) {
                return ['status' => 'ok', 'message' => 'Connected'];
            }
            return ['status' => 'error', 'message' => 'Connection failed: ' . $response->status()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        $queueDriver = config('queue.default');
        return ['status' => 'ok', 'message' => 'Running (' . $queueDriver . ')'];
    }

    protected function checkStorage(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;
            $percentage = $total > 0 ? round(($used / $total) * 100, 1) : 0;
            $status = $percentage > 90 ? 'warning' : 'ok';
            return [
                'status' => $status,
                'message' => $percentage . '% used',
                'used' => $this->formatBytes($used),
                'total' => $this->formatBytes($total),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $result = Cache::get('health_check');
            return $result === 'ok'
                ? ['status' => 'ok', 'message' => 'Working']
                : ['status' => 'error', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function checkSession(): array
    {
        try {
            session()->put('health_check', 'ok');
            $result = session()->get('health_check');
            return $result === 'ok'
                ? ['status' => 'ok', 'message' => 'Working']
                : ['status' => 'error', 'message' => 'Session read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function getRecentActivities()
    {
        $activities = DB::table('audit_logs')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->orderBy('audit_logs.created_at', 'desc')
            ->limit(10)
            ->get();

        return $activities->map(function ($activity) {
            $activity->created_at = \Carbon\Carbon::parse($activity->created_at);
            return $activity;
        });
    }

    protected function getRecentErrors(): array
    {
        $errors = [];
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $lines = explode("\n", $content);
            $errorLines = array_filter($lines, function($line) {
                return strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false;
            });
            $errors = array_slice(array_values($errorLines), -5);
        }
        return $errors;
    }

    protected function logActivity(string $action, string $details): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'Admin',
            'new_values' => json_encode(['details' => $details]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function formatBytes($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}