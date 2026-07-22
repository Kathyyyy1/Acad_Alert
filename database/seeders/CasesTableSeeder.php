<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CasesTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('cases')->truncate();
        
        $flags = DB::table('flags')
            ->where('severity', 'critical')
            ->orWhere('severity', 'high')
            ->get();
        
        $counselors = DB::table('counselors')->get();
        
        $caseCount = 0;
        
        foreach ($flags as $flag) {
            $counselor = $counselors->random();
            $riskScore = DB::table('risk_scores')
                ->where('student_id', $flag->student_id)
                ->where('grading_period', $flag->grading_period)
                ->first();
            
            DB::table('cases')->insert([
                'student_id' => $flag->student_id,
                'counselor_id' => $counselor->id,
                'escalated_by' => 2, // Assume first master teacher
                'escalated_at' => now(),
                'school_year' => '2024-2025',
                'semester' => '1st',
                'priority' => $flag->severity === 'critical' ? 'Critical' : 'High',
                'status' => 'New',
                'risk_level_at_escalation' => 'High',
                'risk_score_at_escalation' => $riskScore->risk_score ?? 85,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $caseCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$caseCount cases seeded successfully.");
    }
}