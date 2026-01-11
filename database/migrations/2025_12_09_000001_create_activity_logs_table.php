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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created, updated, deleted, processed, imported
            $table->string('subject_type', 100); // Model class name (e.g., App\Models\Grant)
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name')->nullable(); // Human-readable name for display
            $table->text('description')->nullable();
            $table->json('properties')->nullable(); // Store old/new values
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for efficient querying
            $table->index('user_id');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
