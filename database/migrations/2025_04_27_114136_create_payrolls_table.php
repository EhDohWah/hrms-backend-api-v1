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

            // Encrypted payroll fields (cast in model; use decimal as recommended for salary, stored as string for encryption compatibility)
            $table->text('gross_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('gross_salary_by_FTE')->comment('Required Encryption. TYPE - decimal()');
            $table->text('compensation_refund')->comment('Required Encryption. TYPE - decimal()');
            $table->text('thirteen_month_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('thirteen_month_salary_accured')->comment('Required Encryption');
            $table->text('pvd')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('saving_fund')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('employer_social_security')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employee_social_security')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employer_health_welfare')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employee_health_welfare')->comment('Required Encryption. TYPE - decimal()');
            $table->text('tax')->comment('Required Encryption. TYPE - decimal()');
            $table->text('net_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_salary')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_pvd')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_saving_fund')->comment('Required Encryption. TYPE - decimal()');
            $table->text('salary_bonus')->comment('Required Encryption. TYPE - decimal()')->nullable();
            $table->text('total_income')->comment('Required Encryption. TYPE - decimal()');
            $table->text('employer_contribution')->comment('Required Encryption. TYPE - decimal()');
            $table->text('total_deduction')->comment('Required Encryption. TYPE - decimal()');

            // Non-encrypted notes (plain text, for payslip display)
            $table->text('notes')->nullable()->comment('Notes for the payslip.');

            // Pay period date
            $table->date('pay_period_date');

            // Timestamps, for auditability
            $table->timestamps();

            // Optionally: add unique or composite keys, or soft deletes, depending on business requirements
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
