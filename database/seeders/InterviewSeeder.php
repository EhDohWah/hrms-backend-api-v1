<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InterviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        // Example records to seed the interviews table.
        DB::table('interviews')->insert([
            [
                'job_position'      => 'Software Developer',
                'interview_date'    => Carbon::now()->addDays(1)->toDateString(),
                'start_time'        => '10:00:00',
                'end_time'          => '11:00:00',
                'interview_mode'    => 'in-person',
                'interview_status'  => 'scheduled',
                'score'             => null,
                'feedback'          => null,
                'resume'            => 'resumes/resume1.pdf',
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
                'created_by'        => 'Admin',
                'updated_by'        => 'Admin',
            ],
            [
                'job_position'      => 'Project Manager',
                'interview_date'    => Carbon::now()->addDays(2)->toDateString(),
                'start_time'        => '14:00:00',
                'end_time'          => '15:00:00',
                'interview_mode'    => 'virtual',
                'interview_status'  => 'scheduled',
                'score'             => null,
                'feedback'          => null,
                'resume'            => 'resumes/resume2.pdf',
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
                'created_by'        => 'Admin',
                'updated_by'        => 'Admin',
            ],
            [
                'job_position'      => 'UI/UX Designer',
                'interview_date'    => Carbon::now()->subDays(2)->toDateString(),
                'start_time'        => '09:00:00',
                'end_time'          => '10:00:00',
                'interview_mode'    => 'in-person',
                'interview_status'  => 'completed',
                'score'             => 85.50,
                'feedback'          => 'Strong portfolio and good communication skills.',
                'resume'            => 'resumes/resume3.pdf',
                'created_at'        => Carbon::now()->subDays(3),
                'updated_at'        => Carbon::now()->subDays(3),
                'created_by'        => 'Admin',
                'updated_by'        => 'Admin',
            ],
            [
                'job_position'      => 'Data Analyst',
                'interview_date'    => Carbon::now()->subDays(5)->toDateString(),
                'start_time'        => '13:00:00',
                'end_time'          => '14:30:00',
                'interview_mode'    => 'virtual',
                'interview_status'  => 'completed',
                'score'             => 92.00,
                'feedback'          => 'Excellent analytical skills and presentation.',
                'resume'            => 'resumes/resume4.pdf',
                'created_at'        => Carbon::now()->subDays(6),
                'updated_at'        => Carbon::now()->subDays(6),
                'created_by'        => 'Admin',
                'updated_by'        => 'Admin',
            ],
            [
                'job_position'      => 'HR Manager',
                'interview_date'    => Carbon::now()->addDays(3)->toDateString(),
                'start_time'        => '11:30:00',
                'end_time'          => '12:30:00',
                'interview_mode'    => 'in-person',
                'interview_status'  => 'cancelled',
                'score'             => null,
                'feedback'          => 'Candidate withdrew application.',
                'resume'            => 'resumes/resume5.pdf',
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
                'created_by'        => 'Admin',
                'updated_by'        => 'Admin',
            ],
        ]);
    }
}
