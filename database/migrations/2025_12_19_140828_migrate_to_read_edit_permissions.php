<?php

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts granular CRUD permissions (create, read, update, delete, import, export)
     * to simplified read/edit permissions across all modules.
     */
    public function up(): void
    {
        echo "🔄 Starting permission migration to read/edit model...\n";

        // Step 1: Build conversion mapping for existing user permissions
        echo "📊 Building user permission mapping...\n";
        $userPermissionMapping = $this->buildUserPermissionMapping();

        // Step 2: Get all module names from Module table or use hardcoded list
        echo "📋 Fetching modules...\n";
        $modules = Module::all()->pluck('name')->toArray();

        // If Module table is empty, use hardcoded list (important for fresh migrations/tests)
        if (empty($modules)) {
            echo "   Module table empty, using hardcoded module list\n";
            $modules = $this->getHardcodedModuleNames();
        }

        echo '   Found '.count($modules)." modules\n";

        // Step 3: Delete all granular permissions (except read)
        echo "🗑️  Deleting granular permissions (create, update, delete, import, export)...\n";
        $deletedCount = Permission::whereIn('name', function ($query) {
            $query->selectRaw('name')
                ->from('permissions')
                ->where('name', 'like', '%.create')
                ->orWhere('name', 'like', '%.update')
                ->orWhere('name', 'like', '%.delete')
                ->orWhere('name', 'like', '%.import')
                ->orWhere('name', 'like', '%.export');
        })->delete();
        echo "   Deleted {$deletedCount} granular permissions\n";

        // Step 4: Create new read and edit permissions for all modules
        echo "✨ Creating read/edit permissions for all modules...\n";
        foreach ($modules as $moduleName) {
            // Ensure read permission exists
            Permission::firstOrCreate([
                'name' => "{$moduleName}.read",
                'guard_name' => 'web',
            ]);

            // Create edit permission
            Permission::firstOrCreate([
                'name' => "{$moduleName}.edit",
                'guard_name' => 'web',
            ]);
        }
        echo '   Created permissions for '.count($modules)." modules\n";

        // Step 5: Re-assign permissions to users based on mapping
        echo "👥 Re-assigning user permissions...\n";
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

            // Sync permissions for this user
            $user->syncPermissions($newPermissions);
            $userCount++;
        }
        echo "   Updated permissions for {$userCount} users\n";

        // Step 6: Clear permission cache
        echo "🧹 Clearing permission cache...\n";
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        echo "✅ Permission migration completed successfully!\n";
        echo '   Total modules: '.count($modules)."\n";
        echo "   Total users updated: {$userCount}\n";
    }

    /**
     * Get hardcoded permission module names (fallback when Module table is empty).
     * These are the permission prefixes used in ModuleSeeder and old migrations.
     * This ensures permissions are created even during fresh migrations/tests.
     * These MUST match the module names in ModuleSeeder exactly!
     * NOTE: Only actionable submenus are listed here - NOT parent menu names!
     */
    protected function getHardcodedModuleNames(): array
    {
        return [
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

    /**
     * Build a mapping of current user permissions.
     *
     * Returns: [
     *   user_id => [
     *     module_name => [
     *       'hasRead' => bool,
     *       'hasEdit' => bool
     *     ]
     *   ]
     * ]
     */
    protected function buildUserPermissionMapping(): array
    {
        $mapping = [];

        // Get all users with their permissions
        $users = User::with('permissions')->get();

        foreach ($users as $user) {
            $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

            if (empty($userPermissions)) {
                continue;
            }

            $mapping[$user->id] = [];

            // Group permissions by module
            foreach ($userPermissions as $permissionName) {
                // Extract module name (e.g., "user.create" => "user")
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

                // Check if this is a read permission
                if ($action === 'read') {
                    $mapping[$user->id][$moduleName]['hasRead'] = true;
                }

                // Check if this is an edit permission (create, update, delete, import, export)
                if (in_array($action, ['create', 'update', 'delete', 'import', 'export'])) {
                    $mapping[$user->id][$moduleName]['hasEdit'] = true;
                }
            }
        }

        return $mapping;
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: This rollback is not recommended as it will cause data loss.
     * The original granular permissions cannot be fully restored.
     */
    public function down(): void
    {
        echo "⚠️  WARNING: Rolling back permission migration.\n";
        echo "   This will delete all read/edit permissions.\n";
        echo "   Original granular permissions cannot be fully restored.\n";
        echo "   You should restore from database backup instead.\n";

        // Get all modules
        $modules = Module::all()->pluck('name')->toArray();

        // If Module table is empty, use hardcoded list
        if (empty($modules)) {
            $modules = $this->getHardcodedModuleNames();
        }

        // Delete read/edit permissions
        foreach ($modules as $moduleName) {
            Permission::where('name', "{$moduleName}.read")->delete();
            Permission::where('name', "{$moduleName}.edit")->delete();
        }

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        echo "⚠️  Rollback completed. Please restore granular permissions from backup.\n";
    }
};
