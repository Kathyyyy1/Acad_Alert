<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubjectsTableSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('subjects')->truncate();
        Schema::enableForeignKeyConstraints();
        
        $csvFile = database_path('csv/subjects.csv');
        if (!file_exists($csvFile)) {
            $this->command->error("CSV file not found: $csvFile");
            $this->command->info("Please create subjects.csv in database/csv/ with all subject data.");
            return;
        }
        
        // Read CSV with proper handling
        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file);
        
        // Clean header - remove BOM if present and trim
        $header = array_map(function($item) {
            return trim(str_replace("\xEF\xBB\xBF", '', $item));
        }, $header);
        
        $subjectCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Fix: Ensure row has same number of columns as header
            $row = array_pad($row, count($header), '');
            
            // Truncate row if it has more columns than header
            if (count($row) > count($header)) {
                $row = array_slice($row, 0, count($header));
            }
            
            try {
                $data = array_combine($header, $row);
            } catch (\ValueError $e) {
                $this->command->warn("Skipping row: header has " . count($header) . " columns, row has " . count($row));
                $errorCount++;
                continue;
            }
            
            // Skip if required fields are empty
            if (empty($data['program_code']) || empty($data['subject_code']) || empty($data['subject_name'])) {
                $skippedCount++;
                continue;
            }
            
            // Get program_id from program code
            $program = DB::table('programs')->where('code', $data['program_code'])->first();
            if (!$program) {
                $this->command->warn("Program not found: {$data['program_code']} - skipping subject: {$data['subject_name']}");
                $skippedCount++;
                continue;
            }
            
            DB::table('subjects')->insert([
                'program_id' => $program->id,
                'year_level' => (int) $data['year_level'],
                'semester' => (int) $data['semester'],
                'subject_code' => $data['subject_code'],
                'subject_name' => $data['subject_name'],
                'units' => isset($data['units']) ? (int) $data['units'] : 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $subjectCount++;
        }
        
        fclose($file);
        
        $this->command->info("==================================================");
        $this->command->info(" $subjectCount subjects seeded successfully from CSV.");
        if ($skippedCount > 0) {
            $this->command->warn(" $skippedCount subjects skipped due to missing programs or empty data.");
        }
        if ($errorCount > 0) {
            $this->command->warn(" $errorCount rows had column mismatch errors and were skipped.");
        }
        $this->command->info("==================================================");
    }
}