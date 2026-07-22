<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RiskScoresTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('risk_scores')->truncate();
        
        $students = DB::table('students')->get();
        $gradingPeriods = ['Prelim', 'Midterm', 'Semifinal', 'Finals'];
        
        $riskCount = 0;
        
        foreach ($students as $student) {
            // Get average grade and attendance rate
            $grades = DB::table('grades')->where('student_id', $student->id)->get();
            $attendance = DB::table('attendance_summaries')->where('student_id', $student->id)->get();
            
            foreach ($gradingPeriods as $period) {
                $periodGrades = $grades->where('grading_period', $period);
                $avgGrade = $periodGrades->avg('numerical_grade') ?? 75;
                
                $periodAttendance = $attendance->where('grading_period', $period)->first();
                $attendanceRate = $periodAttendance->attendance_rate ?? 85;
                
                // Calculate risk score (60% grade, 40% attendance)
                $riskScore = round(($avgGrade * 0.6) + ($attendanceRate * 0.4));
                
                // Determine risk level
                if ($riskScore >= 71) {
                    $riskLevel = 'High';
                } elseif ($riskScore >= 41) {
                    $riskLevel = 'Moderate';
                } else {
                    $riskLevel = 'Low';
                }
                
                DB::table('risk_scores')->insert([
                    'student_id' => $student->id,
                    'grading_period' => $period,
                    'school_year' => '2024-2025',
                    'semester' => '1st',
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'risk_factors' => json_encode([]),
                    'scoring_method' => 'fallback',
                    'processing_status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $riskCount++;
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$riskCount risk scores seeded successfully.");
    }
}