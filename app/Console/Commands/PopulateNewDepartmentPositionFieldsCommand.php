<?php

namespace App\Console\Commands;

use App\Models\Employment;
// DepartmentPosition model removed - using direct DB queries instead
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopulateNewDepartmentPositionFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:populate-department-position-fields 
                            {--dry-run : Show what would be updated without making changes}
                            {--model= : Migrate specific model only (employment, travel_request, org_funded_allocation, employment_history, resignation)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate new department_id and position_id columns from existing department_position_id references';

    private $departmentPositionMap;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Populating new department and position fields...');

        $dryRun = $this->option('dry-run');
        $specificModel = $this->option('model');

        try {
            // Build department position mapping
            $this->info('Building department position mapping...');
            $this->buildDepartmentPositionMap();

            if ($this->departmentPositionMap->isEmpty()) {
                $this->error('No department positions found to map. Please ensure the migration has run.');

                return 1;
            }

            $this->info("Found {$this->departmentPositionMap->count()} department position mappings.");

            if ($dryRun) {
                $this->info('DRY RUN - No changes will be made');
            }

            $modelsToMigrate = $specificModel ? [$specificModel] : [
                'employment',
                'travel_request',
                'org_funded_allocation',
                'employment_history',
                'resignation',
            ];

            $totalUpdated = 0;

            foreach ($modelsToMigrate as $model) {
                $this->info("\nProcessing {$model}...");
                $updated = $this->migrateModel($model, $dryRun);
                $totalUpdated += $updated;
                $this->info("Updated {$updated} {$model} records.");
            }

            $this->info("\nMigration completed successfully!");
            $this->info("Total records updated: {$totalUpdated}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Migration failed: '.$e->getMessage());
            Log::error('Department position fields population failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Build mapping from department_position_id to new department_id and position_id
     */
    private function buildDepartmentPositionMap(): void
    {
        // Get all department positions from the old table
        $departmentPositions = DB::table('department_positions')->get();

        // Get all departments and positions from new tables
        $departments = DB::table('departments')->get()->keyBy('name');
        $positions = DB::table('positions')
            ->join('departments', 'positions.department_id', '=', 'departments.id')
            ->select('positions.*', 'departments.name as department_name')
            ->get();

        $this->departmentPositionMap = collect();

        foreach ($departmentPositions as $dp) {
            // Find matching department
            $department = $departments->get($dp->department);
            if (! $department) {
                $this->warn("Department not found: {$dp->department}");

                continue;
            }

            // Find matching position
            $position = $positions->firstWhere(function ($p) use ($dp) {
                return $p->department_name === $dp->department &&
                       $this->titlesMatch($p->title, $dp->position);
            });

            if (! $position) {
                $this->warn("Position not found: {$dp->position} in {$dp->department}");

                continue;
            }

            $this->departmentPositionMap->put($dp->id, [
                'department_id' => $department->id,
                'position_id' => $position->id,
                'department_name' => $department->name,
                'position_title' => $position->title,
            ]);
        }
    }

    /**
     * Check if two position titles match (allowing for slight variations)
     */
    private function titlesMatch($title1, $title2): bool
    {
        $normalized1 = strtolower(trim($title1));
        $normalized2 = strtolower(trim($title2));

        // Exact match
        if ($normalized1 === $normalized2) {
            return true;
        }

        // Remove common variations
        $variations = [
            '/', ' / ', '-', ' - ', '&', ' & ', '.', ', ', ' and ',
        ];

        foreach ($variations as $variation) {
            $normalized1 = str_replace($variation, ' ', $normalized1);
            $normalized2 = str_replace($variation, ' ', $normalized2);
        }

        // Clean up extra spaces
        $normalized1 = preg_replace('/\s+/', ' ', trim($normalized1));
        $normalized2 = preg_replace('/\s+/', ' ', trim($normalized2));

        return $normalized1 === $normalized2;
    }

    /**
     * Migrate a specific model
     */
    private function migrateModel(string $model, bool $dryRun): int
    {
        switch ($model) {
            case 'employment':
                return $this->migrateEmployments($dryRun);
            case 'travel_request':
                return $this->migrateTravelRequests($dryRun);
            case 'org_funded_allocation':
                return $this->migrateOrgFundedAllocations($dryRun);
            case 'employment_history':
                return $this->migrateEmploymentHistories($dryRun);
            case 'resignation':
                return $this->migrateResignations($dryRun);
            default:
                $this->error("Unknown model: {$model}");

                return 0;
        }
    }

    /**
     * Migrate employments table
     */
    private function migrateEmployments(bool $dryRun): int
    {
        $query = DB::table('employments')
            ->whereNotNull('department_position_id')
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereNull('position_id');
            });

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would update {$count} employment records");

            return $count;
        }

        $updated = 0;
        $query->chunk(100, function ($employments) use (&$updated) {
            foreach ($employments as $employment) {
                $mapping = $this->departmentPositionMap->get($employment->department_position_id);
                if ($mapping) {
                    DB::table('employments')
                        ->where('id', $employment->id)
                        ->update([
                            'department_id' => $mapping['department_id'],
                            'position_id' => $mapping['position_id'],
                            'updated_at' => now(),
                        ]);
                    $updated++;
                    $this->line("  ✓ Updated employment {$employment->id}: {$mapping['department_name']} - {$mapping['position_title']}");
                }
            }
        });

        return $updated;
    }

    /**
     * Migrate travel requests table
     */
    private function migrateTravelRequests(bool $dryRun): int
    {
        $query = DB::table('travel_requests')
            ->whereNotNull('department_position_id')
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereNull('position_id');
            });

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would update {$count} travel request records");

            return $count;
        }

        $updated = 0;
        $query->chunk(100, function ($travelRequests) use (&$updated) {
            foreach ($travelRequests as $travelRequest) {
                $mapping = $this->departmentPositionMap->get($travelRequest->department_position_id);
                if ($mapping) {
                    DB::table('travel_requests')
                        ->where('id', $travelRequest->id)
                        ->update([
                            'department_id' => $mapping['department_id'],
                            'position_id' => $mapping['position_id'],
                            'updated_at' => now(),
                        ]);
                    $updated++;
                    $this->line("  ✓ Updated travel request {$travelRequest->id}");
                }
            }
        });

        return $updated;
    }

    /**
     * Migrate org funded allocations table
     */
    private function migrateOrgFundedAllocations(bool $dryRun): int
    {
        $query = DB::table('org_funded_allocations')
            ->whereNotNull('department_position_id')
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereNull('position_id');
            });

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would update {$count} org funded allocation records");

            return $count;
        }

        $updated = 0;
        $query->chunk(100, function ($allocations) use (&$updated) {
            foreach ($allocations as $allocation) {
                $mapping = $this->departmentPositionMap->get($allocation->department_position_id);
                if ($mapping) {
                    DB::table('org_funded_allocations')
                        ->where('id', $allocation->id)
                        ->update([
                            'department_id' => $mapping['department_id'],
                            'position_id' => $mapping['position_id'],
                            'updated_at' => now(),
                        ]);
                    $updated++;
                    $this->line("  ✓ Updated org funded allocation {$allocation->id}");
                }
            }
        });

        return $updated;
    }

    /**
     * Migrate employment histories table
     */
    private function migrateEmploymentHistories(bool $dryRun): int
    {
        $query = DB::table('employment_histories')
            ->whereNotNull('department_position_id')
            ->where(function ($q) {
                $q->whereNull('department_id')->orWhereNull('position_id');
            });

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would update {$count} employment history records");

            return $count;
        }

        $updated = 0;
        $query->chunk(100, function ($histories) use (&$updated) {
            foreach ($histories as $history) {
                $mapping = $this->departmentPositionMap->get($history->department_position_id);
                if ($mapping) {
                    DB::table('employment_histories')
                        ->where('id', $history->id)
                        ->update([
                            'department_id' => $mapping['department_id'],
                            'position_id' => $mapping['position_id'],
                            'updated_at' => now(),
                        ]);
                    $updated++;
                    $this->line("  ✓ Updated employment history {$history->id}");
                }
            }
        });

        return $updated;
    }

    /**
     * Migrate resignations table
     */
    private function migrateResignations(bool $dryRun): int
    {
        // The resignations table structure is different - it uses department_id and position_id
        // but they currently reference department_positions table
        // We need to map these to the correct department and position IDs

        $query = DB::table('resignations')
            ->whereNotNull('department_id')
            ->whereNotNull('position_id');

        if ($dryRun) {
            $count = $query->count();
            $this->line("  Would update {$count} resignation records");

            return $count;
        }

        $updated = 0;
        $query->chunk(100, function ($resignations) use (&$updated) {
            foreach ($resignations as $resignation) {
                // Map both department_id and position_id from old department_positions references
                $departmentMapping = $this->departmentPositionMap->get($resignation->department_id);
                $positionMapping = $this->departmentPositionMap->get($resignation->position_id);

                if ($departmentMapping && $positionMapping) {
                    DB::table('resignations')
                        ->where('id', $resignation->id)
                        ->update([
                            'department_id' => $departmentMapping['department_id'],
                            'position_id' => $positionMapping['position_id'],
                            'updated_at' => now(),
                        ]);
                    $updated++;
                    $this->line("  ✓ Updated resignation {$resignation->id}");
                }
            }
        });

        return $updated;
    }
}
