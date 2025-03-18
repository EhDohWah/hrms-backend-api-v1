<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        $data = [
            [
                'name'             => 'Annual Vacation',
                'default_duration' => 26.00,
                'description'      => 'Annual vacation leave.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Traditional Day-Off',
                'default_duration' => 13.00,
                'description'      => 'Traditional day-off leave. Specific details may apply.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Sick (State Disease)',
                'default_duration' => 30.00,
                'description'      => 'Sick leave for state-certified diseases.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Maternity or Paternity',
                'default_duration' => 98.00,
                'description'      => 'Leave for maternity or paternity purposes.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Compassionate',
                'default_duration' => 5.00,
                'description'      => 'Compassionate leave for death or severe illness in immediate family (spouse, children, parents, in-laws, siblings, or grandparents).',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Career Development Training',
                'default_duration' => 14.00,
                'description'      => 'Leave for career development training. Please attach the training request form.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Personal Leave',
                'default_duration' => 3.00,
                'description'      => 'Personal leave for individual matters.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Military Leave',
                'default_duration' => 60.00,
                'description'      => 'Leave for military duties or training.',
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
            [
                'name'             => 'Sterilization Leave',
                'default_duration' => null,
                'description'      => "Leave for sterilization procedures. Duration depends on doctor's consideration.",
                'created_at'       => $now,
                'updated_at'       => $now,
                'created_by'       => 'Migration',
            ],
        ];

        DB::table('leave_types')->insert($data);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('leave_types')
            ->whereIn('name', [
                'Annual Vacation',
                'Traditional Day-Off',
                'Sick (State Disease)',
                'Maternity or Paternity',
                'Compassionate',
                'Career Development Training',
                'Personal Leave',
                'Military Leave',
                'Sterilization Leave',
            ])
            ->delete();
    }
};
