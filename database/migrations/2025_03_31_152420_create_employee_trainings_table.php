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
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('training_id');
            $table->string('status', 50);
            $table->timestamps(); // creates created_at and updated_at
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Foreign key constraint: employee_trainings.training_id references trainings.id
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->foreign('training_id')->references('id')->on('trainings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_trainings');
    }
};
