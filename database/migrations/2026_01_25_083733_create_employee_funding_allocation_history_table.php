<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the employee_funding_allocation_history table for audit trail.
 *
 * Purpose:
 * - Track ALL changes to funding allocations over time
 * - Audit trail for compliance and reporting
 * - Answers: "What was this employee's funding structure in March 2025?"
 *
 * Triggers (when to create history records):
 * - When allocation is created (change_type = 'created')
 * - When allocation is updated (change_type = 'updated')
 * - When allocation is ended/replaced (change_type = 'ended')
 * - When probation completes (change_type = 'probation_completed')
 * - When allocation is terminated (change_type = 'terminated')
 *
 * Key difference from payroll_grant_allocations:
 * - This table tracks CHANGES regardless of payroll
 * - payroll_grant_allocations only captures snapshots at payment time
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_funding_allocation_history', function (Blueprint $table) {
            $table->id();

            // Reference to the allocation (may be deleted, so nullable)
            $table->foreignId('employee_funding_allocation_id')
                ->nullable()
                ->constrained('employee_funding_allocations')
                ->nullOnDelete();

            // Employee and employment context
            // Note: Using noActionOnDelete to avoid SQL Server cascade path conflicts
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->noActionOnDelete();

            $table->foreignId('employment_id')
                ->constrained('employments')
                ->noActionOnDelete();

            // Grant item reference (may be deleted)
            $table->foreignId('grant_item_id')
                ->nullable()
                ->constrained('grant_items')
                ->nullOnDelete();

            // =================================================
            // SNAPSHOT DATA (preserved even if source changes)
            // =================================================
            $table->string('grant_code', 50)->nullable();
            $table->string('grant_name', 255)->nullable();
            $table->string('budget_line_code', 50)->nullable();
            $table->string('grant_position', 255)->nullable();

            // Allocation details at the time of this history record
            $table->decimal('fte', 5, 4)
                ->comment('FTE as decimal (0.60 = 60%)');

            $table->decimal('allocated_amount', 15, 2)->nullable();

            $table->string('salary_type', 50)->nullable()
                ->comment('probation_salary or pass_probation_salary');

            $table->string('allocation_status', 20)->default('active')
                ->comment('Status at time of change: active, historical, terminated');

            // =================================================
            // CHANGE TRACKING
            // =================================================
            $table->date('effective_date')
                ->comment('When this allocation state became effective');

            $table->date('end_date')->nullable()
                ->comment('When this allocation state ended (null = still current)');

            $table->string('change_type', 30)
                ->comment('created, updated, ended, probation_completed, terminated');

            $table->text('change_reason')->nullable()
                ->comment('Human-readable reason for the change');

            $table->text('change_details')->nullable()
                ->comment('JSON: What specifically changed (old vs new values)');

            // Who made the change
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('changed_by_name', 100)->nullable()
                ->comment('Snapshot of user name in case user is deleted');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['employee_id', 'effective_date'], 'efah_employee_date_idx');
            $table->index(['employment_id', 'effective_date'], 'efah_employment_date_idx');
            $table->index(['grant_item_id', 'effective_date'], 'efah_grant_date_idx');
            $table->index('change_type', 'efah_change_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_funding_allocation_history');
    }
};
