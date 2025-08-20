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
            $table->foreignId('employment_id')->constrained('employments'); // Required - links to employment record
            $table->foreignId('employee_id')->constrained('employees'); // Required - links to employee record
            $table->string('employment_type'); // Required - type of employment
            $table->date('start_date'); // Required - when employment started
            $table->date('probation_pass_date')->nullable(); // Optional - when probation ends
            $table->string('pay_method')->nullable(); // Optional - pay method
            $table->foreignId('department_position_id')->nullable()->constrained('department_positions'); // Required - department
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations'); // Required - work location
            $table->decimal('position_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation

            $table->decimal('fte', 10, 2)->nullable(); // Optional - full-time equivalent
            $table->boolean('active')->default(true); // Required - employment status
            $table->boolean('health_welfare')->default(false); // Required - health benefits flag
            $table->boolean('pvd')->default(false); // Required - provident fund flag
            $table->boolean('saving_fund')->default(false); // Required - saving fund flag
            $table->date('change_date')->nullable(); // Optional - date when change occurred

            // Change tracking fields
            $table->string('change_reason')->nullable(); // Optional - reason for the change
            $table->string('changed_by_user')->nullable(); // Optional - user who made the change
            $table->json('changes_made')->nullable(); // Optional - details of changes made
            $table->json('previous_values')->nullable(); // Optional - previous values before change
            $table->text('notes')->nullable(); // Optional - additional notes

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
        Schema::dropIfExists('employment_histories');
    }
};
