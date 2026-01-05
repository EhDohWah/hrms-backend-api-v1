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

        // 1. ADMIN USER - Auto-permissions
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
        $admin->syncPermissions(Permission::all());
        $this->command->info('✓ Admin user created (admin@hrms.com)');
        $this->command->info('  - Role: System Administrator');
        $this->command->info('  - Permissions: ALL ('.Permission::count().' permissions)');

        // 2. HR MANAGER - Auto-permissions
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
        $this->command->info('- Admin and HR Manager have ALL permissions automatically');
        $this->command->info('- Additional users should be created via User Management UI');
        $this->command->info('- Other roles can be created via Role Management UI');
        $this->command->info('- Permissions for other users assigned via UI by Admin/HR Manager');
        $this->command->info('========================================');
    }
}
