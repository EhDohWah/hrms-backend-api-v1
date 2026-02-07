<?php

namespace App\Console\Commands;

use App\Services\SafeDeleteService;
use Illuminate\Console\Command;

class PurgeExpiredDeletionsCommand extends Command
{
    protected $signature = 'recycle-bin:purge {--days=30 : Number of days to retain deleted records}';

    protected $description = 'Permanently delete records that have been in the recycle bin longer than the retention period';

    public function handle(SafeDeleteService $service): int
    {
        $days = (int) $this->option('days');

        $this->info("Purging deletion manifests older than {$days} days...");

        $count = $service->purgeExpired($days);

        $this->info("Purged {$count} expired deletion manifest(s).");

        return Command::SUCCESS;
    }
}
