<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define your modules and their respective actions.
        // For most modules we use CRUD; for reports we only allow export.
        $modules = [
            'user'              => ['create', 'read', 'update', 'delete'],
            'grant'             => ['create', 'read', 'update', 'delete'],
            'interview'         => ['create', 'read', 'update', 'delete'],
            'employee'          => ['create', 'read', 'update', 'delete'],
            'employment'        => ['create', 'read', 'update', 'delete'],
            'employment_history'=> ['create', 'read', 'update', 'delete'],
            'children'          => ['create', 'read', 'update', 'delete'],
            'questionnaire'     => ['create', 'read', 'update', 'delete'],
            'language'          => ['create', 'read', 'update', 'delete'],
            'reference'         => ['create', 'read', 'update', 'delete'],
            'education'         => ['create', 'read', 'update', 'delete'],
            'payroll'           => ['create', 'read', 'update', 'delete'],
            'attendance'        => ['create', 'read', 'update', 'delete'],
            'training'          => ['create', 'read', 'update', 'delete'],
            'reports'           => ['export'],
            'travel_request'    => ['create', 'read', 'update', 'delete'],
            'leave_request'     => ['create', 'read', 'update', 'delete'],
        ];

        // Create a permission for each module-action pair.
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}"
                ]);
            }
        }

        // Create roles
        $adminRole       = Role::firstOrCreate(['name' => 'Admin']);
        $hrManagerRole   = Role::firstOrCreate(['name' => 'HR-Manager']);
        $hrAssistantRole = Role::firstOrCreate(['name' => 'HR-Assistant']);
        $employeeRole    = Role::firstOrCreate(['name' => 'Employee']);

        // Full access roles: Admin, HR-Manager, HR-Assistant get every permission.
        $fullPermissions = Permission::all();
        $adminRole->syncPermissions($fullPermissions);
        $hrManagerRole->syncPermissions($fullPermissions);
        $hrAssistantRole->syncPermissions($fullPermissions);

        // Limited permissions for Employee:
        // They can view and update their profile (user module) and can manage
        // attendance, travel requests, and leave requests (only create, read, update).
        $employeePermissions = [
            'user.read',
            'user.update',
            'attendance.create', 'attendance.read', 'attendance.update',
            'travel_request.create', 'travel_request.read', 'travel_request.update',
            'leave_request.create', 'leave_request.read', 'leave_request.update',
        ];

        $employeeRole->syncPermissions($employeePermissions);
    }
}
