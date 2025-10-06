<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_actions', function (Blueprint $table) {
            $table->id();
            $table->string('form_number')->default('SMRU-SF038');
            $table->string('reference_number')->unique();

            // Employment Reference
            $table->unsignedBigInteger('employment_id');

            // Section 1: Current Employment Information (captured at creation for audit trail)
            $table->string('current_employee_no')->nullable();
            $table->unsignedBigInteger('current_department_id')->nullable();
            $table->unsignedBigInteger('current_position_id')->nullable();
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->unsignedBigInteger('current_work_location_id')->nullable();
            $table->date('current_employment_date')->nullable();
            $table->date('effective_date');

            // Section 2: Action Type (using string constants instead of enum)
            $table->string('action_type'); // appointment, fiscal_increment, etc.
            $table->string('action_subtype')->nullable(); // re_evaluated_pay, promotion, etc.
            $table->boolean('is_transfer')->default(false);
            $table->string('transfer_type')->nullable(); // internal_department, site_to_site, etc.

            // Section 3: New Employment Information (proposed changes with foreign keys)
            $table->unsignedBigInteger('new_department_id')->nullable();
            $table->unsignedBigInteger('new_position_id')->nullable();
            $table->unsignedBigInteger('new_work_location_id')->nullable();
            $table->decimal('new_salary', 12, 2)->nullable();

            // Additional text fields for supplementary information
            $table->string('new_work_schedule')->nullable();
            $table->string('new_report_to')->nullable();
            $table->string('new_pay_plan')->nullable();
            $table->string('new_phone_ext')->nullable();
            $table->string('new_email')->nullable();

            // Section 4: Comments/Details
            $table->text('comments')->nullable();
            $table->text('change_details')->nullable();

            // Four Simple Boolean Approvals
            $table->boolean('dept_head_approved')->default(false);
            $table->boolean('coo_approved')->default(false);
            $table->boolean('hr_approved')->default(false);
            $table->boolean('accountant_approved')->default(false);

            // Metadata
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (using NO ACTION for SQL Server compatibility - prevents cascade cycles)
            $table->foreign('employment_id')->references('id')->on('employments');
            $table->foreign('current_department_id')->references('id')->on('departments');
            $table->foreign('current_position_id')->references('id')->on('positions');
            $table->foreign('current_work_location_id')->references('id')->on('work_locations');
            $table->foreign('new_department_id')->references('id')->on('departments');
            $table->foreign('new_position_id')->references('id')->on('positions');
            $table->foreign('new_work_location_id')->references('id')->on('work_locations');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');

            // Indexes removed due to identifier length issues
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_actions');
    }
};
