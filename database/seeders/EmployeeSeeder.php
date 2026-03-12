<?php

namespace Database\Seeders;

use App\Enums\IdentificationType;
use App\Models\Employee;
use App\Models\EmployeeIdentification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        // Check if employees already exist
        if (Employee::count() > 0) {
            $this->command->info('Employees already exist, skipping EmployeeSeeder.');

            return;
        }

        $identificationTypes = IdentificationType::values();

        DB::transaction(function () use ($faker, $identificationTypes) {
            // Disable observers to avoid:
            // - EmployeeObserver::created firing LeaveType::all() + LeaveBalance::create per employee (~900 extra queries)
            // - EmployeeIdentificationObserver::created syncing names back to employee (redundant SELECT + UPDATE)
            Employee::withoutEvents(function () use ($faker, $identificationTypes) {
                EmployeeIdentification::withoutEvents(function () use ($faker, $identificationTypes) {
                    for ($i = 0; $i < 100; $i++) {
                        $firstName = $faker->firstName;
                        $lastName = $faker->lastName;
                        $initialEn = $faker->randomElement(['Mr.', 'Ms.', 'Dr.']);
                        $firstNameTh = $faker->firstName;
                        $lastNameTh = $faker->lastName;
                        $initialTh = $faker->randomElement(['นาย', 'นาง', 'นางสาว']);

                        $employee = Employee::create([
                            'staff_id' => str_pad($i + 1, 4, '0', STR_PAD_LEFT),

                            // Name fields
                            'initial_en' => $initialEn,
                            'first_name_en' => $firstName,
                            'last_name_en' => $lastName,
                            'first_name_th' => $firstNameTh,
                            'last_name_th' => $lastNameTh,
                            'initial_th' => $initialTh,

                            // Personal information
                            'gender' => $faker->randomElement(['M', 'F']),
                            'date_of_birth' => $faker->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
                            'status' => $faker->randomElement(['Expats (Local)', 'Local ID Staff', 'Local non ID Staff']),
                            'nationality' => $faker->randomElement(['Thai', 'Myanmar', 'American', 'British', 'Australian']),
                            'religion' => $faker->randomElement(['Islam', 'Christianity', 'Hinduism', 'Buddhism', 'Other']),

                            // Government identification
                            'social_security_number' => $faker->numerify('###########'),
                            'tax_number' => $faker->numerify('##########'),

                            // Banking information
                            'bank_name' => $faker->company.' Bank',
                            'bank_branch' => $faker->city,
                            'bank_account_name' => $faker->name,
                            'bank_account_number' => $faker->bankAccountNumber,

                            // Contact information
                            'mobile_phone' => $faker->phoneNumber,
                            'permanent_address' => $faker->address,
                            'current_address' => $faker->address,

                            // Personal status
                            'military_status' => $faker->boolean(),
                            'marital_status' => $faker->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),

                            // Family information
                            'spouse_name' => $faker->optional(0.3)->name,
                            'spouse_phone_number' => $faker->optional(0.3)->phoneNumber,

                            // Emergency contact
                            'emergency_contact_person_name' => $faker->name,
                            'emergency_contact_person_relationship' => $faker->randomElement(['Spouse', 'Parent', 'Sibling', 'Friend']),
                            'emergency_contact_person_phone' => $faker->phoneNumber,

                            // Parents information
                            'father_name' => $faker->name,
                            'father_occupation' => $faker->jobTitle,
                            'father_phone_number' => $faker->optional(0.7)->phoneNumber,
                            'mother_name' => $faker->name,
                            'mother_occupation' => $faker->jobTitle,
                            'mother_phone_number' => $faker->optional(0.7)->phoneNumber,

                            // Other details
                            'driver_license_number' => $faker->optional(0.8)->numerify('DL-########'),
                            'remark' => $faker->optional(0.2)->sentence,

                            // Audit fields
                            'created_by' => 'Seeder',
                            'updated_by' => 'Seeder',
                        ]);

                        // Create primary identification record
                        EmployeeIdentification::create([
                            'employee_id' => $employee->id,
                            'identification_type' => $faker->randomElement($identificationTypes),
                            'identification_number' => $faker->numerify('##########'),
                            'identification_issue_date' => $faker->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
                            'identification_expiry_date' => $faker->dateTimeBetween('+1 year', '+5 years')->format('Y-m-d'),
                            'first_name_en' => $firstName,
                            'last_name_en' => $lastName,
                            'initial_en' => $initialEn,
                            'first_name_th' => $firstNameTh,
                            'last_name_th' => $lastNameTh,
                            'initial_th' => $initialTh,
                            'is_primary' => true,
                            'created_by' => 'Seeder',
                            'updated_by' => 'Seeder',
                        ]);
                    }
                });
            });
        });
    }
}
