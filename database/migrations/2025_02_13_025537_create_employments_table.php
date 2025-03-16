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
            $table->unsignedBigInteger('employee_id'); // Required - links to employee record
            $table->unsignedBigInteger('employment_type_id'); // Required - type of employment
            $table->date('start_date'); // Required - when employment started
            $table->date('probation_end_date')->nullable(); // Optional - when probation ends
            $table->date('end_date')->nullable(); // Optional - when employment ends (for contracts)
            $table->unsignedBigInteger('position_id'); // Required - job position
            $table->unsignedBigInteger('department_id'); // Required - department
            $table->unsignedBigInteger('work_location_id'); // Required - where employee works
            $table->decimal('position_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation
            $table->unsignedBigInteger('supervisor_id')->nullable(); // Optional - reporting manager
            $table->decimal('employee_tax', 10, 2)->nullable(); // Optional - tax rate
            $table->decimal('fte', 10, 2)->nullable(); // Optional - full-time equivalent
            $table->boolean('active')->default(true); // Required - employment status
            $table->boolean('health_welfare')->default(false); // Required - health benefits flag
            $table->boolean('pvd')->default(false); // Required - provident fund flag
            $table->boolean('saving_fund')->default(false); // Required - saving fund flag
            $table->string('social_security_id')->nullable(); // Optional - social security identifier
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->unsignedBigInteger('grant_item_id')->nullable(); // Optional - grant item id

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('employment_type_id')->references('id')->on('employment_types');
            $table->foreign('position_id')->references('id')->on('positions');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('work_location_id')->references('id')->on('work_locations');
            $table->foreign('supervisor_id')->references('id')->on('employees');
            $table->foreign('grant_item_id')->references('id')->on('grant_items');
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
