<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-Time Conversion Seeder — converts old granular CRUD permissions to read/edit model.
 *
 * This seeder migrates existing user permissions from the old format:
 *   module.create, module.read, module.update, module.delete, module.import, module.export
 * To the simplified format:
 *   module.read, module.edit
 *
 * Conversion rules:
 *   - module.read                                    → module.read
 *   - module.create/update/delete/import/export (any) → module.edit
 *
 * Safety: Automatically skips if no old granular permissions exist (already converted).
 *
 * Usage (manual only — NOT included in DatabaseSeeder):
 *   php artisan db:seed --class=ConvertPermissionsToReadEditSeeder
 */
class ConvertPermissionsToReadEditSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info(' Permission Conversion: CRUD → Read/Edit');
        $this->command->info('========================================');
        $this->command->info('');

        // Safety check: skip if no old granular permissions exist
        $oldPermissionCount = Permission::where(function ($query) {
            $query->where('name', 'like', '%.create')
                ->orWhere('name', 'like', '%.update')
                ->orWhere('name', 'like', '%.delete')
                ->orWhere('name', 'like', '%.import')
                ->orWhere('name', 'like', '%.export');
        })->count();

        if ($oldPermissionCount === 0) {
            $this->command->info('No old granular permissions found — already converted or fresh install.');
            $this->command->info('Skipping conversion.');
            $this->command->info('');

            return;
        }

        $this->command->info("Found {$oldPermissionCount} old granular permissions to convert.");
        $this->command->info('');

        // Step 1: Build conversion mapping for existing user permissions
        $this->command->info('Building user permission mapping...');
        $userPermissionMapping = $this->buildUserPermissionMapping();
        $this->command->info('  Mapped '.count($userPermissionMapping).' users.');

        // Step 2: Get module names from Module table (with hardcoded fallback)
        $this->command->info('Fetching modules...');
        $moduleNames = $this->getModuleNames();
        $this->command->info('  Found '.count($moduleNames).' modules.');

        // Step 3: Delete old granular permissions
        $this->command->info('Deleting old granular permissions (create, update, delete, import, export)...');
        $deletedCount = Permission::where(function ($query) {
            $query->where('name', 'like', '%.create')
                ->orWhere('name', 'like', '%.update')
                ->orWhere('name', 'like', '%.delete')
                ->orWhere('name', 'like', '%.import')
                ->orWhere('name', 'like', '%.export');
        })->delete();
        $this->command->info("  Deleted {$deletedCount} granular permissions.");

        // Step 4: Create new read/edit permissions for all modules
        $this->command->info('Creating read/edit permissions for all modules...');
        foreach ($moduleNames as $moduleName) {
            Permission::firstOrCreate([
                'name' => "{$moduleName}.read",
                'guard_name' => 'web',
            ]);
            Permission::firstOrCreate([
                'name' => "{$moduleName}.edit",
                'guard_name' => 'web',
            ]);
        }
        $this->command->info('  Created permissions for '.count($moduleNames).' modules.');

        // Step 5: Re-assign permissions to users based on mapping
        $this->command->info('Re-assigning user permissions...');
        $userCount = 0;
        foreach ($userPermissionMapping as $userId => $modulePermissions) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            $newPermissions = [];
            foreach ($modulePermissions as $moduleName => $access) {
                if ($access['hasRead']) {
                    $newPermissions[] = "{$moduleName}.read";
                }
                if ($access['hasEdit']) {
                    $newPermissions[] = "{$moduleName}.edit";
                }
            }

            $user->syncPermissions($newPermissions);
            $userCount++;
        }
        $this->command->info("  Updated permissions for {$userCount} users.");

        // Step 6: Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info(' Conversion complete!');
        $this->command->info("  Deleted: {$deletedCount} old permissions");
        $this->command->info('  Created: '.(count($moduleNames) * 2).' new permissions');
        $this->command->info("  Users updated: {$userCount}");
        $this->command->info('========================================');
        $this->command->info('');
    }

    /**
     * Get module names from the Module table, with hardcoded fallback.
     */
    private function getModuleNames(): array
    {
        $modules = Module::all();
        $moduleNames = [];

        if ($modules->isNotEmpty()) {
            foreach ($modules as $module) {
                if ($module->read_permission) {
                    $prefix = str_replace('.read', '', $module->read_permission);
                    $moduleNames[] = $prefix;
                }
            }

            return array_unique($moduleNames);
        }

        // Fallback: hardcoded list (must match ModuleSeeder)
        return [
            'dashboard',
            'grants_list', 'grant_position',
            'interviews', 'job_offers',
            'employees', 'employment_records', 'employee_resignation', 'employee_funding_allocations',
            'holidays', 'resignation', 'termination',
            'leaves_admin', 'leave_types', 'leave_balances',
            'travel_admin',
            'attendance_admin', 'attendance_employee', 'timesheets', 'shift_schedule', 'overtime',
            'training_list', 'employee_training',
            'employee_salary', 'tax_settings', 'benefit_settings', 'payslip', 'payroll_items',
            'lookup_list',
            'sites', 'departments', 'positions', 'section_departments',
            'users', 'roles',
            'report_list', 'expense_report', 'invoice_report', 'payment_report',
            'project_report', 'task_report', 'user_report', 'employee_report',
            'payslip_report', 'attendance_report', 'leave_report', 'daily_report',
            'file_uploads',
            'recycle_bin_list',
        ];
    }

    /**
     * Build a mapping of current user permissions before conversion.
     *
     * Returns: [
     *   user_id => [
     *     module_name => ['hasRead' => bool, 'hasEdit' => bool]
     *   ]
     * ]
     */
    private function buildUserPermissionMapping(): array
    {
        $mapping = [];

        $users = User::with('permissions')->get();

        foreach ($users as $user) {
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            if (empty($userPermissions)) {
                continue;
            }

            $mapping[$user->id] = [];

            foreach ($userPermissions as $permissionName) {
                if (! str_contains($permissionName, '.')) {
                    continue;
                }

                [$moduleName, $action] = explode('.', $permissionName, 2);

                if (! isset($mapping[$user->id][$moduleName])) {
                    $mapping[$user->id][$moduleName] = [
                        'hasRead' => false,
                        'hasEdit' => false,
                    ];
                }

                if ($action === 'read') {
                    $mapping[$user->id][$moduleName]['hasRead'] = true;
                }

                // Any write action (create, update, delete, import, export) maps to edit
                if (in_array($action, ['create', 'update', 'delete', 'import', 'export'])) {
                    $mapping[$user->id][$moduleName]['hasEdit'] = true;
                }
            }
        }

        return $mapping;
    }
}
