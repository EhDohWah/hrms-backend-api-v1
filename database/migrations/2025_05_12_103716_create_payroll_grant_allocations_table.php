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
        Schema::create('payroll_grant_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')
                  ->constrained('payrolls');
            $table->foreignId('employee_grant_allocation_id')
                  ->constrained('employee_grant_allocations');
            $table->decimal('loe_snapshot', 10, 2);
            $table->decimal('amount', 18, 2);
            $table->boolean('is_advance')->default(false);
            $table->string('description',255)->nullable();
            $table->timestamps();
            $table->string('created_by',100)->nullable();
            $table->string('updated_by',100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_grant_allocations');
    }
};
