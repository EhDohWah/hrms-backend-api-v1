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
        Schema::create('benefit_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique()->comment('Unique identifier for the setting (e.g., health_welfare_percentage)');
            $table->decimal('setting_value', 10, 2)->comment('Numeric value of the setting');
            $table->string('setting_type', 50)->default('percentage')->comment('Type: percentage, boolean, numeric, etc.');
            $table->text('description')->nullable()->comment('Human-readable description of the setting');
            $table->date('effective_date')->nullable()->comment('Date when this setting becomes effective');
            $table->boolean('is_active')->default(true)->comment('Whether this setting is currently active');
            $table->json('applies_to')->nullable()->comment('JSON conditions for applicability (organization, etc.)');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('setting_key');
            $table->index('is_active');
            $table->index('effective_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_settings');
    }
};
