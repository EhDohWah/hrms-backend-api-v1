<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the holiday_compensation_records table for tracking when employees
     * work on public holidays and earn compensation days to use later.
     */
    public function up(): void
    {
        Schema::create('holiday_compensation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('holiday_id')->constrained('holidays');
            $table->date('worked_date')->comment('The holiday date the employee worked');
            $table->decimal('compensation_days', 3, 1)->default(1.0)->comment('Days earned for working');
            $table->decimal('used_days', 3, 1)->default(0.0)->comment('Days already taken as leave');
            $table->decimal('remaining_days', 3, 1)->default(1.0)->comment('Available compensation days');
            $table->date('expiry_date')->nullable()->comment('Optional expiry for compensation days');
            $table->string('status', 50)->default('available')
                ->comment('available, partially_used, exhausted, expired');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();

            // One record per employee per holiday
            $table->unique(['employee_id', 'holiday_id'], 'hcr_employee_holiday_unique');

            // Index for finding available compensation
            $table->index(['employee_id', 'status'], 'hcr_employee_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday_compensation_records');
    }
};
