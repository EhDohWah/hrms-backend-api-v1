<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Retrieve test employees by their unique staff_id
        $employee1 = DB::table('employees')->where('staff_id', 'STAFF-TEST1')->first();
        $employee2 = DB::table('employees')->where('staff_id', 'STAFF-TEST2')->first();
        $employee3 = DB::table('employees')->where('staff_id', 'STAFF-TEST3')->first();
        $employee4 = DB::table('employees')->where('staff_id', 'STAFF-TEST4')->first();

        // Retrieve department positions
        $departmentPosition1 = DB::table('department_positions')->where('id', 1)->first();
        $departmentPosition2 = DB::table('department_positions')->where('id', 2)->first();
        $departmentPosition3 = DB::table('department_positions')->where('id', 3)->first();
        $departmentPosition4 = DB::table('department_positions')->where('id', 4)->first();

        // Insert employment records using sample data
        DB::table('employments')->insert([
            [
                'employee_id'             => $employee1->id,
                'employment_type'         => 'Full-Time',
                'start_date'              => '2020-01-01',
                'probation_end_date'      => '2020-03-31',
                'end_date'                => null,
                'department_position_id'  => null,
                'work_location'           => 'Headquarters',
                'position_salary'         => 3000.00,
                'probation_salary'        => 2800.00,
                'supervisor_id'           => null,
                'employee_tax'            => 10.00,
                'fte'                     => 1.0,
                'active'                  => true,
                'health_welfare'          => true,
                'pvd'                     => true,
                'saving_fund'             => false,
                'social_security_id'      => 'SSN-TEST1',
                'created_at'              => now(),
                'updated_at'              => now(),
                'created_by'              => 'Migration',
                'updated_by'              => 'Migration',
            ],
            [
                'employee_id'             => $employee2->id,
                'employment_type'         => 'Full-Time',
                'start_date'              => '2021-02-01',
                'probation_end_date'      => '2021-04-30',
                'end_date'                => null,
                'department_position_id'  => null,
                'work_location'           => 'Headquarters',
                'position_salary'         => 3200.00,
                'probation_salary'        => 3000.00,
                'supervisor_id'           => null,
                'employee_tax'            => 12.00,
                'fte'                     => 1.0,
                'active'                  => true,
                'health_welfare'          => true,
                'pvd'                     => false,
                'saving_fund'             => true,
                'social_security_id'      => 'SSN-TEST2',
                'created_at'              => now(),
                'updated_at'              => now(),
                'created_by'              => 'Migration',
                'updated_by'              => 'Migration',
            ],
            [
                'employee_id'             => $employee3->id,
                'employment_type'         => 'Full-Time',
                'start_date'              => '2019-05-15',
                'probation_end_date'      => '2019-08-15',
                'end_date'                => null,
                'department_position_id'  => null,
                'work_location'           => 'Headquarters',
                'position_salary'         => 2900.00,
                'probation_salary'        => 2700.00,
                'supervisor_id'           => null,
                'employee_tax'            => 9.50,
                'fte'                     => 1.0,
                'active'                  => true,
                'health_welfare'          => false,
                'pvd'                     => true,
                'saving_fund'             => false,
                'social_security_id'      => 'SSN-TEST3',
                'created_at'              => now(),
                'updated_at'              => now(),
                'created_by'              => 'Migration',
                'updated_by'              => 'Migration',
            ],
            [
                'employee_id'             => $employee4->id,
                'employment_type'         => 'Full-Time',
                'start_date'              => '2018-09-01',
                'probation_end_date'      => '2018-11-30',
                'end_date'                => null,
                'department_position_id'  => null,
                'work_location'           => 'Headquarters',
                'position_salary'         => 3100.00,
                'probation_salary'        => 2900.00,
                'supervisor_id'           => null,
                'employee_tax'            => 11.00,
                'fte'                     => 1.0,
                'active'                  => true,
                'health_welfare'          => true,
                'pvd'                     => true,
                'saving_fund'             => true,
                'social_security_id'      => 'SSN-TEST4',
                'created_at'              => now(),
                'updated_at'              => now(),
                'created_by'              => 'Migration',
                'updated_by'              => 'Migration',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the inserted employment records based on test employee staff IDs.
        $employeeIds = DB::table('employees')
            ->whereIn('staff_id', ['STAFF-TEST1', 'STAFF-TEST2', 'STAFF-TEST3', 'STAFF-TEST4'])
            ->pluck('id')
            ->toArray();

        DB::table('employments')->whereIn('employee_id', $employeeIds)->delete();
    }
};
