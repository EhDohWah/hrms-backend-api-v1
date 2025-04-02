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
            $table->increments('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('department_position_id')->nullable();
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

            // Foreign keys:
            $table->foreign('department_position_id')
                  ->references('id')
                  ->on('department_positions')
                  ->onDelete('no action');

            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('no action');
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
