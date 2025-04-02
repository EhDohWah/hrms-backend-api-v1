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
            $table->unsignedBigInteger('employee_id');
            $table->date('pay_period_date');
            $table->decimal('basic_salary', 18, 2);
            $table->decimal('salary_by_FTE', 18, 2);
            $table->decimal('compensation_refund', 18, 2);
            $table->decimal('thirteen_month_salary', 18, 2);
            $table->decimal('pvd', 18, 2);
            $table->decimal('saving_fund', 18, 2);
            $table->decimal('employer_social_security', 18, 2);
            $table->decimal('employee_social_security', 18, 2);
            $table->decimal('employer_health_welfare', 18, 2);
            $table->decimal('employee_health_welfare', 18, 2);
            $table->decimal('tax', 18, 2);
            $table->decimal('grand_total_income', 18, 2);
            $table->decimal('grand_total_deduction', 18, 2);
            $table->decimal('net_paid', 18, 2); // balance after deduction
            $table->decimal('employer_contribution_total', 18, 2);
            $table->decimal('two_sides', 18, 2);
            $table->date('payslip_date')->nullable();
            $table->string('payslip_number', 50)->nullable();
            $table->string('staff_signature', 200)->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Foreign key constraint: ensure that employee_id references the employees table.
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
