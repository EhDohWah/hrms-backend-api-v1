<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bulk_payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->string('pay_period'); // Format: YYYY-MM
            $table->json('filters')->nullable(); // Stores organization, department, grant filters
            $table->integer('total_employees')->default(0);
            $table->integer('total_payrolls')->default(0); // Will be > employees due to multiple allocations
            $table->integer('processed_payrolls')->default(0);
            $table->integer('successful_payrolls')->default(0);
            $table->integer('failed_payrolls')->default(0);
            $table->integer('advances_created')->default(0);
            $table->string('status', 20)->default('pending');
            $table->json('errors')->nullable(); // Array of error objects
            $table->json('summary')->nullable(); // Final summary with totals, breakdown
            $table->string('current_employee')->nullable(); // Currently processing employee name
            $table->string('current_allocation')->nullable(); // Currently processing allocation label
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // Foreign key
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['status', 'created_by']);
            $table->index('pay_period');
        });

        // SQL Server doesn't support ENUM - use CHECK constraint instead
        DB::statement("ALTER TABLE bulk_payroll_batches ADD CONSTRAINT chk_bulk_payroll_status CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_payroll_batches');
    }
};
