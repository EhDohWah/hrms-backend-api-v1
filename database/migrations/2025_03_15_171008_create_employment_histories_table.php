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
            $table->unsignedBigInteger('employment_id');   // Links back to employments.id
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('employment_type_id');
            $table->date('start_date');
            $table->date('probation_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('work_location_id');
            $table->decimal('position_salary', 10, 2);
            $table->decimal('probation_salary', 10, 2)->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->decimal('employee_tax', 10, 2)->nullable();
            $table->decimal('fte', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('health_welfare')->default(false);
            $table->boolean('pvd')->default(false);
            $table->boolean('saving_fund')->default(false);
            $table->string('social_security_id')->nullable();
            $table->unsignedBigInteger('grant_item_id')->nullable(); // Keep track of the grant
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Foreign keys
            $table->foreign('employment_id')->references('id')->on('employments')->onDelete('cascade');
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
        Schema::dropIfExists('employment_histories');
    }
};
