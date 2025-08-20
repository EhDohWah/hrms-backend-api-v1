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
        Schema::create('position_slots', function (Blueprint $table) {
            $table->id();

            // Foreign key to grant_items
            $table->foreignId('grant_item_id')->constrained('grant_items')->cascadeOnDelete();

            // Slot number, e.g., 1, 2, 3...
            $table->unsignedInteger('slot_number');

            // Foreign key to budget_lines
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();

            $table->timestamps();

            // Optionally track auditing
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_slots');
    }
};
