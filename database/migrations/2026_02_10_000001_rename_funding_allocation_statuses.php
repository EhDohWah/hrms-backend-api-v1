<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename funding allocation statuses for clearer business meaning:
     * - 'terminated' → 'inactive' (temporarily not in use, can be reactivated)
     * - 'historical' → 'closed' (permanently ended, no reactivation)
     */
    public function up(): void
    {
        DB::table('employee_funding_allocations')
            ->where('status', 'terminated')
            ->update(['status' => 'inactive']);

        DB::table('employee_funding_allocations')
            ->where('status', 'historical')
            ->update(['status' => 'closed']);
    }

    /**
     * Reverse the status renames.
     */
    public function down(): void
    {
        DB::table('employee_funding_allocations')
            ->where('status', 'inactive')
            ->update(['status' => 'terminated']);

        DB::table('employee_funding_allocations')
            ->where('status', 'closed')
            ->update(['status' => 'historical']);
    }
};
