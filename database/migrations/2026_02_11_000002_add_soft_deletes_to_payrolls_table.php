<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft deletes to payrolls table for recycle bin support.
 *
 * Payroll records are financial data — soft delete allows users to
 * "delete" payrolls with 90-day recovery via recycle bin, while
 * Prunable handles permanent cleanup after the retention period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'idx_payrolls_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('idx_payrolls_deleted_at');
            $table->dropSoftDeletes();
        });
    }
};
