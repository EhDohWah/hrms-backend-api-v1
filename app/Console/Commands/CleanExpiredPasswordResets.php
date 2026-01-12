<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredPasswordResets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clean-resets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired password reset tokens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = DB::table('password_reset_tokens')
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->delete();

        $this->info("Deleted {$deleted} expired password reset tokens.");

        return Command::SUCCESS;
    }
}
