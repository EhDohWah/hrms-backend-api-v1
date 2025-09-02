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
            // Rename file_path to document_url and change its purpose
            $table->renameColumn('file_path', 'document_url');

            // Update the length to accommodate longer URLs
            $table->string('document_url', 1000)->change();

            // Rename file_name to document_name for clarity
            $table->renameColumn('file_name', 'document_name');

            // Rename uploaded_at to added_at since we're not uploading files
            $table->renameColumn('uploaded_at', 'added_at');

            // Add optional description field
            $table->text('description')->nullable()->after('document_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_attachments', function (Blueprint $table) {
            // Remove the description field
            $table->dropColumn('description');

            // Revert column renames and changes
            $table->renameColumn('document_url', 'file_path');
            $table->string('file_path', 500)->change();
            $table->renameColumn('document_name', 'file_name');
            $table->renameColumn('added_at', 'uploaded_at');
        });
    }
};
