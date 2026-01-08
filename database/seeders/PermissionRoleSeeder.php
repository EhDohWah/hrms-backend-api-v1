<?php

namespace Database\Seeders;

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

        // Define simplified actions: read and edit only
        $defaultActions = ['read', 'edit'];

        // Get permission module names from Module table's read_permission field
        // Extract unique permission prefixes (e.g., 'admin', 'user', 'grant')
        $modules = \App\Models\Module::all();
        $moduleNames = [];

        if ($modules->isNotEmpty()) {
            foreach ($modules as $module) {
                if ($module->read_permission) {
                    // Extract prefix before '.read' (e.g., 'admin.read' => 'admin')
                    $prefix = str_replace('.read', '', $module->read_permission);
                    $moduleNames[] = $prefix;
                }
            }
            $moduleNames = array_unique($moduleNames);
        }

        // If Module table is empty (first time seeding), use hardcoded permission prefixes
        // These MUST match the module names in ModuleSeeder exactly!
        // NOTE: Only actionable submenus are listed here - NOT parent menu names!
        if (empty($moduleNames)) {
            $moduleNames = [
                // Dashboard (1 - unified for all users)
                'dashboard',
                // Grants submenus (2)
                'grants_list',
                'grant_position',
                // Recruitment submenus (2)
                'interviews',
                'job_offers',
                // Employee submenus (3)
                'employees',
                'employment_records',
                'employee_resignation',
                'employee_funding_allocations', // new module
                // HRM standalone items (3)
                'holidays',
                'resignation',
                'termination',
                // Leaves submenus (5)
                'leaves_admin',
                'leaves_employee',
                'leave_settings',
                'leave_types',
                'leave_balances',
                // Travel submenus (2)
                'travel_admin',
                'travel_employee',
                // Attendance submenus (5)
                'attendance_admin',
                'attendance_employee',
                'timesheets',
                'shift_schedule',
                'overtime',
                // Training submenus (2)
                'training_list',
                'employee_training',
                // Payroll submenus (5)
                'employee_salary',
                'tax_settings',
                'benefit_settings',
                'payslip',
                'payroll_items',
                // Lookups submenus (1)
                'lookup_list',
                // Organization Structure submenus (4)
                'sites',
                'departments',
                'positions',
                'section_departments',
                // User Management submenus (2)
                'users',
                'roles',
                // Reports submenus (12)
                'report_list',
                'expense_report',
                'invoice_report',
                'payment_report',
                'project_report',
                'task_report',
                'user_report',
                'employee_report',
                'payslip_report',
                'attendance_report',
                'leave_report',
                'daily_report',
                // Administration standalone (1)
                'file_uploads',
                // Recycle Bin submenus (1)
                'recycle_bin_list',
            ];
        }

        // Build modules array with default actions
        $modules = [];
        foreach ($moduleNames as $moduleName) {
            $modules[$moduleName] = $defaultActions;
        }

        // Create a permission for each module-action pair.
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                ]);
            }
        }

        // Create only core system roles (protected roles)
        // Other organizational roles will be created dynamically via Role Management UI
        $coreRoles = [
            'admin' => 'System Administrator',
            'hr-manager' => 'HR Manager',
        ];

        foreach ($coreRoles as $roleName => $description) {
            Role::firstOrCreate(['name' => $roleName]);
            $this->command->info("✓ Created core role: {$description} ({$roleName})");
        }

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Permission & Role Seeder Complete!');
        $this->command->info('Permissions created: '.Permission::count());
        $this->command->info('Core roles created: '.count($coreRoles));
        $this->command->info('');
        $this->command->info('NOTES:');
        $this->command->info('- Admin and HR Manager get auto-permissions via UserSeeder');
        $this->command->info('- Additional roles can be created via Role Management UI');
        $this->command->info('- Other users get permissions assigned via UI by Admin/HR Manager');
        $this->command->info('========================================');
    }
}
