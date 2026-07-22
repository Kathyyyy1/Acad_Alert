<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterTeachersTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('master_teachers')->truncate();
        
        $users = DB::table('users')->where('role', 'master_teacher')->get();
        
        foreach ($users as $user) {
            // Get department based on email
            $departmentCode = strtoupper(explode('@', $user->email)[0]);
            $departmentCode = str_replace('MT.', '', $departmentCode);
            
            $department = DB::table('departments')->where('code', $departmentCode)->first();
            
            DB::table('master_teachers')->insert([
                'user_id' => $user->id,
                'department_id' => $department->id ?? 1,
                'employee_number' => 'MT-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                'specialization' => 'Information Technology',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info('Master teachers seeded successfully.');
    }
}