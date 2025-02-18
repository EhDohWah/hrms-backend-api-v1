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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->string('job_position');
            $table->date('interview_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('interview_mode', ['in-person', 'virtual']);
            $table->enum('interview_status', ['scheduled', 'completed', 'cancelled']);
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            // New field for storing resume file path (optional)
            $table->string('resume')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
