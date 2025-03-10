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
        Schema::create('grant_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('grants')->onDelete('cascade');
            $table->string('budget_line', 50);
            $table->decimal('grant_salary', 15, 2);
            $table->decimal('grant_benefit', 15, 2);
            $table->decimal('grant_level_of_effort', 5, 2);
            $table->string('grant_position_number', 50);
            $table->decimal('grant_monthly_cost', 15, 2);
            $table->decimal('grant_total_person_cost', 15, 2);
            $table->decimal('grant_total_amount', 15, 2);
            $table->timestamps();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            // Removed duplicate foreign key constraint
            // The foreignId()->constrained() above already creates the constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_positions');
    }
};
