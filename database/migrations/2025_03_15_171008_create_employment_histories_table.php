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
        Schema::create('employment_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_id'); // Required - links to employment record
            $table->unsignedBigInteger('employee_id'); // Required - links to employee record
            $table->string('employment_type'); // Required - type of employment
            $table->date('start_date'); // Required - when employment started
            $table->date('probation_end_date')->nullable(); // Optional - when probation ends
            $table->date('end_date')->nullable(); // Optional - when employment ends (for contracts)
            $table->unsignedBigInteger('department_position_id')->nullable(); // Required - department
            $table->unsignedBigInteger('work_location_id')->nullable(); // Required - work location
            $table->decimal('position_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation
            $table->decimal('employee_tax', 10, 2)->nullable(); // Optional - tax rate
            $table->decimal('fte', 10, 2)->nullable(); // Optional - full-time equivalent
            $table->boolean('active')->default(true); // Required - employment status
            $table->boolean('health_welfare')->default(false); // Required - health benefits flag
            $table->boolean('pvd')->default(false); // Required - provident fund flag
            $table->boolean('saving_fund')->default(false); // Required - saving fund flag
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Foreign keys
            $table->foreign('employment_id')->references('id')->on('employments')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('work_location_id')->references('id')->on('work_locations');
            $table->foreign('department_position_id')->references('id')->on('department_positions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_histories');
    }
};
