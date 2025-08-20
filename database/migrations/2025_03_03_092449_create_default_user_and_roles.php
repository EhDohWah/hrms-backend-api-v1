<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define default actions and modules
        $defaultActions = ['create', 'read', 'update', 'delete', 'import', 'export'];
        $modules = [
            'admin' => $defaultActions,
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
            'job_offer' => $defaultActions,
            'budget_line' => $defaultActions,
            'position_slot' => $defaultActions,
            'tax' => $defaultActions,
        ];

        // Create each permission (if not exists)
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

        // Full access for Admin, HR Manager, and HR Assistant
        $fullPermissions = Permission::all();
        $adminRole->syncPermissions($fullPermissions);
        $hrManagerRole->syncPermissions($fullPermissions);
        $hrAssistantRole->syncPermissions($fullPermissions);

        // Limited permissions for Employee
        $employeePermissions = [
            'user.read',
            'user.update',
            'attendance.create',
            'attendance.read',
            'travel_request.create',
            'travel_request.read',
            'travel_request.update',
            'leave_request.create',
            'leave_request.read',
            'leave_request.update',
        ];
        $employeeRole->syncPermissions($employeePermissions);

        // Create a default admin user if not already exists
        $defaultAdminEmail = 'admin@hrms.com';
        if (! User::where('email', $defaultAdminEmail)->exists()) {
            $adminUser = User::create([
                'name' => 'Admin User',
                'email' => $defaultAdminEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $adminUser->assignRole($adminRole);
            $adminUser->syncPermissions($fullPermissions);
        }

        // Create a default hr manager user if not already exists
        $defaultHrManagerEmail = 'hrmanager@hrms.com';
        if (! User::where('email', $defaultHrManagerEmail)->exists()) {
            $hrManagerUser = User::create([
                'name' => 'HR Manager User',
                'email' => $defaultHrManagerEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $hrManagerUser->assignRole($hrManagerRole);
            $hrManagerUser->syncPermissions($fullPermissions);
        }

        // Create a default hr assistant user if not already exists
        $defaultHrAssistantEmail = 'hrassistant@hrms.com';
        if (! User::where('email', $defaultHrAssistantEmail)->exists()) {
            $hrAssistantUser = User::create([
                'name' => 'HR Assistant User',
                'email' => $defaultHrAssistantEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $hrAssistantUser->assignRole($hrAssistantRole);
            $hrAssistantUser->syncPermissions($fullPermissions);
        }

        // Create a default employee user if not already exists
        $defaultEmployeeEmail = 'employee@hrms.com';
        if (! User::where('email', $defaultEmployeeEmail)->exists()) {
            $employeeUser = User::create([
                'name' => 'Employee User',
                'email' => $defaultEmployeeEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $employeeUser->assignRole($employeeRole);
            $employeeUser->syncPermissions($employeePermissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally, you could delete the default admin user.
        // Be cautious: you may not want to remove roles/permissions in production.
        $defaultAdminEmail = 'admin@hrms.com';
        User::where('email', $defaultAdminEmail)->delete();

        // Optionally, remove the roles if needed:
        // Role::whereIn('name', ['admin', 'hr-manager', 'hr-assistant', 'employee'])->delete();

        // Similarly for permissions if you really intend to remove them:
        // Permission::whereIn('name', collect($modules)->flatten())->delete();
    }
};
