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
        Schema::create('inter_subsidiary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_grant_allocation_id')
                  ->constrained('payroll_grant_allocations')
                  ->cascadeOnDelete();
            $table->string('from_subsidiary',5);
            $table->string('to_subsidiary',5);
            $table->foreignId('via_grant_id')
                  ->constrained('grants');
            $table->decimal('amount',18,2);
            $table->date('advance_date');
            $table->string('notes',255)->nullable();
            $table->date('settlement_date')->nullable();
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
        Schema::dropIfExists('inter_subsidiary_advances');
    }
};
