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
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('🔧 Creating core system users...');
        $this->command->info('========================================');

        // 1. ADMIN USER — Administration modules only
        //    Dashboard, Lookups, Organization Structure, User Management, File Uploads, Recycle Bin
        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'last_login_at' => now(),
                'created_by' => 'system',
                'updated_by' => 'system',
                'status' => 'active',
            ]
        );
        $admin->assignRole('admin');

        $adminModules = [
            'dashboard',           // Dashboard
            'lookup_list',         // Lookups
            'sites',               // Organization Structure
            'departments',         // Organization Structure
            'positions',           // Organization Structure
            'section_departments', // Organization Structure
            'users',               // User Management
            'roles',               // User Management
            'file_uploads',        // Administration
            'recycle_bin_list',    // Recycle Bin
        ];

        $adminPermissions = [];
        foreach ($adminModules as $module) {
            $adminPermissions[] = "{$module}.read";
            $adminPermissions[] = "{$module}.edit";
        }
        $admin->syncPermissions($adminPermissions);

        $this->command->info('✓ Admin user created (admin@hrms.com)');
        $this->command->info('  - Role: System Administrator');
        $this->command->info('  - Permissions: Administration only ('.count($adminPermissions).' permissions)');

        // 2. HR MANAGER — full access to all modules (manages employee/HR data)
        $hrManager = User::firstOrCreate(
            ['email' => 'hrmanager@hrms.com'],
            [
                'name' => 'HR Manager',
                'password' => Hash::make('password'),
                'last_login_at' => now(),
                'created_by' => 'system',
                'updated_by' => 'system',
                'status' => 'active',
            ]
        );
        $hrManager->assignRole('hr-manager');
        $hrManager->syncPermissions(Permission::all());
        $this->command->info('');
        $this->command->info('✓ HR Manager created (hrmanager@hrms.com)');
        $this->command->info('  - Role: HR Manager');
        $this->command->info('  - Permissions: ALL ('.Permission::count().' permissions)');

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('User Seeder Complete!');
        $this->command->info('');
        $this->command->info('Core users created: 2');
        $this->command->info('  - admin@hrms.com (password: password)');
        $this->command->info('  - hrmanager@hrms.com (password: password)');
        $this->command->info('');
        $this->command->info('NOTES:');
        $this->command->info('- Admin has Administration-only permissions (org structure, users, lookups, etc.)');
        $this->command->info('- HR Manager has ALL permissions (full access to HR/employee data)');
        $this->command->info('- Additional users should be created via User Management UI');
        $this->command->info('- Other roles can be created via Role Management UI');
        $this->command->info('- Permissions for other users assigned via UI by Admin/HR Manager');
        $this->command->info('========================================');
    }
}
