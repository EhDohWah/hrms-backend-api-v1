<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\ImportFailedNotification;
use App\Services\NotificationService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class NotifyOnImportFailed
{
    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        if (! str_contains($jobName, 'Maatwebsite\Excel\Jobs')) {
            return; // Only target Excel import failures
        }

        Log::error('Import job failed', [
            'job' => $jobName,
            'exception' => $event->exception->getMessage(),
        ]);

        $payload = $event->job->payload()['data']['command'] ?? null;
        if ($payload && preg_match('/"userId";i:(\d+);/', $payload, $m)) {
            $user = User::find($m[1]);
            if ($user) {
                $importId = preg_match('/"importId";s:\d+:"([^"]+)";/', $payload, $i) ? $i[1] : null;
                app(NotificationService::class)->notifyUser(
                    $user,
                    new ImportFailedNotification('Import failed', null, $importId, 'import')
                );
            }
        }
    }
}
