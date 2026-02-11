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
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null'); // Department reference
            $table->foreignId('position_id')->nullable()->constrained('positions')->onDelete('no action'); // Position reference
            $table->string('destination', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('purpose')->nullable();
            $table->string('grant', 50)->nullable();
            $table->string('transportation', 100)->nullable();
            $table->string('transportation_other_text', 200)->nullable(); // Custom text when transportation is 'other'
            $table->string('accommodation', 100)->nullable();
            $table->string('accommodation_other_text', 200)->nullable(); // Custom text when accommodation is 'other'
            $table->date('request_by_date')->nullable();
            $table->boolean('supervisor_approved')->default(false);
            $table->date('supervisor_approved_date')->nullable();
            $table->boolean('hr_acknowledged')->default(false);
            $table->date('hr_acknowledgement_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            // Indexes for FK columns (SQL Server does NOT auto-create these)
            $table->index('employee_id', 'idx_travel_req_employee');
            $table->index('department_id', 'idx_travel_req_department');
            $table->index('position_id', 'idx_travel_req_position');
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
