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
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('department_position_id')->nullable()->constrained('department_positions')->nullOnDelete();
            $table->string('destination', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('purpose')->nullable();
            $table->string('grant', 50)->nullable();
            $table->string('transportation', 100)->nullable();
            $table->string('accommodation', 100)->nullable();
            $table->string('request_by_signature', 200)->nullable();
            $table->string('request_by_fullname', 200)->nullable();
            $table->date('request_by_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 50)->default('pending'); // overall status of travel request
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};
