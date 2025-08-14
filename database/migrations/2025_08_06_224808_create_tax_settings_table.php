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
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 50);
            $table->decimal('setting_value', 15, 2);
            $table->string('setting_type', 30); // 'DEDUCTION', 'RATE', 'LIMIT'
            $table->string('description')->nullable();
            $table->integer('effective_year');
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('setting_key');
            $table->index(['setting_type', 'effective_year']);
            $table->index(['effective_year', 'is_active']);
            $table->unique(['setting_key', 'effective_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
