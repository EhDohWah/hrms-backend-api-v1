<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateDepartmentPositionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:department-positions 
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Force migration even if target tables have data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from department_positions table to separate departments and positions tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting department_positions data migration...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        try {
            // Check if source table exists
            if (! DB::getSchemaBuilder()->hasTable('department_positions')) {
                $this->error('Source table "department_positions" does not exist.');

                return 1;
            }

            // Check if target tables exist
            if (! DB::getSchemaBuilder()->hasTable('departments') || ! DB::getSchemaBuilder()->hasTable('positions')) {
                $this->error('Target tables "departments" and "positions" must exist. Please run migrations first.');

                return 1;
            }

            // Check if target tables have data (unless forced)
            if (! $force) {
                $departmentCount = Department::count();
                $positionCount = Position::count();

                if ($departmentCount > 0 || $positionCount > 0) {
                    $this->error("Target tables already contain data (Departments: {$departmentCount}, Positions: {$positionCount}).");
                    $this->error('Use --force to proceed anyway, but this will duplicate data.');

                    return 1;
                }
            }

            // Get all department_positions data
            $departmentPositions = DB::table('department_positions')->get();

            if ($departmentPositions->isEmpty()) {
                $this->warn('No data found in department_positions table.');

                return 0;
            }

            $this->info("Found {$departmentPositions->count()} records in department_positions table.");

            if ($dryRun) {
                $this->info('DRY RUN - No changes will be made');

                return $this->showMigrationPreview($departmentPositions);
            }

            // Start migration
            return DB::transaction(function () use ($departmentPositions) {
                return $this->performMigration($departmentPositions);
            });

        } catch (\Exception $e) {
            $this->error('Migration failed: '.$e->getMessage());
            Log::error('Department positions migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Show what would be migrated without making changes
     */
    private function showMigrationPreview($departmentPositions): int
    {
        // Extract unique departments
        $departments = $departmentPositions->groupBy('department')
            ->map(function ($group, $name) {
                return [
                    'name' => $name,
                    'description' => $this->generateDepartmentDescription($name),
                    'positions_count' => $group->count(),
                ];
            });

        $this->info("\nDepartments to be created:");
        $this->table(['Name', 'Description', 'Positions Count'], $departments->values()->toArray());

        $this->info("\nPositions to be created:");
        $positionData = $departmentPositions->map(function ($dp) {
            return [
                'Title' => $dp->position,
                'Department' => $dp->department,
                'Reports To' => $dp->report_to ?: 'None',
                'Level' => $this->calculateLevel($dp->report_to),
                'Is Manager' => $this->isManagerPosition($dp->position) ? 'Yes' : 'No',
            ];
        });

        $this->table(['Title', 'Department', 'Reports To', 'Level', 'Is Manager'], $positionData->toArray());

        $this->info("\nMigration summary:");
        $this->info("- Departments: {$departments->count()}");
        $this->info("- Positions: {$departmentPositions->count()}");

        return 0;
    }

    /**
     * Perform the actual migration
     */
    private function performMigration($departmentPositions): int
    {
        $this->info('Starting data migration...');

        // Step 1: Create departments
        $this->info('Creating departments...');
        $departmentMap = $this->createDepartments($departmentPositions);
        $this->info("Created {$departmentMap->count()} departments.");

        // Step 2: Create positions (without relationships first)
        $this->info('Creating positions...');
        $positionMap = $this->createPositions($departmentPositions, $departmentMap);
        $this->info("Created {$positionMap->count()} positions.");

        // Step 3: Set up reporting relationships
        $this->info('Setting up reporting relationships...');
        $relationshipsCount = $this->setupReportingRelationships($departmentPositions, $positionMap);
        $this->info("Set up {$relationshipsCount} reporting relationships.");

        // Step 4: Validation
        $this->info('Validating migration...');
        if ($this->validateMigration($departmentPositions, $departmentMap, $positionMap)) {
            $this->info('Migration completed successfully!');

            $this->info("\nMigration summary:");
            $this->info("- Departments: {$departmentMap->count()}");
            $this->info("- Positions: {$positionMap->count()}");
            $this->info("- Reporting relationships: {$relationshipsCount}");

            return 0;
        } else {
            $this->error('Migration validation failed!');

            return 1;
        }
    }

    /**
     * Create departments from department_positions data
     */
    private function createDepartments($departmentPositions)
    {
        $uniqueDepartments = $departmentPositions->groupBy('department');
        $departmentMap = collect();

        foreach ($uniqueDepartments as $departmentName => $positions) {
            $department = Department::create([
                'name' => $departmentName,
                'description' => $this->generateDepartmentDescription($departmentName),
                'is_active' => true,
                'created_by' => 'migration',
            ]);

            $departmentMap->put($departmentName, $department);
            $this->line("  ✓ Created department: {$departmentName}");
        }

        return $departmentMap;
    }

    /**
     * Create positions from department_positions data
     */
    private function createPositions($departmentPositions, $departmentMap)
    {
        $positionMap = collect();

        foreach ($departmentPositions as $dp) {
            $department = $departmentMap->get($dp->department);

            if (! $department) {
                $this->warn("Department not found for position: {$dp->position}");

                continue;
            }

            $level = $this->calculateLevel($dp->report_to);
            $isManager = $this->isManagerPosition($dp->position);

            $position = Position::create([
                'title' => $dp->position,
                'department_id' => $department->id,
                'reports_to_position_id' => null, // Will be set later
                'level' => $level,
                'is_manager' => $isManager,
                'is_active' => true,
                'created_by' => 'migration',
            ]);

            // Store mapping with original data for relationship setup
            $positionMap->put($dp->id, [
                'position' => $position,
                'original_report_to' => $dp->report_to,
                'department' => $dp->department,
            ]);

            $this->line("  ✓ Created position: {$dp->position} in {$dp->department}");
        }

        return $positionMap;
    }

    /**
     * Set up reporting relationships between positions
     */
    private function setupReportingRelationships($departmentPositions, $positionMap)
    {
        $relationshipsCount = 0;

        foreach ($positionMap as $originalId => $positionData) {
            $position = $positionData['position'];
            $reportTo = $positionData['original_report_to'];

            if (! $reportTo) {
                continue; // No supervisor
            }

            // Find supervisor position
            $supervisor = $this->findSupervisorPosition($reportTo, $positionData['department'], $positionMap);

            if ($supervisor) {
                $position->update([
                    'reports_to_position_id' => $supervisor->id,
                    'level' => $supervisor->level + 1,
                ]);
                $relationshipsCount++;
                $this->line("  ✓ {$position->title} reports to {$supervisor->title}");
            } else {
                $this->warn("  ! Could not find supervisor '{$reportTo}' for position: {$position->title}");
            }
        }

        return $relationshipsCount;
    }

    /**
     * Find supervisor position by report_to value
     */
    private function findSupervisorPosition($reportTo, $department, $positionMap)
    {
        // Try to find by position title in same department
        foreach ($positionMap as $positionData) {
            $position = $positionData['position'];

            if ($positionData['department'] === $department &&
                (str_contains(strtolower($position->title), strtolower($reportTo)) ||
                 str_contains(strtolower($reportTo), strtolower($position->title)))) {
                return $position;
            }
        }

        // Try to find by ID if report_to is numeric
        if (is_numeric($reportTo)) {
            $supervisorData = $positionMap->get($reportTo);
            if ($supervisorData) {
                return $supervisorData['position'];
            }
        }

        // Try to find department head if report_to suggests manager role
        if (str_contains(strtolower($reportTo), 'manager') || str_contains(strtolower($reportTo), 'head')) {
            foreach ($positionMap as $positionData) {
                $position = $positionData['position'];

                if ($positionData['department'] === $department &&
                    $position->is_manager &&
                    $position->level === 1) {
                    return $position;
                }
            }
        }

        return null;
    }

    /**
     * Calculate position level based on report_to
     */
    private function calculateLevel($reportTo): int
    {
        if (! $reportTo) {
            return 1; // Top level
        }

        // If report_to is numeric, assume it's referring to another position
        if (is_numeric($reportTo)) {
            return 2; // Default for positions that report to someone
        }

        // If report_to contains manager/head keywords, this is likely level 2
        if (str_contains(strtolower($reportTo), 'manager') ||
            str_contains(strtolower($reportTo), 'head') ||
            str_contains(strtolower($reportTo), 'director')) {
            return 2;
        }

        return 2; // Default for positions with supervisors
    }

    /**
     * Determine if a position is a manager based on title
     */
    private function isManagerPosition($title): bool
    {
        $managerKeywords = [
            'manager', 'head', 'director', 'supervisor', 'coordinator',
            'lead', 'chief', 'administrator', 'specialist',
        ];

        $titleLower = strtolower($title);

        foreach ($managerKeywords as $keyword) {
            if (str_contains($titleLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate description for department
     */
    private function generateDepartmentDescription($departmentName): string
    {
        $descriptions = [
            'Administration' => 'Administrative operations and support',
            'Finance' => 'Financial management and accounting',
            'Grant' => 'Grant management and funding',
            'Human Resources' => 'HR operations and employee management',
            'Logistics' => 'Logistics and transportation operations',
            'Procurement & Store' => 'Procurement and inventory management',
            'Data management' => 'Data operations and management',
            'IT' => 'Information Technology services',
            'Clinical' => 'Clinical services and healthcare',
            'Research/Study' => 'Research and study operations',
            'Training' => 'Training and capacity building',
            'Research/Study M&E' => 'Research monitoring and evaluation',
            'MCH' => 'Maternal and Child Health programs',
            'M&E' => 'Monitoring and Evaluation',
            'Laboratory' => 'Laboratory services and testing',
            'Malaria' => 'Malaria prevention and control programs',
            'Public Engagement' => 'Public engagement and community outreach',
            'TB' => 'Tuberculosis prevention and treatment programs',
            'Media Group' => 'Media and communications',
        ];

        return $descriptions[$departmentName] ?? $departmentName.' operations';
    }

    /**
     * Validate the migration results
     */
    private function validateMigration($originalData, $departmentMap, $positionMap): bool
    {
        $originalDepartmentCount = $originalData->groupBy('department')->count();
        $originalPositionCount = $originalData->count();

        $newDepartmentCount = $departmentMap->count();
        $newPositionCount = $positionMap->count();

        $this->line("Original departments: {$originalDepartmentCount}, New: {$newDepartmentCount}");
        $this->line("Original positions: {$originalPositionCount}, New: {$newPositionCount}");

        if ($originalDepartmentCount !== $newDepartmentCount) {
            $this->error('Department count mismatch!');

            return false;
        }

        if ($originalPositionCount !== $newPositionCount) {
            $this->error('Position count mismatch!');

            return false;
        }

        return true;
    }
}
