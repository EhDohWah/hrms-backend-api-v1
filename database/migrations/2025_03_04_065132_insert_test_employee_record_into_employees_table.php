<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('employees')->insert([
            'staff_id'                    => 'STAFF-TEST1',
            'subsidiary'                  => 'SMRU',
            'user_id'                     => null,
            'first_name'                  => 'Test',
            'middle_name'                 => 'Employee',
            'last_name'                   => 'One',
            'gender'                      => 'Male',
            'date_of_birth'               => '1990-01-01',
            'status'                      => 'Local ID',
            'religion'                    => 'Other',
            'birth_place'                 => 'Test City',
            'identification_number'       => 'ID-00000001',
            'social_security_number'      => null,
            'tax_identification_number'   => null,
            'passport_number'             => 'P-00000001',
            'bank_name'                   => 'Test Bank',
            'bank_branch'                 => 'Test Branch',
            'bank_account_name'           => 'Test Employee One',
            'bank_account_number'         => '0000000001',
            'office_phone'                => '1234567890',
            'mobile_phone'                => '0987654321',
            'permanent_address'           => '123 Test Street, Test City',
            'current_address'             => '123 Test Street, Test City',
            'stay_with'                   => 'Family',
            'military_status'             => false,
            'marital_status'              => 'Single',
            'spouse_name'                 => null,
            'spouse_occupation'           => null,
            'father_name'                 => 'Test Father',
            'father_occupation'           => 'Engineer',
            'mother_name'                 => 'Test Mother',
            'mother_occupation'           => 'Teacher',
            'driver_license_number'       => 'DL-00000001',
            'created_by'                  => 'Migration',
            'updated_by'                  => 'Migration',
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the test record by matching the unique staff_id
        DB::table('employees')->where('staff_id', 'STAFF-TEST1')->delete();
    }
};
