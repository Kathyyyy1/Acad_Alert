<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DepartmentHelper
{
    /**
     * Get the department ID for the current Master Teacher or Counselor.
     */
    public static function getDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    /**
     * Get the Master Teacher object for the current user.
     */
    public static function getMasterTeacher(Request $request): ?object
    {
        return $request->attributes->get('master_teacher');
    }

    /**
     * Get the Counselor object for the current user.
     */
    public static function getCounselor(Request $request): ?object
    {
        return $request->attributes->get('counselor');
    }

    /**
     * Check if the current user is a Master Teacher with a department.
     */
    public static function isMasterTeacherWithDepartment(Request $request): bool
    {
        return self::getDepartmentId($request) !== null && self::getMasterTeacher($request) !== null;
    }

    /**
     * Check if the current user is a Counselor with a department.
     */
    public static function isCounselorWithDepartment(Request $request): bool
    {
        return self::getDepartmentId($request) !== null && self::getCounselor($request) !== null;
    }

    /**
     * Get all programs for the Master Teacher's or Counselor's department.
     */
    public static function getDepartmentPrograms(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        return DB::table('programs')
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Get all blocks for the Master Teacher's or Counselor's department.
     */
    public static function getDepartmentBlocks(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        return DB::table('blocks')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('programs.department_id', $departmentId)
            ->select('blocks.*', 'year_levels.year_number', 'programs.code as program_code')
            ->orderBy('programs.code')
            ->orderBy('year_levels.year_number')
            ->orderBy('blocks.block_number')
            ->get()
            ->toArray();
    }

    /**
     * Get students for a specific block (with department isolation).
     */
    public static function getBlockStudents(Request $request, int $blockId): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        $students = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('blocks.id', $blockId)
            ->where('programs.department_id', $departmentId)
            ->select(
                'students.*',
                'blocks.name as block_name',
                'year_levels.year_number',
                'programs.code as program_code'
            )
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->toArray();

        return $students;
    }

    /**
     * Check if a block belongs to the Master Teacher's or Counselor's department.
     */
    public static function isBlockInDepartment(Request $request, int $blockId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $block = DB::table('blocks')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('blocks.id', $blockId)
            ->where('programs.department_id', $departmentId)
            ->first();

        return $block !== null;
    }

    /**
     * Check if a program belongs to the Master Teacher's or Counselor's department.
     */
    public static function isProgramInDepartment(Request $request, int $programId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $program = DB::table('programs')
            ->where('id', $programId)
            ->where('department_id', $departmentId)
            ->first();

        return $program !== null;
    }

    /**
     * Check if a student belongs to the Master Teacher's or Counselor's department.
     */
    public static function isStudentInDepartment(Request $request, int $studentId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $student = DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('students.id', $studentId)
            ->where('programs.department_id', $departmentId)
            ->first();

        return $student !== null;
    }

    /**
     * Check if a case belongs to a counselor's department.
     */
    public static function isCaseInCounselorDepartment(Request $request, int $caseId): bool
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return false;
        }

        $case = DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.id', $caseId)
            ->where('programs.department_id', $departmentId)
            ->first();

        return $case !== null;
    }

    /**
     * Get the counselor's department ID.
     */
    public static function getCounselorDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    /**
     * Get all students for a department.
     */
    public static function getDepartmentStudents(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        return DB::table('students')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('programs.department_id', $departmentId)
            ->where('students.status', 'Active')
            ->select(
                'students.*',
                'blocks.name as block_name',
                'year_levels.year_number',
                'programs.code as program_code'
            )
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->get()
            ->toArray();
    }

    /**
     * Get all cases for a counselor's department.
     */
    public static function getDepartmentCases(Request $request): array
    {
        $departmentId = self::getDepartmentId($request);
        if (!$departmentId) {
            return [];
        }

        $counselor = self::getCounselor($request);
        if (!$counselor) {
            return [];
        }

        return DB::table('cases')
            ->join('students', 'cases.student_id', '=', 'students.id')
            ->join('blocks', 'students.block_id', '=', 'blocks.id')
            ->join('year_levels', 'blocks.year_level_id', '=', 'year_levels.id')
            ->join('programs', 'year_levels.program_id', '=', 'programs.id')
            ->where('cases.counselor_id', $counselor->id)
            ->where('programs.department_id', $departmentId)
            ->select('cases.*', 'students.first_name', 'students.last_name', 'students.student_number')
            ->orderByRaw("FIELD(cases.priority, 'Critical', 'High', 'Medium', 'Low')")
            ->orderBy('cases.updated_at', 'desc')
            ->get()
            ->toArray();
    }
}