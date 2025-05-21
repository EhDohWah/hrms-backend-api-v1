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
            $table->unsignedBigInteger('grant_items_id');
            $table->decimal('level_of_effort', 5, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            // Foreign keys
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('grant_items_id')->references('id')->on('grant_items');

            // Add composite index for grant_items_id and active columns
            $table->index(['grant_items_id', 'active'], 'alloc_item_active_idx');
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
