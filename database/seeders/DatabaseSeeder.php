<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========== STATIC DATA SEEDERS (No Dependencies) ==========
        $this->call(DepartmentsTableSeeder::class);
        $this->call(ProgramsTableSeeder::class);
        $this->call(SemestersTableSeeder::class);
        $this->call(AcademicCalendarTableSeeder::class);
        $this->call(SchoolYearsTableSeeder::class);
        $this->call(SystemSettingsTableSeeder::class);
        
        // ========== HIERARCHY SEEDERS ==========
        $this->call(YearLevelsTableSeeder::class);
        $this->call(BlocksTableSeeder::class);
        $this->call(SubjectsTableSeeder::class);
        
        // ========== USER SEEDERS ==========
        $this->call(UsersTableSeeder::class);              // Admin user created here
        $this->call(MasterTeachersTableSeeder::class);
        $this->call(CounselorsTableSeeder::class);
        
        // ========== RISK THRESHOLDS (Requires admin user) ==========
        $this->call(RiskThresholdsTableSeeder::class);     // MOVED HERE - after UsersTableSeeder
        
        // ========== STUDENT & PARENT SEEDERS ==========
        $this->call(StudentsTableSeeder::class);
        $this->call(ParentsTableSeeder::class);
        
        // ========== TEACHER ASSIGNMENTS ==========
        $this->call(TeacherAssignmentsTableSeeder::class);
        
        // ========== ACADEMIC DATA SEEDERS ==========
        $this->call(GradesTableSeeder::class);
        $this->call(AttendanceTableSeeder::class);
        $this->call(AttendanceWarningsTableSeeder::class);
        
        // ========== RISK SCORES ==========
        $this->call(RiskScoresTableSeeder::class);
        $this->call(FlagsTableSeeder::class);
        $this->call(InterventionRecommendationsTableSeeder::class);
        
        // ========== CASES & ESCALATIONS ==========
        $this->call(CasesTableSeeder::class);
        $this->call(EscalationsTableSeeder::class);
        $this->call(CaseSessionsTableSeeder::class);
        
        // ========== STUDENT INTERACTIONS ==========
        $this->call(AlertAcknowledgmentsTableSeeder::class);
        
        // ========== FINANCIAL SEEDERS ==========
        $this->call(PaymentsTableSeeder::class);
        $this->call(PaymentHistoryTableSeeder::class);
        
        // ========== SYSTEM SEEDERS ==========
        $this->call(AuditLogsTableSeeder::class);
        
        // ========== OPTIONAL V2 SEEDERS ==========
        // $this->call(RiskOverridesTableSeeder::class);
        // $this->call(StudentRecommendationTrackingTableSeeder::class);
        // $this->call(PermissionsTableSeeder::class);
    }
}