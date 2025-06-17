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
        Schema::create('employee_grant_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            // $table->unsignedBigInteger('grant_position_slot_id');
            $table->unsignedBigInteger('grant_item_id');
            $table->unsignedBigInteger('employment_id')->nullable();
            $table->string('bg_line')->nullable();
            $table->decimal('level_of_effort', 5, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees');
            // $table->foreign('grant_position_slot_id')->references('id')->on('grant_position_slots');
            $table->foreign('grant_item_id')->references('id')->on('grant_items');
            $table->foreign('employment_id')->references('id')->on('employments');

            // Add composite index for grant_position_slot_id and employment_id columns
            $table->index(['grant_item_id', 'active'], 'alloc_item_active_idx'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_grant_allocations');
    }
};
