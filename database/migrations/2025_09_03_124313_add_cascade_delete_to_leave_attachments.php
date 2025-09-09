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
        Schema::table('leave_attachments', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['leave_request_id']);

            // Add the foreign key constraint with cascade delete
            $table->foreign('leave_request_id')
                ->references('id')
                ->on('leave_requests')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_attachments', function (Blueprint $table) {
            // Drop the cascade constraint
            $table->dropForeign(['leave_request_id']);

            // Restore the original foreign key constraint without cascade
            $table->foreign('leave_request_id')
                ->references('id')
                ->on('leave_requests');
        });
    }
};
