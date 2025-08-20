<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
        $defaultActions = ['create', 'read', 'update', 'delete', 'import', 'export'];
        // For most modules we use CRUD; for reports we only allow export.
        $modules = [
            'user' => $defaultActions,
            'grant' => $defaultActions,
            'interview' => $defaultActions,
            'employee' => $defaultActions,
            'employment' => $defaultActions,
            'employment_history' => $defaultActions,
            'children' => $defaultActions,
            'questionnaire' => $defaultActions,
            'language' => $defaultActions,
            'reference' => $defaultActions,
            'education' => $defaultActions,
            'payroll' => $defaultActions,
            'attendance' => $defaultActions,
            'training' => $defaultActions,
            'reports' => $defaultActions,
            'travel_request' => $defaultActions,
            'leave_request' => $defaultActions,
        ];

        // Create a permission for each module-action pair.
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                ]);
            }
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $hrManagerRole = Role::firstOrCreate(['name' => 'hr-manager']);
        $hrAssistantRole = Role::firstOrCreate(['name' => 'hr-assistant']);
        $employeeRole = Role::firstOrCreate(['name' => 'employee']);

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
