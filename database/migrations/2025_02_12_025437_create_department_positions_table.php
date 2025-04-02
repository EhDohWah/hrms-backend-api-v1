<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('department_positions', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('position');
            $table->string('report_to')->nullable()->comment('Name or identifier of the manager position');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });

        // Insert default department positions
        DB::table('department_positions')->insert([
            ['department' => 'IT', 'position' => 'Head of Dept.', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'System Administrator', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'DM', 'position' => 'Head of Dept.', 'report_to' => null, 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'DM', 'position' => 'Data Entry', 'report_to' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'DM', 'position' => 'Data Analytics Manager', 'report_to' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'DM', 'position' => 'Data Collection Officer', 'report_to' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['department' => 'IT', 'position' => 'Help Desk', 'report_to' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_positions');
    }
};
