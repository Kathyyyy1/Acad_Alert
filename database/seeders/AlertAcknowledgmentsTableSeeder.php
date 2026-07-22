<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertAcknowledgmentsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('alert_acknowledgments')->truncate();
        
        $flags = DB::table('flags')->where('is_acknowledged', false)->get();
        $ackCount = 0;
        
        foreach ($flags as $flag) {
            // Simulate 50% acknowledgment rate
            if (rand(1, 100) <= 50) {
                DB::table('alert_acknowledgments')->insert([
                    'student_id' => $flag->student_id,
                    'flag_id' => $flag->id,
                    'acknowledged_at' => now(),
                    'ip_address' => '127.0.0.1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Update flag as acknowledged
                DB::table('flags')
                    ->where('id', $flag->id)
                    ->update(['is_acknowledged' => true]);
                
                $ackCount++;
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->command->info("$ackCount alert acknowledgments seeded successfully.");
    }
}