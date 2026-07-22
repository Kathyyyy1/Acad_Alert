<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait DepartmentIsolationTrait
{
    /**
     * Get the current Master Teacher's department ID from the request.
     */
    protected function getDepartmentId(Request $request): ?int
    {
        return $request->attributes->get('department_id');
    }

    /**
     * Get the current Master Teacher object from the request.
     */
    protected function getMasterTeacher(Request $request): ?object
    {
        return $request->attributes->get('master_teacher');
    }

    /**
     * Check if a student belongs to the Master Teacher's department.
     */
    protected function isStudentInDepartment(Request $request, $studentId): bool
    {
        $departmentId = $this->getDepartmentId($request);
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
     * Filter a query to only show records for the Master Teacher's department.
     * This is useful for Eloquent queries.
     */
    protected function applyDepartmentFilter(Request $request, $query)
    {
        $departmentId = $this->getDepartmentId($request);
        if (!$departmentId) {
            return $query;
        }

        // For students through blocks -> year_levels -> programs -> department
        return $query->whereHas('block.yearLevel.program', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }

    /**
     * Get all department IDs for the Master Teacher.
     * (Returns only their department)
     */
    protected function getDepartmentIds(Request $request): array
    {
        $departmentId = $this->getDepartmentId($request);
        return $departmentId ? [$departmentId] : [];
    }
}