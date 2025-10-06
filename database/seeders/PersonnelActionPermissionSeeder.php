<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PersonnelActionPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create personnel action permissions
        $permissions = [
            'personnel_action.create',
            'personnel_action.read',
            'personnel_action.update',
            'personnel_action.delete',
            'personnel_action.import',
            'personnel_action.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Get the permissions
        $personnelPermissions = Permission::whereIn('name', $permissions)->get();

        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($personnelPermissions);
        }

        $hrManagerRole = Role::where('name', 'hr-manager')->first();
        if ($hrManagerRole) {
            $hrManagerRole->givePermissionTo($personnelPermissions);
        }

        $hrAssistantSeniorRole = Role::where('name', 'hr-assistant-senior')->first();
        if ($hrAssistantSeniorRole) {
            $hrAssistantSeniorRole->givePermissionTo($personnelPermissions);
        }

        // HR Assistant Junior gets all permissions since it's just data entry
        $hrAssistantJuniorRole = Role::where('name', 'hr-assistant-junior')->first();
        if ($hrAssistantJuniorRole) {
            $hrAssistantJuniorRole->givePermissionTo($personnelPermissions);
        }

        $this->command->info('Personnel action permissions created and assigned successfully!');
    }
}
