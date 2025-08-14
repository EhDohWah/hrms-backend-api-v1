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
        Schema::create('org_funded_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('grants')->cascadeOnDelete();
            $table->foreignId('department_position_id')->constrained('department_positions')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();

            // Optional: for security/auditing
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Index for performance
            $table->index(['grant_id', 'department_position_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_funded_allocations');
    }
};
