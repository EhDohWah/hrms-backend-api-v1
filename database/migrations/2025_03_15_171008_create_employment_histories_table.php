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
            $table->date('start_date'); // Required - when employment started
            $table->date('pass_probation_date')->nullable(); // Optional - when probation ends
            $table->string('pay_method')->nullable(); // Optional - pay method
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('no action'); // Department reference
            $table->foreignId('section_department_id')->nullable()->constrained('section_departments')->nullOnDelete()->comment('Sub-department within department'); // Section department reference
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('no action'); // Position reference
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete(); // Site/organizational location
            $table->string('section_department')->nullable(); // Legacy text field - retained for migration compatibility
            $table->decimal('pass_probation_salary', 10, 2); // Required - regular salary
            $table->decimal('probation_salary', 10, 2)->nullable(); // Optional - salary during probation

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
