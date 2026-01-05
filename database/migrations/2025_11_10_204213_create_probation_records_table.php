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
        Schema::create('probation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_id')->constrained('employments')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('no action');

            // Probation Event Details
            $table->string('event_type', 20)->comment('Type of probation event: initial, extension, passed, failed');
            $table->date('event_date')->comment('When this event occurred');
            $table->date('decision_date')->nullable()->comment('When decision was made');

            // Probation Dates
            $table->date('probation_start_date')->comment('When this probation period started');
            $table->date('probation_end_date')->comment('When this probation period should end');
            $table->date('previous_end_date')->nullable()->comment('Previous end date (for extensions)');

            // Extension Tracking
            $table->integer('extension_number')->default(0)->comment('0=initial, 1=first extension, 2=second extension, etc.');

            // Decision Details
            $table->string('decision_reason', 500)->nullable()->comment('Reason for extension/pass/fail');
            $table->text('evaluation_notes')->nullable()->comment('Performance evaluation notes');
            $table->string('approved_by')->nullable()->comment('Who approved this decision');

            // Current Status
            $table->boolean('is_active')->default(true)->comment('Is this the current probation record?');

            // Audit Trail
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('employment_id', 'idx_probation_employment');
            $table->index('employee_id', 'idx_probation_employee');
            $table->index('event_type', 'idx_probation_event_type');
            $table->index('is_active', 'idx_probation_is_active');
            $table->index('probation_end_date', 'idx_probation_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probation_records');
    }
};
