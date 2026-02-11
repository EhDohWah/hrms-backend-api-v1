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
        Schema::create('resignations', function (Blueprint $table) {
            $table->id();

            // Core fields as per schema
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('no action');
            $table->date('resignation_date');
            $table->date('last_working_date');
            $table->string('reason', 50);
            $table->text('reason_details')->nullable();
            $table->string('acknowledgement_status', 50)->default('Pending');
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            $table->datetime('acknowledged_at')->nullable();

            // Base template fields
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Indexes for performance
            $table->index(['acknowledgement_status', 'resignation_date']);
            $table->index(['employee_id', 'acknowledgement_status']);
            $table->index(['resignation_date', 'last_working_date']);
            $table->index(['department_id', 'acknowledgement_status']);

            // Indexes for FK columns not covered by composites above
            $table->index('position_id', 'idx_resignations_position');
            $table->index('acknowledged_by', 'idx_resignations_ack_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resignations');
    }
};
