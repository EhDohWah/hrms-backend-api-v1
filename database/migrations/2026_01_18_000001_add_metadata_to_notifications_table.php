<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds category, module, priority, and action_url columns to notifications table
     * for enhanced notification categorization and UI display.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Category for UI grouping (e.g., 'employee', 'grants', 'payroll', 'leaves')
            $table->string('category', 50)->nullable()->after('data')->index();

            // Module identifier matching ModuleSeeder names (e.g., 'employees', 'grants_list')
            $table->string('module', 100)->nullable()->after('category')->index();

            // Optional expiration for auto-cleanup
            $table->timestamp('expires_at')->nullable()->after('module');

            // Composite index for common query patterns
            $table->index(['notifiable_type', 'notifiable_id', 'category']);
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'category']);
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->dropColumn(['category', 'module', 'expires_at']);
        });
    }
};
