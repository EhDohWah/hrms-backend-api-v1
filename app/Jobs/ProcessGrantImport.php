<?php

namespace App\Jobs;

use App\Imports\GrantSheetImport;
use App\Imports\GrantsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessGrantImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    protected string $filePath;

    protected string $importId;

    protected int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $importId, int $userId)
    {
        $this->filePath = $filePath;
        $this->importId = $importId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting grant import job', [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'file_path' => $this->filePath,
        ]);

        try {
            // Create the import handler
            $grantsImport = new GrantsImport($this->importId, $this->userId);

            // Load spreadsheet and process each sheet
            $spreadsheet = IOFactory::load($this->filePath);
            $sheets = $spreadsheet->getAllSheets();

            $sheetImport = new GrantSheetImport($grantsImport);

            foreach ($sheets as $sheet) {
                $sheetImport->processSheet($sheet);
            }

            // Log results
            Log::info('Grant import completed', [
                'import_id' => $this->importId,
                'processed_grants' => $grantsImport->getProcessedGrants(),
                'processed_items' => $grantsImport->getProcessedItems(),
                'errors' => count($grantsImport->getErrors()),
                'skipped' => count($grantsImport->getSkippedGrants()),
            ]);

            // Send completion notification
            $grantsImport->sendCompletionNotification();

        } catch (\Exception $e) {
            Log::error('Grant import job failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to mark job as failed
        } finally {
            // Clean up temporary file
            $this->cleanupTempFile();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Grant import job failed permanently', [
            'import_id' => $this->importId,
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
        ]);

        // Clean up temp file on failure
        $this->cleanupTempFile();

        // Notify user of failure
        $user = \App\Models\User::find($this->userId);
        if ($user) {
            app(\App\Services\NotificationService::class)->notifyUser(
                $user,
                new \App\Notifications\ImportedCompletedNotification(
                    'Grant import failed: '.($exception?->getMessage() ?? 'Unknown error'),
                    'import'
                )
            );
        }
    }

    /**
     * Clean up temporary file after processing
     */
    protected function cleanupTempFile(): void
    {
        if (file_exists($this->filePath)) {
            try {
                unlink($this->filePath);
                Log::info('Cleaned up temporary import file', ['file' => $this->filePath]);
            } catch (\Exception $e) {
                Log::warning('Failed to clean up temporary file', [
                    'file' => $this->filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
