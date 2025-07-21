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
            $table->foreignId('org_funded_id')->nullable()->constrained('org_funded_allocations');
            $table->foreignId('position_slot_id')->nullable()->constrained('position_slots');
            $table->decimal('level_of_effort', 4, 2);
            $table->string('allocation_type', 20); // e.g., 'grant', 'org_funded'
            $table->decimal('allocation_percentage', 15, 2)->nullable();
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'employment_id']);
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
