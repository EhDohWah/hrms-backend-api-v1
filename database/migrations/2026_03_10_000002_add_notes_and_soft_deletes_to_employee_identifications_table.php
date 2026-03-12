<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_identifications', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('is_primary');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('employee_identifications', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropSoftDeletes();
        });
    }
};
