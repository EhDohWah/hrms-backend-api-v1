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
            $table->date('pass_probation_date')->nullable()->comment('First day employee receives pass_probation_salary - typically 3 months after start_date'); // Optional - when probation ends
            $table->date('start_date'); // Required - when employment started
            $table->date('end_date')->nullable(); // Optional - when employment ends (for contracts)
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('no action'); // Department reference
            $table->foreignId('section_department_id')->nullable()->constrained('section_departments')->nullOnDelete()->comment('Sub-department within department'); // Section department reference
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('no action'); // Position reference
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete()->comment('Organizational unit/site'); // Site/organizational location
            $table->string('section_department')->nullable(); // Legacy text field - retained for migration compatibility
            $table->decimal('pass_probation_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation

            $table->boolean('health_welfare')->default(false)->comment('Health benefits flag (opt-in/out)'); // Required - health benefits flag
            $table->boolean('pvd')->default(false)->comment('Provident fund flag (opt-in/out)'); // Required - provident fund flag
            $table->boolean('saving_fund')->default(false)->comment('Saving fund flag (opt-in/out)'); // Required - saving fund flag
            // NOTE: Benefit percentages are now managed globally in benefit_settings table
            $table->boolean('status')->default(true)->comment('Employment status: true=Active, false=Inactive'); // Required - employment status
            // NOTE: Probation status is now tracked in probation_records table via event_type field
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['pass_probation_date', 'end_date', 'status'], 'idx_transition_check');
            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['section_department_id', 'status']);
            $table->index(['site_id', 'status']);
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
