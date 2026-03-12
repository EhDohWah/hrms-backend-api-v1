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
            $table->string('organization', 10);
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('no action'); // Position reference
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('no action'); // Department reference
            $table->foreignId('section_department_id')->nullable()->constrained('section_departments')->nullOnDelete()->comment('Sub-department within department'); // Section department reference
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete()->comment('Organizational unit/site'); // Site/organizational location

            $table->string('pay_method')->comment('Transferred to bank, Cash cheque')->nullable(); // Optional - pay method

            $table->date('start_date'); // Required - when employment started
            $table->date('end_date')->nullable()->comment('Employment end date, set when resignation is acknowledged');
            $table->date('pass_probation_date')->nullable()->comment('First day employee receives pass_probation_salary - typically 3 months after start_date'); // Optional - when probation ends
            $table->date('end_probation_date')->nullable()->comment('Last day of probation period');
            $table->boolean('probation_required')->default(true)->comment('If false, employee skips probation and gets full benefits from day 1');

            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation
            $table->decimal('pass_probation_salary', 10, 2); // Required - regular salary
            $table->decimal('previous_year_salary', 10, 2)->nullable(); // System-managed: snapshot of salary before annual increase

            $table->boolean('health_welfare')->default(false)->comment('Health benefits flag (opt-in/out)'); // Required - health benefits flag
            $table->boolean('pvd')->default(false)->comment('Provident fund flag (opt-in/out)'); // Required - provident fund flag
            $table->boolean('saving_fund')->default(false)->comment('Saving fund flag (opt-in/out)'); // Required - saving fund flag
            $table->decimal('study_loan', 10, 2)->nullable()->default(0)->comment('Monthly study loan deduction');
            $table->decimal('retroactive_salary', 10, 2)->nullable()->default(0)->comment('Manual HR payroll correction: +ve=under-paid, -ve=over-paid');
            // NOTE: Benefit percentages are now managed globally in benefit_settings table
            // NOTE: Probation status is now tracked in probation_records table via event_type field
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['pass_probation_date', 'end_probation_date', 'end_date'], 'idx_transition_check');
            $table->index(['employee_id', 'end_date']);
            $table->index(['department_id', 'end_date']);
            $table->index('organization', 'idx_employments_organization');
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
