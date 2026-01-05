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
        Schema::create('employee_funding_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('employment_id')->nullable()->constrained('employments');

            // Direct references to funding sources (replacing intermediary tables)
            $table->foreignId('grant_item_id')
                ->nullable()
                ->constrained('grant_items')
                ->onDelete('no action')
                ->comment('Direct link to grant_items for all allocations (project + hub)');

            $table->decimal('fte', 4, 2)->comment('Full-Time Equivalent - represents the actual funding allocation percentage for this employee');
            $table->string('allocation_type', 20); // e.g., 'grant', 'org_funded'
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->string('salary_type', 50)
                ->nullable()
                ->comment('Which salary type was used for calculation: probation_salary, pass_probation_salary');
            $table->string('status', 20)
                ->default('active')
                ->comment('Lifecycle status of the allocation: active, historical, terminated');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'employment_id']);
            $table->index(['employment_id', 'status'], 'idx_employment_status');
            $table->index(['status', 'end_date'], 'idx_status_end_date');
            $table->index(['grant_item_id', 'status'], 'idx_grant_item_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_funding_allocations');
    }
};
