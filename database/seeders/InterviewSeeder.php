<?php

namespace Database\Seeders;

use App\Models\Interview;
use Illuminate\Database\Seeder;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        Interview::factory()->count(300)->create();
        
        $this->command->info('Created 300 interview records!');
    }
}
