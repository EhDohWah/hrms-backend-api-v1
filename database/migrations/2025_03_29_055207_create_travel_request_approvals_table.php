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
        Schema::create('travel_request_approvals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('travel_request_id');
            $table->string('approver_role', 100)->nullable();
            $table->string('approver_name', 200)->nullable();
            $table->string('approver_signature', 200)->nullable();
            $table->date('approval_date')->nullable();
            $table->string('status', 50)->default('pending'); // approved/declined/pending
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->foreign('travel_request_id')
                  ->references('id')
                  ->on('travel_requests')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_request_approvals');
    }
};
