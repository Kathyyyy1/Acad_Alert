<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeacherAssignmentsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('teacher_assignments')->truncate();
        
        $masterTeachers = DB::table('master_teachers')->get();
        $blocks = DB::table('blocks')->get();
        $subjects = DB::table('subjects')->get();
        
        foreach ($masterTeachers as $teacher) {
            // Assign each master teacher to blocks in their department
            $departmentBlocks = [];
            foreach ($blocks as $block) {
                $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
                $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
                if ($program->department_id == $teacher->department_id) {
                    $departmentBlocks[] = $block;
                }
            }
            
            // Assign to first 2 blocks
            $assignedBlocks = array_slice($departmentBlocks, 0, 2);
            foreach ($assignedBlocks as $block) {
                // Get subjects for this block's year level and program
                $yearLevel = DB::table('year_levels')->where('id', $block->year_level_id)->first();
                $program = DB::table('programs')->where('id', $yearLevel->program_id)->first();
                
                $blockSubjects = DB::table('subjects')
                    ->where('program_id', $program->id)
                    ->where('year_level', $yearLevel->year_number)
                    ->where('semester', 1)
                    ->take(2)
                    ->get();
                
                foreach ($blockSubjects as $subject) {
                    DB::table('teacher_assignments')->insert([
                        'master_teacher_id' => $teacher->id,
                        'block_id' => $block->id,
                        'subject_id' => $subject->id,
                        'school_year' => '2024-2025',
                        'semester' => '1st',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Teacher assignments seeded successfully.');
    }
}