<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the holidays table for storing organization's traditional day-off/public holidays.
     * These dates are excluded when calculating leave request working days.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('name_th', 255)->nullable()->comment('Thai name for the holiday');
            $table->date('date');
            $table->integer('year')->comment('Year for easy filtering');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->comment('Enable/disable the holiday');
            $table->timestamps();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();

            // Unique constraint: one holiday per date
            $table->unique('date', 'holidays_date_unique');

            // Index for efficient date range queries
            $table->index(['date', 'year'], 'holidays_date_year_index');
            $table->index('year', 'holidays_year_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
