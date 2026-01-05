<?php

namespace App\Console\Commands;

use App\Models\Employment;
use App\Models\ProbationRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProbationRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'probation:migrate-records {--dry-run : Run in dry-run mode without creating records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing employment probation data to probation_records table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Running in DRY-RUN mode - no records will be created');
            $this->newLine();
        }

        $this->info('🚀 Starting probation records migration...');
        $this->newLine();

        // Find all employments with probation dates
        $employments = Employment::whereNotNull('pass_probation_date')->get();

        $this->info("Found {$employments->count()} employment records with probation dates");
        $this->newLine();

        $created = 0;
        $skipped = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            foreach ($employments as $employment) {
                // Check if probation record already exists
                $exists = ProbationRecord::where('employment_id', $employment->id)
                    ->where('event_type', ProbationRecord::EVENT_INITIAL)
                    ->exists();

                if ($exists) {
                    $this->line("⏭️  Skipping employment #{$employment->id} - probation record already exists");
                    $skipped++;

                    continue;
                }

                // Determine event type based on current probation_status
                $eventType = match ($employment->probation_status) {
                    'passed' => ProbationRecord::EVENT_PASSED,
                    'failed' => ProbationRecord::EVENT_FAILED,
                    'extended' => ProbationRecord::EVENT_EXTENSION,
                    default => ProbationRecord::EVENT_INITIAL
                };

                // Determine extension number based on event type
                $extensionNumber = $eventType === ProbationRecord::EVENT_EXTENSION ? 1 : 0;

                if (! $dryRun) {
                    ProbationRecord::create([
                        'employment_id' => $employment->id,
                        'employee_id' => $employment->employee_id,
                        'event_type' => $eventType,
                        'event_date' => $employment->start_date,
                        'probation_start_date' => $employment->start_date,
                        'probation_end_date' => $employment->pass_probation_date,
                        'extension_number' => $extensionNumber,
                        'decision_reason' => "Migrated from existing employment record (status: {$employment->probation_status})",
                        'is_active' => true,
                        'created_by' => 'migration_script',
                        'updated_by' => 'migration_script',
                    ]);

                    $this->line("✅ Created probation record for employment #{$employment->id} (type: {$eventType})");
                } else {
                    $this->line("[DRY-RUN] Would create probation record for employment #{$employment->id} (type: {$eventType})");
                }

                $created++;
            }

            if (! $dryRun) {
                DB::commit();
                $this->newLine();
                $this->info('✨ Migration completed successfully!');
            } else {
                DB::rollBack();
                $this->newLine();
                $this->info('✨ DRY-RUN completed! Run without --dry-run to create records.');
            }

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Employments', $employments->count()],
                    ['Records Created', $created],
                    ['Records Skipped', $skipped],
                    ['Errors', $errors],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->newLine();
            $this->error('❌ Migration failed: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
