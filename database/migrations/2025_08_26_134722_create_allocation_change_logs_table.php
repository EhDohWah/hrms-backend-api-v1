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
        Schema::create('allocation_change_logs', function (Blueprint $table) {
            $table->id();

            // Related entities
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('employment_id')->nullable()->constrained('employments');
            $table->foreignId('employee_funding_allocation_id')->nullable()->constrained('employee_funding_allocations');

            // Change tracking
            $table->string('change_type', 50); // created, updated, deleted, transferred
            $table->string('action_description'); // Human readable description
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values
            $table->json('allocation_summary')->nullable(); // Snapshot of all allocations

            // Financial impact
            $table->decimal('financial_impact', 15, 2)->nullable(); // Change in allocated amount
            $table->string('impact_type', 20)->nullable(); // increase, decrease, neutral

            // Approval workflow
            $table->string('approval_status', 20)->default('approved'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Business context
            $table->string('reason_category', 50)->nullable(); // promotion, transfer, budget_change, etc.
            $table->text('business_justification')->nullable();
            $table->string('effective_date')->nullable(); // When change takes effect

            // Audit trail
            $table->string('changed_by', 100);
            $table->string('change_source', 50)->default('manual'); // manual, system, import, api
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamps();

            // Indexes for performance
            $table->index(['employee_id', 'created_at']);
            $table->index(['employment_id', 'change_type']);
            $table->index(['change_type', 'approval_status']);
            $table->index(['created_at', 'change_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_change_logs');
    }
};
