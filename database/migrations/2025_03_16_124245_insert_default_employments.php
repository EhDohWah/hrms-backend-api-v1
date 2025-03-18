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
        // Retrieve test employees by their unique staff_id
        $employee1 = DB::table('employees')->where('staff_id', 'STAFF-TEST1')->first();
        $employee2 = DB::table('employees')->where('staff_id', 'STAFF-TEST2')->first();
        $employee3 = DB::table('employees')->where('staff_id', 'STAFF-TEST3')->first();
        $employee4 = DB::table('employees')->where('staff_id', 'STAFF-TEST4')->first();

        // Retrieve required foreign key records:
        // Employment Type - "Full time"
        $fullTime = DB::table('employment_types')->where('name', 'Full time')->first();

        // Positions (assuming these test positions exist)
        $position1 = DB::table('positions')->where('title', 'Medic')->first();
        $position2 = DB::table('positions')->where('title', 'Midwife')->first();
        $position3 = DB::table('positions')->where('title', 'Healthworker')->first();
        $position4 = DB::table('positions')->where('title', 'HR-assistant')->first();

        // Departments (assuming these departments exist)
        $department1 = DB::table('departments')->where('name', 'Admin')->first();
        $department2 = DB::table('departments')->where('name', 'HR')->first();
        $department3 = DB::table('departments')->where('name', 'DATA-MANAGEMENT')->first();
        $department4 = DB::table('departments')->where('name', 'IT')->first();

        // Work location (assuming "Headquarters" exists)
        $workLocation = DB::table('work_locations')->where('name', 'Headquarters')->first();

        // Insert employment records using sample data
        DB::table('employments')->insert([
            [
                'employee_id'         => $employee1->id,
                'employment_type_id'  => $fullTime->id,
                'start_date'          => '2020-01-01',
                'probation_end_date'  => '2020-03-31',
                'end_date'            => null,
                'position_id'         => $position1->id,
                'department_id'       => $department1->id,
                'work_location_id'    => $workLocation->id,
                'position_salary'     => 3000.00,
                'probation_salary'    => 2800.00,
                'supervisor_id'       => null,
                'employee_tax'        => 10.00,
                'fte'                 => 1.0,
                'active'              => true,
                'health_welfare'      => true,
                'pvd'                 => true,
                'saving_fund'         => false,
                'social_security_id'  => 'SSN-TEST1',
                'created_at'          => now(),
                'updated_at'          => now(),
                'created_by'          => 'Migration',
                'updated_by'          => 'Migration',
            ],
            [
                'employee_id'         => $employee2->id,
                'employment_type_id'  => $fullTime->id,
                'start_date'          => '2021-02-01',
                'probation_end_date'  => '2021-04-30',
                'end_date'            => null,
                'position_id'         => $position2->id,
                'department_id'       => $department2->id,
                'work_location_id'    => $workLocation->id,
                'position_salary'     => 3200.00,
                'probation_salary'    => 3000.00,
                'supervisor_id'       => null,
                'employee_tax'        => 12.00,
                'fte'                 => 1.0,
                'active'              => true,
                'health_welfare'      => true,
                'pvd'                 => false,
                'saving_fund'         => true,
                'social_security_id'  => 'SSN-TEST2',
                'created_at'          => now(),
                'updated_at'          => now(),
                'created_by'          => 'Migration',
                'updated_by'          => 'Migration',

            ],
            [
                'employee_id'         => $employee3->id,
                'employment_type_id'  => $fullTime->id,
                'start_date'          => '2019-05-15',
                'probation_end_date'  => '2019-08-15',
                'end_date'            => null,
                'position_id'         => $position3->id,
                'department_id'       => $department3->id,
                'work_location_id'    => $workLocation->id,
                'position_salary'     => 2900.00,
                'probation_salary'    => 2700.00,
                'supervisor_id'       => null,
                'employee_tax'        => 9.50,
                'fte'                 => 1.0,
                'active'              => true,
                'health_welfare'      => false,
                'pvd'                 => true,
                'saving_fund'         => false,
                'social_security_id'  => 'SSN-TEST3',
                'created_at'          => now(),
                'updated_at'          => now(),
                'created_by'          => 'Migration',
                'updated_by'          => 'Migration',
            ],
            [
                'employee_id'         => $employee4->id,
                'employment_type_id'  => $fullTime->id,
                'start_date'          => '2018-09-01',
                'probation_end_date'  => '2018-11-30',
                'end_date'            => null,
                'position_id'         => $position4->id,
                'department_id'       => $department4->id,
                'work_location_id'    => $workLocation->id,
                'position_salary'     => 3100.00,
                'probation_salary'    => 2900.00,
                'supervisor_id'       => null,
                'employee_tax'        => 11.00,
                'fte'                 => 1.0,
                'active'              => true,
                'health_welfare'      => true,
                'pvd'                 => true,
                'saving_fund'         => true,
                'social_security_id'  => 'SSN-TEST4',
                'created_at'          => now(),
                'updated_at'          => now(),
                'created_by'          => 'Migration',
                'updated_by'          => 'Migration',
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
