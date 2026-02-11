<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status', 20)->default('Present');
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('employee_id', 'idx_attendance_employee');
            $table->index('date', 'idx_attendance_date');
            $table->index('status', 'idx_attendance_status');
        });

        // CHECK constraint for status (SQL Server compatible — no enum)
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT chk_attendance_status CHECK (status IN ('Present', 'Absent', 'Late', 'Half Day', 'On Leave'))");

        // Unique constraint: one attendance record per employee per date
        DB::statement('CREATE UNIQUE INDEX uq_attendance_employee_date ON attendances (employee_id, date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
