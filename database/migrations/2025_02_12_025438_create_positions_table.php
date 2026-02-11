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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->onDelete('no action');
            $table->integer('level')->default(1)->comment('Hierarchy level (1 = top level, 2 = reports to level 1, etc.)');
            $table->boolean('is_manager')->default(false)->comment('Is this a managerial position?');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            // Indexes for better performance
            $table->index(['department_id', 'is_active']);
            $table->index('reports_to_position_id');
            $table->index(['department_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
