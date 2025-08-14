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
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_income', 15, 2);
            $table->decimal('max_income', 15, 2)->nullable(); // NULL for highest bracket
            $table->decimal('tax_rate', 5, 2); // Percentage (e.g., 5.00 for 5%)
            $table->integer('bracket_order');
            $table->integer('effective_year');
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['effective_year', 'is_active']);
            $table->index('bracket_order');
            $table->unique(['effective_year', 'bracket_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
