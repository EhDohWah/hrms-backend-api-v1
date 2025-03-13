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
        // Insert default positions
        DB::table('positions')->insert([
            [
                'title' => 'Medic',
                'description' => 'Provides medical care and treatment to patients',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'title' => 'Midwife',
                'description' => 'Provides care and support to women during pregnancy, labor, and after birth',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'title' => 'Healthworker',
                'description' => 'Provides basic healthcare services and education to communities',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'title' => 'HR-assistant',
                'description' => 'Supports HR department with administrative tasks and employee relations',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'title' => 'Driver',
                'description' => 'Provides transportation services for staff and equipment',
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
        // No need to drop the table as we're just inserting data
        // We can clear the inserted records if needed
        DB::table('positions')->whereIn('title', ['Medic', 'Midwife', 'Healthworker', 'HR-assistant', 'Driver'])
            ->where('created_by', 'Migration')
            ->delete();
    }
};
