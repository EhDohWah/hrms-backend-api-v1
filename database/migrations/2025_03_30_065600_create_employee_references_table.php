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
        Schema::create('employee_references', function (Blueprint $table) {
            $table->id();
            $table->string('referee_name', 200);
            $table->string('occupation', 200);
            $table->string('candidate_name', 100);
            $table->string('relation', 200);
            $table->string('address', 200);
            $table->string('phone_number', 50);
            $table->string('email', 200);
            $table->timestamps(); // creates created_at and updated_at columns
            $table->string('created_by', 100);
            $table->string('updated_by', 100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_references');
    }
};
