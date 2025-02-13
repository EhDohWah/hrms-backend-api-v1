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
        for ($i = 0; $i < 10; $i++) {
            Employee::create([
                'staff_id'              => 'STAFF-' . Str::upper(Str::random(5)),
                'subsidiary'            => $faker->randomElement(['SMRU', 'BHF']),
                // Use optional() so that some employees won't have an associated user account.
                'first_name'            => $faker->firstName,
                'middle_name'           => $faker->firstName,
                'last_name'             => $faker->lastName,
                'gender'                => $faker->randomElement(['Male', 'Female', 'Other']),
                'date_of_birth'         => $faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
                'status'                => $faker->randomElement(['Expats', 'Local ID', 'Local non ID']),
                'religion'              => $faker->randomElement(['Islam', 'Christianity', 'Hinduism', 'Buddhism', 'Other']),
                'birth_place'           => $faker->city,
                'identification_number' => $faker->numerify('ID-########'),
                'passport_number'       => $faker->numerify('P-########'),
                'bank_name'             => $faker->company . ' Bank',
                'bank_branch'           => $faker->city,
                'bank_account_name'     => $faker->name,
                'bank_account_number'   => $faker->bankAccountNumber,
                'office_phone'          => $faker->phoneNumber,
                'mobile_phone'          => $faker->phoneNumber,
                'height'                => $faker->randomFloat(2, 150, 200), // height in centimeters
                'weight'                => $faker->randomFloat(2, 50, 100),  // weight in kilograms
                'permanent_address'     => $faker->address,
                'current_address'       => $faker->address,
                'stay_with'             => $faker->word,
                'military_status'       => $faker->boolean, // true or false
                'marital_status'        => $faker->randomElement(['Single', 'Married', 'Divorced']),
                // Use optional() for spouse details as not all employees will have these
                'spouse_name'           => $faker->optional()->name,
                'spouse_occupation'     => $faker->optional()->jobTitle,
                'father_name'           => $faker->name,
                'father_occupation'     => $faker->jobTitle,
                'mother_name'           => $faker->name,
                'mother_occupation'     => $faker->jobTitle,
                'driver_license_number' => $faker->numerify('DL-########'),
                'created_by'            => 'Seeder',
                'updated_by'            => 'Seeder',
            ]);
        }
    }
}
