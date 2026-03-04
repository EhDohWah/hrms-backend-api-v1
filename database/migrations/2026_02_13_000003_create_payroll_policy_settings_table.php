<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_policy_settings', function (Blueprint $table) {
            $table->id();

            // 13th Month Salary policy
            $table->boolean('thirteenth_month_enabled')->default(true);
            $table->integer('thirteenth_month_divisor')->default(12);
            $table->integer('thirteenth_month_min_months')->default(6);
            $table->string('thirteenth_month_accrual_method', 50)->default('monthly');

            // Annual Salary Increase policy
            $table->boolean('salary_increase_enabled')->default(true);
            $table->decimal('salary_increase_rate', 5, 2)->default(1.00);
            $table->integer('salary_increase_min_working_days')->default(365);
            $table->integer('salary_increase_effective_month')->nullable();

            // Policy metadata
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();

            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('effective_date');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_policy_settings');
    }
};
