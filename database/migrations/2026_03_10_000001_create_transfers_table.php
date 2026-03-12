<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('from_organization', 10);
            $table->string('to_organization', 10);
            $table->date('from_start_date');
            $table->date('to_start_date');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users');
            $table->index('employee_id', 'idx_transfers_employee');
            $table->index('created_by', 'idx_transfers_created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
