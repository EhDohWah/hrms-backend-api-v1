<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Employee;
use Illuminate\Support\Str;
use Faker\Generator as Faker;


class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        // Create 10 sample employee records
        for ($i = 0; $i < 100; $i++) {
            Employee::create([
                'staff_id'              => str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'subsidiary'            => $faker->randomElement(['SMRU', 'BHF']),
                
                // Name fields (both English and Thai)
                'initial_en'            => $faker->randomElement(['Mr.', 'Ms.', 'Dr.']),
                'initial_th'            => $faker->randomElement(['นาย', 'นาง', 'นางสาว']),
                'first_name_en'         => $faker->firstName,
                'last_name_en'          => $faker->lastName,
                'first_name_th'         => $faker->firstName,
                'last_name_th'          => $faker->lastName,
                
                // Personal information
                'gender'                => $faker->randomElement(['Male', 'Female', 'Other']),
                'date_of_birth'         => $faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
                'status'                => $faker->randomElement(['Expats', 'Local ID', 'Local non ID']),
                'nationality'           => $faker->randomElement(['Thai', 'Myanmar', 'American', 'British', 'Australian']),
                'religion'              => $faker->randomElement(['Islam', 'Christianity', 'Hinduism', 'Buddhism', 'Other']),
                
                // Government identification
                'social_security_number' => $faker->numerify('###########'),
                'tax_number'            => $faker->numerify('##########'),
                
                // Banking information
                'bank_name'             => $faker->company . ' Bank',
                'bank_branch'           => $faker->city,
                'bank_account_name'     => $faker->name,
                'bank_account_number'   => $faker->bankAccountNumber,
                
                // Contact information
                'mobile_phone'          => $faker->phoneNumber,
                'permanent_address'     => $faker->address,
                'current_address'       => $faker->address,
                
                // Personal status
                'military_status'       => $faker->randomElement(['Exempt', 'Completed', 'Deferred', 'Not Applicable']),
                'marital_status'        => $faker->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
                
                // Family information
                'spouse_name'           => $faker->optional(0.3)->name,
                'spouse_phone_number'   => $faker->optional(0.3)->phoneNumber,
                
                // Emergency contact
                'emergency_contact_person_name' => $faker->name,
                'emergency_contact_person_relationship' => $faker->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend']),
                'emergency_contact_person_phone' => $faker->phoneNumber,
                
                // Parents information
                'father_name'           => $faker->name,
                'father_occupation'     => $faker->jobTitle,
                'father_phone_number'   => $faker->optional(0.7)->phoneNumber,
                'mother_name'           => $faker->name,
                'mother_occupation'     => $faker->jobTitle,
                'mother_phone_number'   => $faker->optional(0.7)->phoneNumber,
                
                // Other details
                'driver_license_number' => $faker->optional(0.8)->numerify('DL-########'),
                'remark'                => $faker->optional(0.2)->sentence,
                
                // Audit fields
                'created_by'            => 'Seeder',
                'updated_by'            => 'Seeder',
            ]);
        }
    }
}
