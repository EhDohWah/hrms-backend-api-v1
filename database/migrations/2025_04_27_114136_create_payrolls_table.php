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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            // Foreign Keys with cascading update, restrict on delete for data integrity
            $table->foreignId('employment_id')
                ->constrained('employments')
                ->cascadeOnUpdate()
                ->noActionOnDelete();

            $table->foreignId('employee_funding_allocation_id')
                ->constrained('employee_funding_allocations')
                ->cascadeOnUpdate()
                ->noActionOnDelete();

            $table->string('organization', 10)->nullable()
                ->comment('Org snapshot at generation time. Never changes after creation.');

            // Snapshot fields (immutable after creation — point-in-time data)
            $table->string('snapshot_staff_id', 50)->nullable()
                ->comment('Snapshot: Employee staff ID at payroll time');
            $table->string('snapshot_employee_name', 255)->nullable()
                ->comment('Snapshot: "FirstName LastName" at payroll time');
            $table->string('snapshot_department', 255)->nullable()
                ->comment('Snapshot: Department name at payroll time');
            $table->string('snapshot_position', 255)->nullable()
                ->comment('Snapshot: Position title at payroll time');
            $table->string('snapshot_site', 255)->nullable()
                ->comment('Snapshot: Site name at payroll time');
            $table->string('snapshot_grant_code', 50)->nullable()
                ->comment('Snapshot: Grant code at payroll time');
            $table->string('snapshot_grant_name', 255)->nullable()
                ->comment('Snapshot: Grant name at payroll time');
            $table->string('snapshot_budget_line_code', 50)->nullable()
                ->comment('Snapshot: Budget line code at payroll time');
            $table->decimal('snapshot_fte', 5, 4)->nullable()
                ->comment('Snapshot: FTE decimal (0.60 = 60%) at payroll time');

            // Encrypted payroll fields (cast in model; use decimal as recommended for salary, stored as string for encryption compatibility)
            $table->text('gross_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('gross_salary_by_FTE')->comment('Required Encryption. TYPE - decimal()');
            $table->text('retroactive_salary')->nullable()->comment('Required Encryption. TYPE - decimal(). Manual HR payroll correction: +ve=under-paid, -ve=over-paid');
            $table->text('thirteen_month_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('thirteen_month_salary_accured')->comment('Required Encryption');
            $table->text('pvd')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('saving_fund')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('study_loan')->nullable()->comment('Required Encryption. TYPE - decimal(). Monthly study loan deduction');
            $table->text('employer_social_security')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employee_social_security')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employer_health_welfare')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employee_health_welfare')->comment('Required Encryption. TYPE - decimal()');
            $table->text('tax')->comment('Required Encryption. TYPE - decimal()');
            $table->text('net_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_pvd')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_saving_fund')->comment('Required Encryption. TYPE - decimal()');
            $table->text('salary_increase')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('total_income')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employer_contribution')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_deduction')->comment('Required Encryption. TYPE - decimal()');

            // Non-encrypted notes (plain text, for payslip display)
            $table->text('notes')->nullable()->comment('Notes for the payslip.');

            // Pay period date
            $table->date('pay_period_date');

            // Timestamps, for auditability
            $table->timestamps();

            // Indexes for FK columns (SQL Server does NOT auto-create these)
            $table->index('employment_id', 'idx_payrolls_employment_id');
            $table->index('employee_funding_allocation_id', 'idx_payrolls_efa_id');
            $table->index('pay_period_date', 'idx_payrolls_pay_period');
            $table->index('organization', 'idx_payrolls_organization');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
