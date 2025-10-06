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
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('employment_type')->comment('Full-time, Part-time, Contract, Temporary'); // Required - type of employment
            $table->string('pay_method')->comment('Monthly, Weekly, Daily, Hourly')->nullable(); // Optional - pay method
            $table->date('probation_pass_date')->nullable()->comment('Typically 3 months after start_date - marks the end of probation period'); // Optional - when probation ends
            $table->date('start_date'); // Required - when employment started
            $table->date('end_date')->nullable(); // Optional - when employment ends (for contracts)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('no action'); // Department reference
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('no action'); // Position reference
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete(); // Required - work location
            $table->decimal('position_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation

            $table->boolean('health_welfare')->default(false); // Required - health benefits flag
            $table->decimal('health_welfare_percentage', 5, 2)->nullable()->comment('Health & Welfare percentage (0-100)'); // Optional - health benefits percentage
            $table->boolean('pvd')->default(false); // Required - provident fund flag
            $table->decimal('pvd_percentage', 5, 2)->nullable()->comment('PVD percentage (0-100)'); // Optional - provident fund percentage
            $table->boolean('saving_fund')->default(false); // Required - saving fund flag
            $table->decimal('saving_fund_percentage', 5, 2)->nullable()->comment('Saving Fund percentage (0-100)'); // Optional - saving fund percentage
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};
