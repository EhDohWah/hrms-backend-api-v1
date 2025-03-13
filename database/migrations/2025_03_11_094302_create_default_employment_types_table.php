<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert default employment types
        DB::table('employment_types')->insert([
            [
                'name' => 'Full time',
                'description' => 'Full time employment',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Part time',
                'description' => 'Part time employment',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Contract',
                'description' => 'Contract based employment',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Probation',
                'description' => 'Probationary period',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Intern',
                'description' => 'Internship position',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to create or drop the table as we're just inserting data
        // We can clear the inserted records if needed
        DB::table('employment_types')
            ->whereIn('name', ['Full time', 'Part time', 'Contract', 'Probation', 'Intern'])
            ->where('created_by', 'Migration')
            ->delete();
    }
};
