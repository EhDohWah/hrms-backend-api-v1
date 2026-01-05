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
        Schema::create('user_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('dashboard_widget_id')->constrained('dashboard_widgets')->onDelete('cascade');
            $table->integer('order')->default(0);            // User's custom order for this widget
            $table->boolean('is_visible')->default(true);    // User can hide widgets
            $table->boolean('is_collapsed')->default(false); // User can collapse widgets
            $table->json('user_config')->nullable();         // User-specific widget settings
            $table->timestamps();

            $table->unique(['user_id', 'dashboard_widget_id']);
            $table->index('user_id');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_widgets');
    }
};
