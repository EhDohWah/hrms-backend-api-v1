<?php

namespace App\Console\Commands;

use App\Models\Employment;
use App\Services\ProbationTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessProbationCompletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employment:process-probation-transitions
                            {--dry-run : Run without making any changes}
                            {--employment= : Process specific employment ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process employment probation transitions and update funding allocation statuses';

    /**
     * Execute the console command.
     */
    public function handle(ProbationTransitionService $probationService): int
    {
        $isDryRun = $this->option('dry-run');
        $specificEmploymentId = $this->option('employment');

        $this->info('Starting probation transition processing...');
        if ($isDryRun) {
            $this->warn('*** DRY RUN MODE - No changes will be made ***');
        }

        try {
            $query = Employment::whereNotNull('pass_probation_date')
                ->whereDate('pass_probation_date', today())
                ->whereNull('end_date')
                ->where(function ($q) {
                    $q->whereNull('probation_status')
                        ->orWhere('probation_status', 'ongoing')
                        ->orWhere('probation_status', 'extended');
                })
                ->with([
                    'employee:id,staff_id,first_name_en,last_name_en',
                    'activeAllocations',
                ]);

            if ($specificEmploymentId) {
                $query->where('id', $specificEmploymentId);
            }

            $employments = $query->get();

            if ($employments->isEmpty()) {
                $this->info('No probation transitions to process today.');

                return Command::SUCCESS;
            }

            $this->info(sprintf('Found %d employment(s) ready for probation transition today.', $employments->count()));
            $this->newLine();

            $processed = 0;
            $failed = 0;
            $skipped = 0;
            $details = [];

            // Create table data
            $tableData = [];

            foreach ($employments as $employment) {
                try {
                    $employeeName = sprintf(
                        '%s (%s %s)',
                        $employment->employee->staff_id,
                        $employment->employee->first_name_en,
                        $employment->employee->last_name_en
                    );

                    if ($isDryRun) {
                        // In dry-run mode, just show what would happen
                        $activeCount = $employment->activeAllocations->count();

                        $tableData[] = [
                            '○',
                            $employment->id,
                            $employeeName,
                            $employment->probation_status ?? 'ongoing',
                            $activeCount,
                            'Would transition',
                        ];

                        $skipped++;
                    } else {
                        // Actually process the transition
                        $result = $probationService->transitionEmploymentAllocations($employment);

                        if ($result['success']) {
                            $tableData[] = [
                                '✓',
                                $employment->id,
                                $employeeName,
                                $employment->probation_status ?? 'ongoing',
                                $result['historical_count'],
                                'Success',
                            ];
                            $processed++;
                        } else {
                            $tableData[] = [
                                '✗',
                                $employment->id,
                                $employeeName,
                                $employment->probation_status ?? 'ongoing',
                                0,
                                $result['message'],
                            ];
                            $failed++;
                        }

                        $details[] = $result;
                    }

                } catch (\Exception $e) {
                    $employeeName = sprintf(
                        '%s (%s %s)',
                        $employment->employee->staff_id ?? 'Unknown',
                        $employment->employee->first_name_en ?? '',
                        $employment->employee->last_name_en ?? ''
                    );

                    $tableData[] = [
                        '✗',
                        $employment->id,
                        $employeeName,
                        $employment->probation_status ?? 'N/A',
                        0,
                        'Exception: '.$e->getMessage(),
                    ];

                    $failed++;

                    Log::error('Error processing probation transition', [
                        'employment_id' => $employment->id,
                        'employee_id' => $employment->employee_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Display results table
            $this->table(
                ['Status', 'Employment ID', 'Employee', 'Current Status', 'Allocations', 'Result'],
                $tableData
            );

            // Summary
            $this->newLine();
            $this->info('=== Processing Summary ===');
            $this->info(sprintf('Total found: %d', $employments->count()));

            if ($isDryRun) {
                $this->warn(sprintf('Would process: %d (Dry run - no changes made)', $skipped));
            } else {
                $this->info(sprintf('Successfully processed: %d', $processed));
                if ($failed > 0) {
                    $this->error(sprintf('Failed: %d', $failed));
                }
            }

            // Log summary
            if (! $isDryRun) {
                Log::info('Probation transition processing completed', [
                    'date' => today()->format('Y-m-d'),
                    'total_found' => $employments->count(),
                    'processed' => $processed,
                    'failed' => $failed,
                    'details' => $details,
                ]);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Fatal error during probation transition processing: '.$e->getMessage());
            Log::error('Fatal error in probation transition command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
