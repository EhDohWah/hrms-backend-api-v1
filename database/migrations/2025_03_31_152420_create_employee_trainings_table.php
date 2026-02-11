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
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->string('status', 50);
            $table->timestamps(); // creates created_at and updated_at
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Indexes for FK columns (SQL Server does NOT auto-create these)
            $table->index('employee_id', 'idx_emp_trainings_employee');
            $table->index('training_id', 'idx_emp_trainings_training');
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
