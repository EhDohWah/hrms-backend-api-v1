<?php

namespace Database\Seeders;

use App\Models\JobOffer;
use Illuminate\Database\Seeder;

class JobOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 100 job offers with a mix of different states

        // 40 pending job offers
        JobOffer::factory()
            ->count(40)
            ->pending()
            ->create();

        // 25 accepted job offers
        JobOffer::factory()
            ->count(25)
            ->accepted()
            ->create();

        // 15 declined job offers
        JobOffer::factory()
            ->count(15)
            ->declined()
            ->create();

        // 10 senior position job offers (mixed statuses)
        JobOffer::factory()
            ->count(10)
            ->seniorPosition()
            ->create();

        // 10 random job offers with random statuses
        JobOffer::factory()
            ->count(10)
            ->create();

        $this->command->info('Created 100 job offers successfully!');
    }
}
