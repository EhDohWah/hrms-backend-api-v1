<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefit_settings', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('setting_type');
            $table->index('category');
        });

        // Update existing health welfare rows with category
        DB::table('benefit_settings')
            ->where('setting_key', 'like', 'health_welfare_%')
            ->update(['category' => 'health_welfare']);
    }

    public function down(): void
    {
        Schema::table('benefit_settings', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
