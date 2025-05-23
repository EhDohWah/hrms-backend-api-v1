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
        Schema::create('grant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('grants')->onDelete('cascade');
            $table->string('bg_line')->nullable();
            $table->string('grant_position')->nullable();
            $table->decimal('grant_salary', 15, 2)->nullable();
            $table->decimal('grant_benefit', 15, 2)->nullable();
            $table->decimal('grant_level_of_effort', 5, 2)->nullable();
            $table->string('grant_position_number')->nullable();
            $table->timestamps();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grant_items');
    }
};
