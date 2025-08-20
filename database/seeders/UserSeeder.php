<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'), // Change the password as needed.
                'last_login_at' => now(),
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]
        );
        $admin->assignRole('Admin');
        $admin->syncPermissions(Permission::all());

        // Create HR Manager user
        $hrManager = User::firstOrCreate(
            ['email' => 'hrmanager@hrms.com'],
            [
                'name' => 'HR Manager',
                'password' => Hash::make('password'),
                'last_login_at' => now(),
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]
        );
        $hrManager->assignRole('HR-Manager');
        $hrManager->syncPermissions(Permission::all());
        // Create HR Assistant user
        $hrAssistant = User::firstOrCreate(
            ['email' => 'hrassistant@hrms.com'],
            [
                'name' => 'HR Assistant',
                'password' => Hash::make('password'),
                'last_login_at' => now(),
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]
        );
        $hrAssistant->assignRole('HR-Assistant');
        $hrAssistant->syncPermissions(Permission::all());

        // Create Employee user
        $employee = User::firstOrCreate(
            ['email' => 'employee@hrms.com'],
            [
                'name' => 'Employee User',
                'password' => Hash::make('password'),
                'last_login_at' => now(),
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]
        );
        $employee->assignRole('Employee');
        // assign permission to the employee
        $employee->syncPermissions([
            'user.read',
            'user.update',
            'attendance.create', 'attendance.read', 'attendance.update',
            'travel_request.create', 'travel_request.read', 'travel_request.update',
            'leave_request.create', 'leave_request.read', 'leave_request.update',
        ]);

    }
}
