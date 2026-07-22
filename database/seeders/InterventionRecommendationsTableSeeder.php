<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InterventionRecommendationsTableSeeder extends Seeder
{
    private $recommendations = [
        'Attend tutoring sessions for struggling subjects',
        'Schedule a meeting with your guidance counselor',
        'Improve attendance - attend all classes this week',
        'Submit missing assignments before deadline',
        'Join a study group for collaborative learning',
        'Meet with your subject teacher for additional help',
        'Create a study schedule and stick to it',
        'Take advantage of online learning resources',
        'Attend academic support workshops',
        'Schedule a parent-teacher conference'
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('intervention_recommendations')->truncate();
        
        $highRiskStudents = DB::table('risk_scores')
            ->where('risk_level', 'High')
            ->distinct('student_id')
            ->get(['student_id']);
        
        $recCount = 0;
        
        foreach ($highRiskStudents as $student) {
            $riskFactors = ['Low grades in major subjects', 'Poor attendance record'];
            $suggestedActions = array_rand(array_flip($this->recommendations), 3);
            
            DB::table('intervention_recommendations')->insert([
                'student_id' => $student->student_id,
                'risk_factors' => json_encode($riskFactors),
                'suggested_actions' => json_encode($suggestedActions),
                'generated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $recCount++;
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$recCount intervention recommendations seeded successfully.");
    }
}