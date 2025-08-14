<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionRoleSeeder::class,
            UserSeeder::class,
            BudgetLineSeeder::class,
            EmployeeSeeder::class,
            InterviewSeeder::class,
            GrantSeeder::class,
            JobOfferSeeder::class,
            TaxBracketSeeder::class,
            TaxSettingSeeder::class,
        ]);
    }
}
