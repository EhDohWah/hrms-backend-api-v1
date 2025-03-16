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
        // Insert default work locations
        DB::table('work_locations')->insert([
            [
                'name' => 'MKT',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'WPA',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'MSL',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'MRM',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'MRMTB',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'KKTB',
                'type' => 'Site',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Headquarters',
                'type' => 'Office',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Field Office',
                'type' => 'Office',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 'Migration',
                'updated_by' => 'Migration'
            ],
            [
                'name' => 'Mobile Clinic',
                'type' => 'Mobile',
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
        // Delete all default work locations created by this migration
        DB::table('work_locations')
            ->whereIn('name', [
                'MKT', 'WPA', 'MSL', 'MRM', 'MRMTB', 'KKTB',
                'Headquarters', 'Field Office', 'Mobile Clinic'
            ])
            ->where('created_by', 'Migration')
            ->delete();
    }
};
