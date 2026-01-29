<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes the user_id column and foreign key from employees table.
     * This relationship was defined but never used in the application.
     * See: docs/USER_EMPLOYEE_RELATIONSHIP_ANALYSIS.md for full analysis.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop foreign key constraint first (if it exists)
            if (Schema::hasColumn('employees', 'user_id')) {
                // Try to drop the foreign key constraint
                // Foreign key name varies by database, so we'll try common patterns
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // If foreign key doesn't exist or already dropped, continue
                }

                // Now drop the column
                $table->dropColumn('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Re-adds the user_id column as it was originally defined.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add the column back after id column
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->onDelete('set null');
        });
    }
};
