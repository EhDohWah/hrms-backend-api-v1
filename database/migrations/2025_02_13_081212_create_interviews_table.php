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
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->onDelete('cascade');
            $table->foreignId('grant_position_id')->nullable()->constrained('grant_positions')->onDelete('cascade');
            $table->string('interviewer_name')->nullable();
            $table->date('interview_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('interview_mode', ['in-person', 'virtual'])->nullable();
            $table->enum('interview_status', ['scheduled', 'completed', 'cancelled']);
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
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
