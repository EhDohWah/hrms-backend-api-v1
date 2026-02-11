<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the payroll_grant_allocations table for Budget History tracking.
 *
 * Purpose:
 * - Snapshot ALL active funding allocations when each payroll is created
 * - Allows "Budget History" view showing which grants paid for each month's salary
 * - Handles employees with multiple allocations (e.g., 60% Grant A + 40% Grant B)
 *
 * Workflow:
 * - When PayrollService creates a payroll, it snapshots current active allocations
 * - Each payroll can have multiple grant allocations (1:many relationship)
 * - Data is denormalized (grant_name, budget_line_code) to preserve history
 *   even if the grant/item is later modified
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_grant_allocations', function (Blueprint $table) {
            $table->id();

            // Foreign key to payroll record
            $table->foreignId('payroll_id')
                ->constrained('payrolls')
                ->cascadeOnDelete();

            // Reference to the funding allocation that was active at payroll time
            // Nullable because allocation could be deleted later
            $table->foreignId('employee_funding_allocation_id')
                ->nullable()
                ->constrained('employee_funding_allocations')
                ->nullOnDelete();

            // Reference to grant item (for queries like "all payrolls funded by Grant X")
            // Nullable because grant_item could be deleted later
            $table->foreignId('grant_item_id')
                ->nullable()
                ->constrained('grant_items')
                ->nullOnDelete();

            // =================================================
            // SNAPSHOT DATA (denormalized for history integrity)
            // =================================================
            // These values are copied at payroll creation time and never change,
            // even if the source grant/item is later modified or deleted.

            $table->string('grant_code', 50)->nullable()
                ->comment('Snapshot: Grant code at payroll time');

            $table->string('grant_name', 255)->nullable()
                ->comment('Snapshot: Grant name at payroll time');

            $table->string('budget_line_code', 50)->nullable()
                ->comment('Snapshot: Budget line code at payroll time');

            $table->string('grant_position', 255)->nullable()
                ->comment('Snapshot: Position title at payroll time');

            // FTE percentage (stored as decimal 0.00-1.00 for consistency with allocations)
            $table->decimal('fte', 5, 4)
                ->comment('FTE as decimal (0.60 = 60%)');

            // Calculated allocation amount for this grant
            $table->decimal('allocated_amount', 15, 2)->nullable()
                ->comment('Amount allocated to this grant for this payroll');

            // What salary type was used (probation vs regular)
            $table->string('salary_type', 50)->nullable()
                ->comment('probation_salary or pass_probation_salary');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['payroll_id', 'grant_item_id'], 'pga_payroll_grant_idx');
            $table->index('grant_item_id', 'pga_grant_item_idx');

            // Index for FK column not covered by composites above
            $table->index('employee_funding_allocation_id', 'idx_pga_efa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_grant_allocations');
    }
};
