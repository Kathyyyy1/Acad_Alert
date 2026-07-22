# File Tree: acadalerts_step17_fail

**Generated:** 7/19/2026, 4:02:10 AM
**Root Path:** `c:\xampp\htdocs\acadalerts_step17_fail`

```
├── app
│   ├── Console
│   │   └── Commands
│   ├── Helpers
│   │   └── DepartmentHelper.php
│   ├── Http
│   │   ├── Controllers
│   │   │   ├── Admin
│   │   │   │   └── DashboardController.php
│   │   │   ├── Api
│   │   │   │   ├── AdminChartController.php
│   │   │   │   ├── CounselorChartController.php
│   │   │   │   ├── StudentChartController.php
│   │   │   │   └── TeacherChartController.php
│   │   │   ├── Auth
│   │   │   │   └── LoginController.php
│   │   │   ├── Counselor
│   │   │   │   └── DashboardController.php
│   │   │   ├── MasterTeacher
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── EscalationController.php
│   │   │   │   ├── InterventionController.php
│   │   │   │   └── RiskScoringController.php
│   │   │   ├── Student
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── RecommendationController.php
│   │   │   └── Controller.php
│   │   ├── Middleware
│   │   │   ├── DepartmentIsolationMiddleware.php
│   │   │   └── RoleMiddleware.php
│   │   └── Traits
│   │       └── DepartmentIsolationTrait.php
│   ├── Jobs
│   │   └── ProcessRiskScoringJob.php
│   ├── Models
│   │   ├── AcademicCalendar.php
│   │   ├── AlertAcknowledgement.php
│   │   ├── Attendance.php
│   │   ├── AttendanceSummary.php
│   │   ├── AttendanceWarning.php
│   │   ├── AuditLog.php
│   │   ├── Block.php
│   │   ├── CaseSession.php
│   │   ├── Counselor.php
│   │   ├── Department.php
│   │   ├── Escalation.php
│   │   ├── Flag.php
│   │   ├── Grade.php
│   │   ├── InterventionRecommendation.php
│   │   ├── MasterTeacher.php
│   │   ├── ParentModel.php
│   │   ├── Payment.php
│   │   ├── PaymentHistory.php
│   │   ├── Permission.php
│   │   ├── Program.php
│   │   ├── RiskOverride.php
│   │   ├── RiskScore.php
│   │   ├── RiskThreshold.php
│   │   ├── SchoolYear.php
│   │   ├── Semester.php
│   │   ├── Student.php
│   │   ├── StudentCase.php
│   │   ├── StudentRecommendationTracking.php
│   │   ├── Subject.php
│   │   ├── SystemSetting.php
│   │   ├── TeacherAssignment.php
│   │   ├── User.php
│   │   └── YearLevel.php
│   ├── Providers
│   │   └── AppServiceProvider.php
│   └── Services
│       ├── AIStudioApiService.php
│       ├── FallbackRiskScoringService.php
│       ├── InterventionService.php
│       └── RiskScoringService.php
├── bootstrap
│   ├── app.php
│   └── providers.php
├── config
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   ├── mail.php
│   ├── queue.php
│   ├── services.php
│   └── session.php
├── database
│   ├── csv
│   │   ├── academic_calendar.csv
│   │   ├── departments.csv
│   │   ├── programs.csv
│   │   ├── school_years.csv
│   │   ├── subjects.csv
│   │   └── system_settings.csv
│   ├── factories
│   │   └── UserFactory.php
│   ├── migrations
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_06_13_063349_create_departments_table.php
│   │   ├── 2026_06_13_063349_create_programs_table.php
│   │   ├── 2026_06_13_063350_create_year_levels_table.php
│   │   ├── 2026_06_13_063351_create_blocks_table.php
│   │   ├── 2026_06_13_063351_create_semesters_table.php
│   │   ├── 2026_06_13_063352_create_subjects_table.php
│   │   ├── 2026_06_13_063353_create_students_table.php
│   │   ├── 2026_06_13_063354_add_role_to_users_table.php
│   │   ├── 2026_06_13_063354_create_parents_table.php
│   │   ├── 2026_06_13_063355_create_master_teachers_table.php
│   │   ├── 2026_06_13_063356_create_counselors_table.php
│   │   ├── 2026_06_13_063357_create_grades_table.php
│   │   ├── 2026_06_13_063357_create_teacher_assignments_table.php
│   │   ├── 2026_06_13_063358_create_attendance_table.php
│   │   ├── 2026_06_13_063359_create_attendance_summaries_table.php
│   │   ├── 2026_06_13_063400_create_attendance_warnings_table.php
│   │   ├── 2026_06_13_063400_create_risk_scores_table.php
│   │   ├── 2026_06_13_063401_create_flags_table.php
│   │   ├── 2026_06_13_063402_create_intervention_recommendations_table.php
│   │   ├── 2026_06_13_063402_create_risk_thresholds_table.php
│   │   ├── 2026_06_13_063403_create_risk_overrides_table.php
│   │   ├── 2026_06_13_063404_create_cases_table.php
│   │   ├── 2026_06_13_063405_create_case_sessions_table.php
│   │   ├── 2026_06_13_063405_create_escalations_table.php
│   │   ├── 2026_06_13_063406_create_alert_acknowledgments_table.php
│   │   ├── 2026_06_13_063407_create_student_recommendation_tracking_table.php
│   │   ├── 2026_06_13_063408_create_school_years_table.php
│   │   ├── 2026_06_13_063408_create_system_settings_table.php
│   │   ├── 2026_06_13_063409_create_audit_logs_table.php
│   │   ├── 2026_06_13_063410_create_permissions_table.php
│   │   ├── 2026_06_13_063411_create_payment_history_table.php
│   │   ├── 2026_06_13_063417_create_academic_calendar_table.php
│   │   ├── 2026_06_13_072331_create_payments_table.php
│   │   ├── 2026_07_15_032417_update_flag_type_enum_in_flags_table.php
│   │   └── Untitled-1.md
│   ├── seeders
│   │   ├── AcademicCalendarTableSeeder.php
│   │   ├── AlertAcknowledgmentsTableSeeder.php
│   │   ├── AttendanceTableSeeder.php
│   │   ├── AttendanceWarningsTableSeeder.php
│   │   ├── AuditLogsTableSeeder.php
│   │   ├── BlocksTableSeeder.php
│   │   ├── CaseSessionsTableSeeder.php
│   │   ├── CasesTableSeeder.php
│   │   ├── CounselorsTableSeeder.php
│   │   ├── DatabaseSeeder.php
│   │   ├── DepartmentsTableSeeder.php
│   │   ├── EscalationsTableSeeder.php
│   │   ├── FlagsTableSeeder.php
│   │   ├── GradesTableSeeder.php
│   │   ├── InterventionRecommendationsTableSeeder.php
│   │   ├── MasterTeachersTableSeeder.php
│   │   ├── ParentsTableSeeder.php
│   │   ├── PaymentHistoryTableSeeder.php
│   │   ├── PaymentsTableSeeder.php
│   │   ├── ProgramsTableSeeder.php
│   │   ├── RiskScoresTableSeeder.php
│   │   ├── RiskThresholdsTableSeeder.php
│   │   ├── SchoolYearsTableSeeder.php
│   │   ├── SemestersTableSeeder.php
│   │   ├── StudentsTableSeeder.php
│   │   ├── SubjectsTableSeeder.php
│   │   ├── SystemSettingsTableSeeder.php
│   │   ├── TeacherAssignmentsTableSeeder.php
│   │   ├── UsersTableSeeder.php
│   │   └── YearLevelsTableSeeder.php
│   └── .gitignore
├── public
│   ├── css
│   │   └── app.css
│   ├── js
│   │   ├── charts
│   │   │   ├── admin-charts.js
│   │   │   ├── chart-config.js
│   │   │   ├── chart-init.js
│   │   │   ├── counselor-charts.js
│   │   │   ├── student-charts.js
│   │   │   └── teacher-charts.js
│   │   └── app.js
│   ├── .htaccess
│   ├── favicon.ico
│   ├── index.php
│   └── robots.txt
├── resources
│   └── views
│       ├── admin
│       │   ├── academic-edit-department.blade.php
│       │   ├── academic-edit-program.blade.php
│       │   ├── academic-edit-subject.blade.php
│       │   ├── academic.blade.php
│       │   ├── audit-logs.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── payments.blade.php
│       │   ├── risk-config.blade.php
│       │   ├── school-year.blade.php
│       │   ├── system-health.blade.php
│       │   ├── users-create.blade.php
│       │   ├── users-edit.blade.php
│       │   └── users.blade.php
│       ├── auth
│       │   └── login.blade.php
│       ├── counselor
│       │   ├── case.blade.php
│       │   └── dashboard.blade.php
│       ├── layouts
│       │   └── app.blade.php
│       ├── master-teacher
│       │   ├── block.blade.php
│       │   ├── blocks.blade.php
│       │   ├── department.blade.php
│       │   ├── recommendation-edit.blade.php
│       │   └── recommendations.blade.php
│       └── student
│           ├── attendance.blade.php
│           ├── counselor.blade.php
│           ├── dashboard.blade.php
│           ├── grades.blade.php
│           └── recommendations.blade.php
├── routes
│   ├── api.php
│   ├── console.php
│   └── web.php
├── storage
│   ├── app
│   │   ├── private
│   │   │   └── .gitignore
│   │   ├── public
│   │   │   └── .gitignore
│   │   └── .gitignore
│   ├── framework
│   │   ├── sessions
│   │   │   └── .gitignore
│   │   ├── testing
│   │   │   └── .gitignore
│   │   ├── views
│   │   │   ├── .gitignore
│   │   │   ├── 079a09bb970b0611789d43e6eaf40f10.php
│   │   │   ├── 09953d047e0f763b0fbb3afdca77a175.php
│   │   │   ├── 108f4f0d57254c471678a14fbbaa1d3b.php
│   │   │   ├── 10e0fe29ed12417c12a8bb99c0c67ac2.php
│   │   │   ├── 1824d1bc3f59af7e68f6034ac2bbaffb.php
│   │   │   ├── 19e6a3cbfb430ace9c4b13989644bfdf.php
│   │   │   ├── 1c672bcfd62576f2ead88a374b85e0f8.php
│   │   │   ├── 1da6a756568fb8e44335f88adccec6b9.php
│   │   │   ├── 1e463193950ce8149db156168c5ca87f.php
│   │   │   ├── 1e9f22b09c74db63ac23c6590f626348.php
│   │   │   ├── 2538721387772e0f8fe104e106dfb48b.php
│   │   │   ├── 26aba744240d24d1212570dbcfb58f41.php
│   │   │   ├── 28f1e08a93d6867fe0e8e35f8bc5266b.php
│   │   │   ├── 2a6f0bf88c95db1e1093a3e354641d36.php
│   │   │   ├── 33ca37bef1c34e956861f518c77bf0b6.php
│   │   │   ├── 3d227a01355c1ad11d57bc9ba2959974.php
│   │   │   ├── 3d3fbf2b8b97a3c4fa45d568d34e6ec3.php
│   │   │   ├── 42034a67b98c8912b983a862fa958640.php
│   │   │   ├── 42df6214a7ac58c958b212c77dbf3c2b.php
│   │   │   ├── 473ef4b15a05f602934d9514a0a2a2c8.php
│   │   │   ├── 48c9b06099cf782cfe443742683e2c5e.php
│   │   │   ├── 4a99b48f0d09310c882e5e26f3068919.php
│   │   │   ├── 5863eab71238f5ff40ceaa43e98dfaa6.php
│   │   │   ├── 5de5483e39963984a5948d1f7ad96618.php
│   │   │   ├── 5f788d5fd30a30b40655bcb9f5f27885.php
│   │   │   ├── 63dd9fb7b096ccea1c398344d9e956d7.php
│   │   │   ├── 64c1d5afbdfecfab4f2872548894dd61.php
│   │   │   ├── 72f66420795b1954105c92dcdf165534.php
│   │   │   ├── 757057bb684054907c4d2522656bfbb2.php
│   │   │   ├── 7790ab3bde941a561e93d3003763ba64.php
│   │   │   ├── 81e46ea8aab87c5e3c884c15ba87c045.php
│   │   │   ├── 82c16aa2818482039014160be9c40ba8.php
│   │   │   ├── 8586d449160f32281d5f96e11845de4d.php
│   │   │   ├── 8a846ded138bd7f4da924cbfaa011050.php
│   │   │   ├── 8acd029050ae69fb954d5c1c16bbf8e9.php
│   │   │   ├── 8bdb299e7aba32f4309c8be0b2f137ad.php
│   │   │   ├── 8e39eb52d6d19a10ea2b8286adc009cf.php
│   │   │   ├── 993fb1b596f614d87a2fdea8aa51bba6.php
│   │   │   ├── 99aa28e3c4261f7eacca6a433e79e033.php
│   │   │   ├── 9bf9be87a47d12b6f6dfbb0fc0e56775.php
│   │   │   ├── 9c5b8e46df4f3d7c37779c3de39ab108.php
│   │   │   ├── 9eed21cc571c2de2e9a0896e1a2cc351.php
│   │   │   ├── 9fefbcc4f8e61658ce7cc51a46a7aac7.php
│   │   │   ├── a3674b4fed75b7e0cb56df4fd02379bb.php
│   │   │   ├── a4a1c8c1c392141cb97a5b57d680df61.php
│   │   │   ├── ac1dd8db41e821753f404428d4015ee6.php
│   │   │   ├── af727ab4e016b8b0e3864243ad1e340f.php
│   │   │   ├── b7c0ebea4c3d7aa345051308d2edf4fd.php
│   │   │   ├── bd135b330ee0f271346f25972f2e2b8f.php
│   │   │   ├── bf24c07db4a391871022a3925ad7fc92.php
│   │   │   ├── c3af4d8ea72243849a7dd8847c5d8777.php
│   │   │   ├── cbafc98de35396655dab30a8a8007355.php
│   │   │   ├── d250acab15525cce95f05aeb3631bcce.php
│   │   │   ├── d7981d85ae33e52d0a73ec93035fa3a5.php
│   │   │   ├── d9deb9247931742276b31df8fe27d38d.php
│   │   │   ├── de766c325792e5dc4e41491cb2be9d6c.php
│   │   │   ├── e0576ccc3b66879961be4783b5f14268.php
│   │   │   ├── e264c46b95bd62f3a2687c7db688f605.php
│   │   │   ├── e58a20da7ef35335f5db48fbdf28574a.php
│   │   │   ├── e88328786d87f33e8c824dea67a1d458.php
│   │   │   ├── efef03aff8bcca688de425faff26d9a2.php
│   │   │   ├── f15c7d1dd59655cbd4e28880b968f6ac.php
│   │   │   ├── f4e9fca95291cf17438c71e6ab038de6.php
│   │   │   └── fcf6fdc286452e33a4d18d7889581b0c.php
│   │   └── .gitignore
│   └── logs
│       └── .gitignore
├── tests
│   ├── Feature
│   │   └── ExampleTest.php
│   ├── Unit
│   │   └── ExampleTest.php
│   └── TestCase.php
├── .editorconfig
├── .env.example
├── .gitattributes
├── .gitignore
├── README.md
├── Untitled-1.md
├── Untitled-2.md
├── artisan
├── composer.json
└── phpunit.xml
```

---
*Generated by FileTree Pro Extension*