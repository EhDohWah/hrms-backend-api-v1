<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add eligible_parents_count column to employees table.
 *
 * Previously, the Employee model auto-computed this by checking whether
 * father_name or mother_name were non-empty — which is incorrect under Thai
 * tax law (parents must be age 60+ with income < ฿30,000/year). This
 * migration replaces that heuristic with an explicit, admin-set integer
 * column that defaults to 0 (no eligible parents assumed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 0 = no eligible parents; max 4 (2 own + 2 spouse's parents under Thai RD rules)
            $table->unsignedTinyInteger('eligible_parents_count')
                ->default(0)
                ->after('mother_phone_number')
                ->comment('Number of tax-eligible parents (age 60+, income < 30000/yr). Set explicitly by admin.');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('eligible_parents_count');
        });
    }
};
