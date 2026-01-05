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
        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Widget identifier (e.g., 'employee_stats')
            $table->string('display_name');                  // Display name (e.g., 'Employee Statistics')
            $table->string('description')->nullable();       // Widget description
            $table->string('component');                     // Vue component name (e.g., 'EmployeeStatsWidget')
            $table->string('icon')->nullable();              // Icon class (e.g., 'ti-users')
            $table->string('category');                      // Category (e.g., 'hr', 'payroll', 'leave', 'general')
            $table->string('size')->default('medium');       // Widget size: small, medium, large, full
            $table->string('required_permission')->nullable(); // Permission needed to view widget
            $table->boolean('is_active')->default(true);     // Is widget available
            $table->boolean('is_default')->default(false);   // Show by default for new users
            $table->integer('default_order')->default(0);    // Default display order
            $table->json('config')->nullable();              // Additional configuration options
            $table->timestamps();

            $table->unique('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
    }
};
