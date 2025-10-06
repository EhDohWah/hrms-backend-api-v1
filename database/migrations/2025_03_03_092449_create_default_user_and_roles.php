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
            'tax' => $defaultActions,
            'personnel_action' => $defaultActions,
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
        $hrAssistantJuniorRole = Role::firstOrCreate(['name' => 'hr-assistant-junior']);
        $hrAssistantSeniorRole = Role::firstOrCreate(['name' => 'hr-assistant-senior']);
        $siteAdminRole = Role::firstOrCreate(['name' => 'site-admin']);

        // Assign permissions per role configuration
        $fullPermissions = Permission::all();

        // Admin: full access
        $adminRole->syncPermissions($fullPermissions);

        // HR Manager: full access
        $hrManagerRole->syncPermissions($fullPermissions);

        // HR Assistant Senior: all except grant.*
        $hrAssistantSeniorPermissions = $fullPermissions->filter(fn ($permission) => strpos($permission->name, 'grant.') !== 0);
        $hrAssistantSeniorRole->syncPermissions($hrAssistantSeniorPermissions);

        // HR Assistant Junior: all except grant.*, employment.*, payroll.*, reports.*
        $juniorExclusions = ['grant.', 'employment.', 'payroll.', 'reports.'];
        $hrAssistantJuniorPermissions = $fullPermissions->filter(function ($permission) use ($juniorExclusions) {
            foreach ($juniorExclusions as $prefix) {
                if (strpos($permission->name, $prefix) === 0) {
                    return false;
                }
            }

            return true;
        });
        $hrAssistantJuniorRole->syncPermissions($hrAssistantJuniorPermissions);

        // Site Admin: only leave_request.*, travel_request.*, training.*
        $siteAdminAllowed = ['leave_request.', 'travel_request.', 'training.'];
        $siteAdminPermissions = $fullPermissions->filter(function ($permission) use ($siteAdminAllowed) {
            foreach ($siteAdminAllowed as $prefix) {
                if (strpos($permission->name, $prefix) === 0) {
                    return true;
                }
            }

            return false;
        });
        $siteAdminRole->syncPermissions($siteAdminPermissions);

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

        // Create a default HR Assistant Junior user if not already exists
        $defaultHrAssistantJuniorEmail = 'hrassistant.junior@hrms.com';
        if (! User::where('email', $defaultHrAssistantJuniorEmail)->exists()) {
            $hrAssistantJuniorUser = User::create([
                'name' => 'HR Assistant Junior User',
                'email' => $defaultHrAssistantJuniorEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $hrAssistantJuniorUser->assignRole($hrAssistantJuniorRole);
            $hrAssistantJuniorUser->syncPermissions($hrAssistantJuniorPermissions);
        }

        // Create a default HR Assistant Senior user if not already exists
        $defaultHrAssistantSeniorEmail = 'hrassistant.senior@hrms.com';
        if (! User::where('email', $defaultHrAssistantSeniorEmail)->exists()) {
            $hrAssistantSeniorUser = User::create([
                'name' => 'HR Assistant Senior User',
                'email' => $defaultHrAssistantSeniorEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $hrAssistantSeniorUser->assignRole($hrAssistantSeniorRole);
            $hrAssistantSeniorUser->syncPermissions($hrAssistantSeniorPermissions);
        }

        // Create a default Site Admin user if not already exists
        $defaultSiteAdminEmail = 'siteadmin@hrms.com';
        if (! User::where('email', $defaultSiteAdminEmail)->exists()) {
            $siteAdminUser = User::create([
                'name' => 'Site Admin User',
                'email' => $defaultSiteAdminEmail,
                'password' => Hash::make('password'), // Change this to a secure password for production
                'created_by' => 'system',
                'updated_by' => 'system',
                'last_login_at' => null,
                'status' => 'active',
            ]);
            $siteAdminUser->assignRole($siteAdminRole);
            $siteAdminUser->syncPermissions($siteAdminPermissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally, you could delete the default users created by this migration.
        // Be cautious: you may not want to remove roles/permissions in production.

        // Delete default users by email
        foreach ([
            'admin@hrms.com',
            'hrmanager@hrms.com',
            'hrassistant.junior@hrms.com',
            'hrassistant.senior@hrms.com',
            'siteadmin@hrms.com',
        ] as $email) {
            User::where('email', $email)->delete();
        }

        // Optionally, remove the roles if needed:
        // Role::whereIn('name', ['hr-manager', 'hr-assistant-junior', 'hr-assistant-senior', 'site-admin'])->delete();

        // Similarly for permissions if you really intend to remove them:
        // Permission::whereIn('name', Permission::pluck('name'))->delete();
    }
};
