<?php

namespace App\Services;

use App\Exceptions\SafeDeleteBlockedException;
use App\Models\ActivityLog;
use App\Models\DeletedModel;
use App\Models\DeletionManifest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBeneficiary;
use App\Models\EmployeeChild;
use App\Models\EmployeeEducation;
use App\Models\EmployeeFundingAllocation;
use App\Models\EmployeeFundingAllocationHistory;
use App\Models\EmployeeLanguage;
use App\Models\EmployeeTraining;
use App\Models\Employment;
use App\Models\EmploymentHistory;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Models\HolidayCompensationRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestItem;
use App\Models\Payroll;
use App\Models\PersonnelAction;
use App\Models\Position;
use App\Models\ProbationRecord;
use App\Models\Resignation;
use App\Models\SectionDepartment;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SafeDeleteService
{
    use \App\Traits\ConvertsDatesForSqlServer;

    // ─── PUBLIC METHODS ──────────────────────────────────────────────────

    /**
     * Validate whether a model can be safely deleted.
     * Returns array of blocker messages. Empty array = safe to delete.
     */
    public function validateDeletion(Model $model): array
    {
        $config = $this->getCascadeConfigFor($model);
        if (! $config) {
            return [];
        }

        $blockers = [];
        foreach ($config['blockers'] as $blockerCheck) {
            $message = $blockerCheck($model);
            if ($message !== null) {
                $blockers[] = $message;
            }
        }

        return $blockers;
    }

    /**
     * Safe delete: validate, snapshot all children + parent, hard delete, create manifest.
     *
     * @throws SafeDeleteBlockedException if blockers exist
     */
    public function delete(Model $model, ?string $reason = null): DeletionManifest
    {
        $blockers = $this->validateDeletion($model);
        if (! empty($blockers)) {
            throw new SafeDeleteBlockedException($blockers);
        }

        return DB::transaction(function () use ($model, $reason) {
            $deletionKey = Str::random(40);
            $snapshotKeys = [];
            $tableOrder = [];

            $config = $this->getCascadeConfigFor($model);

            // 1. Snapshot all children (iterate snapshot_order)
            if ($config) {
                foreach ($config['snapshot_order'] as $entry) {
                    $query = ($entry['query'])($model);
                    $records = $query->get();

                    foreach ($records as $record) {
                        $key = Str::random(40);
                        DeletedModel::create([
                            'key' => $key,
                            'model' => $entry['model'],
                            'values' => $record->toArray(),
                        ]);
                        $snapshotKeys[] = $key;
                    }

                    if ($records->isNotEmpty() && ! in_array($entry['table'], $tableOrder)) {
                        $tableOrder[] = $entry['table'];
                    }
                }
            }

            // 2. Snapshot the root model
            $rootKey = Str::random(40);
            DeletedModel::create([
                'key' => $rootKey,
                'model' => get_class($model),
                'values' => $model->toArray(),
            ]);
            $snapshotKeys[] = $rootKey;
            $tableOrder[] = $model->getTable();

            // 3. Delete children in snapshot_order (deepest first)
            if ($config) {
                foreach ($config['snapshot_order'] as $entry) {
                    $query = ($entry['query'])($model);
                    // SoftDeletes models need forceDelete
                    if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($entry['model']))) {
                        $query->forceDelete();
                    } else {
                        $query->delete();
                    }
                }
            }

            // 4. Delete the root model
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(get_class($model)))) {
                $model->forceDelete();
            } else {
                $model->delete();
            }

            // 5. Create manifest
            $manifest = DeletionManifest::create([
                'deletion_key' => $deletionKey,
                'root_model' => get_class($model),
                'root_id' => $model->getKey(),
                'root_display_name' => $this->getDisplayName($model),
                'snapshot_keys' => $snapshotKeys,
                'table_order' => $tableOrder,
                'deleted_by' => auth()->id(),
                'deleted_by_name' => auth()->user()?->name,
                'reason' => $reason,
            ]);

            // 6. Log to activity_logs
            ActivityLog::log(
                'safe_deleted',
                $model,
                'Moved to recycle bin'.($reason ? ": {$reason}" : ''),
                [
                    'deletion_key' => $deletionKey,
                    'child_records' => count($snapshotKeys) - 1,
                    'tables' => $tableOrder,
                ]
            );

            return $manifest;
        });
    }

    /**
     * Bulk safe delete. Validates ALL models first, then deletes each in its own transaction.
     * Returns ['succeeded' => [...], 'failed' => [...]]
     */
    public function bulkDelete(string $modelClass, array $ids, ?string $reason = null): array
    {
        $succeeded = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $model = $modelClass::findOrFail($id);

                $manifest = $this->delete($model, $reason);
                $succeeded[] = [
                    'id' => $id,
                    'deletion_key' => $manifest->deletion_key,
                    'display_name' => $manifest->root_display_name,
                    'snapshot_count' => $manifest->snapshot_count,
                ];
            } catch (SafeDeleteBlockedException $e) {
                $failed[] = [
                    'id' => $id,
                    'blockers' => $e->blockers,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $id,
                    'blockers' => [$e->getMessage()],
                ];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Restore from manifest: re-insert parent + all children with IDENTITY_INSERT.
     */
    public function restore(string $deletionKey): Model
    {
        $manifest = DeletionManifest::where('deletion_key', $deletionKey)->firstOrFail();

        return DB::transaction(function () use ($manifest) {
            $snapshotKeys = $manifest->snapshot_keys;
            $rootModelClass = $manifest->root_model;

            // 1. Load all deleted_models rows for this manifest
            $deletedRecords = DeletedModel::whereIn('key', $snapshotKeys)->get()->keyBy('key');

            // 2. Find the root record
            $rootRecord = null;
            $childRecords = [];

            foreach ($deletedRecords as $key => $record) {
                if ($record->model === $rootModelClass && ($record->values['id'] ?? null) == $manifest->root_id) {
                    $rootRecord = $record;
                } else {
                    $childRecords[] = $record;
                }
            }

            if (! $rootRecord) {
                throw new \Exception("Root record not found in deleted_models for key {$manifest->deletion_key}");
            }

            // 3. Restore root FIRST (parent must exist before children due to FK)
            $restoredRoot = $this->restoreRecord($rootRecord);

            // 4. Restore children in reverse of deletion order (parent-adjacent first, then leaves)
            //    table_order was stored as deepest-first, so we reverse it
            $tableOrder = array_reverse($manifest->table_order);

            // Group child records by their table name
            $childrenByTable = collect($childRecords)->groupBy(function ($record) {
                return (new ($record->model))->getTable();
            });

            foreach ($tableOrder as $tableName) {
                // Skip root table (already restored)
                if ($tableName === $restoredRoot->getTable()) {
                    continue;
                }

                if (! isset($childrenByTable[$tableName])) {
                    continue;
                }

                foreach ($childrenByTable[$tableName] as $record) {
                    $this->restoreRecord($record);
                }
            }

            // 5. Clean up: delete all deleted_models rows and the manifest
            DeletedModel::whereIn('key', $snapshotKeys)->delete();
            $manifest->delete();

            // 6. Log restoration
            ActivityLog::log(
                'restored',
                $restoredRoot,
                'Restored from recycle bin',
                [
                    'deletion_key' => $manifest->deletion_key,
                    'restored_children' => count($childRecords),
                ]
            );

            return $restoredRoot;
        });
    }

    /**
     * Bulk restore by manifest deletion keys.
     */
    public function bulkRestore(array $deletionKeys): array
    {
        $succeeded = [];
        $failed = [];

        foreach ($deletionKeys as $key) {
            try {
                $restoredModel = $this->restore($key);
                $succeeded[] = [
                    'deletion_key' => $key,
                    'model_type' => class_basename(get_class($restoredModel)),
                    'restored_id' => $restoredModel->getKey(),
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'deletion_key' => $key,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Permanently delete: remove manifest + all associated deleted_models rows.
     */
    public function permanentlyDelete(string $deletionKey): bool
    {
        $manifest = DeletionManifest::where('deletion_key', $deletionKey)->firstOrFail();

        Log::info('Permanently deleting manifest', [
            'deletion_key' => $deletionKey,
            'root_model' => $manifest->root_model,
            'root_id' => $manifest->root_id,
        ]);

        // Delete all associated snapshot records
        DeletedModel::whereIn('key', $manifest->snapshot_keys)->delete();

        return $manifest->delete();
    }

    /**
     * Purge all manifests older than given days.
     */
    public function purgeExpired(int $days = 30): int
    {
        $expired = DeletionManifest::expired($days)->get();
        $count = 0;

        foreach ($expired as $manifest) {
            DeletedModel::whereIn('key', $manifest->snapshot_keys)->delete();
            $manifest->delete();
            $count++;
        }

        Log::info("Purged {$count} expired deletion manifests (older than {$days} days)");

        return $count;
    }

    // ─── CASCADE CONFIGURATIONS ──────────────────────────────────────────

    /**
     * Get the cascade config for a given model, or null if no cascading needed.
     */
    private function getCascadeConfigFor(Model $model): ?array
    {
        return match (true) {
            $model instanceof Employee => $this->getEmployeeCascadeConfig(),
            $model instanceof Grant => $this->getGrantCascadeConfig(),
            $model instanceof Department => $this->getDepartmentCascadeConfig(),
            default => null,
        };
    }

    private function getEmployeeCascadeConfig(): array
    {
        return [
            'blockers' => [
                function (Employee $employee): ?string {
                    $employmentIds = $employee->employments()->pluck('id');
                    if ($employmentIds->isEmpty()) {
                        return null;
                    }

                    $payrollCount = Payroll::whereIn('employment_id', $employmentIds)->count();
                    if ($payrollCount > 0) {
                        return "Cannot delete: {$payrollCount} payroll record(s) exist for this employee. Please delete or archive payrolls first.";
                    }

                    return null;
                },
            ],
            // Order: deepest children first, parent last
            'snapshot_order' => [
                // Tier 4: Allocation & employment history (deep leaves)
                ['model' => EmployeeFundingAllocationHistory::class, 'query' => fn (Employee $emp) => EmployeeFundingAllocationHistory::where('employee_id', $emp->id), 'table' => 'employee_funding_allocation_history'],
                ['model' => EmploymentHistory::class, 'query' => fn (Employee $emp) => EmploymentHistory::where('employee_id', $emp->id), 'table' => 'employment_histories'],
                ['model' => ProbationRecord::class, 'query' => fn (Employee $emp) => ProbationRecord::where('employee_id', $emp->id), 'table' => 'probation_records'],

                // Tier 3b: Funding allocations (must go before employments)
                ['model' => EmployeeFundingAllocation::class, 'query' => fn (Employee $emp) => EmployeeFundingAllocation::where('employee_id', $emp->id), 'table' => 'employee_funding_allocations'],

                // Tier 3a: Personnel actions (reference employment_id with NO ACTION)
                ['model' => PersonnelAction::class, 'query' => fn (Employee $emp) => PersonnelAction::withTrashed()->whereIn('employment_id', $emp->employments()->pluck('id')), 'table' => 'personnel_actions'],

                // Tier 2: Employments (CASCADE from employee, but must go after its children above)
                ['model' => Employment::class, 'query' => fn (Employee $emp) => Employment::where('employee_id', $emp->id), 'table' => 'employments'],

                // Tier 1: Direct employee children
                ['model' => LeaveRequestItem::class, 'query' => fn (Employee $emp) => LeaveRequestItem::whereIn('leave_request_id', LeaveRequest::where('employee_id', $emp->id)->pluck('id')), 'table' => 'leave_request_items'],
                ['model' => LeaveRequest::class, 'query' => fn (Employee $emp) => LeaveRequest::where('employee_id', $emp->id), 'table' => 'leave_requests'],
                ['model' => LeaveBalance::class, 'query' => fn (Employee $emp) => LeaveBalance::where('employee_id', $emp->id), 'table' => 'leave_balances'],
                ['model' => TravelRequest::class, 'query' => fn (Employee $emp) => TravelRequest::where('employee_id', $emp->id), 'table' => 'travel_requests'],
                ['model' => Resignation::class, 'query' => fn (Employee $emp) => Resignation::withTrashed()->where('employee_id', $emp->id), 'table' => 'resignations'],
                ['model' => EmployeeBeneficiary::class, 'query' => fn (Employee $emp) => EmployeeBeneficiary::where('employee_id', $emp->id), 'table' => 'employee_beneficiaries'],
                ['model' => EmployeeChild::class, 'query' => fn (Employee $emp) => EmployeeChild::where('employee_id', $emp->id), 'table' => 'employee_children'],
                ['model' => EmployeeEducation::class, 'query' => fn (Employee $emp) => EmployeeEducation::where('employee_id', $emp->id), 'table' => 'employee_education'],
                ['model' => EmployeeLanguage::class, 'query' => fn (Employee $emp) => EmployeeLanguage::where('employee_id', $emp->id), 'table' => 'employee_languages'],
                ['model' => EmployeeTraining::class, 'query' => fn (Employee $emp) => EmployeeTraining::where('employee_id', $emp->id), 'table' => 'employee_trainings'],
                // TaxCalculationLog excluded: tax_calculation_logs table not yet created
                ['model' => HolidayCompensationRecord::class, 'query' => fn (Employee $emp) => HolidayCompensationRecord::where('employee_id', $emp->id), 'table' => 'holiday_compensation_records'],
            ],
        ];
    }

    private function getGrantCascadeConfig(): array
    {
        return [
            'blockers' => [
                function (Grant $grant): ?string {
                    $allocationCount = EmployeeFundingAllocation::whereIn(
                        'grant_item_id',
                        GrantItem::where('grant_id', $grant->id)->pluck('id')
                    )->count();

                    if ($allocationCount > 0) {
                        return "Cannot delete: {$allocationCount} employee funding allocation(s) reference this grant's items. Please remove or reassign allocations first.";
                    }

                    return null;
                },
            ],
            'snapshot_order' => [
                ['model' => GrantItem::class, 'query' => fn (Grant $grant) => GrantItem::where('grant_id', $grant->id), 'table' => 'grant_items'],
            ],
        ];
    }

    private function getDepartmentCascadeConfig(): array
    {
        return [
            'blockers' => [
                function (Department $dept): ?string {
                    $employmentCount = Employment::where('department_id', $dept->id)->count();
                    if ($employmentCount > 0) {
                        return "Cannot delete: {$employmentCount} employment record(s) are assigned to this department.";
                    }

                    return null;
                },
                function (Department $dept): ?string {
                    $historyCount = EmploymentHistory::where('department_id', $dept->id)->count();
                    if ($historyCount > 0) {
                        return "Cannot delete: {$historyCount} employment history record(s) reference this department.";
                    }

                    return null;
                },
                function (Department $dept): ?string {
                    $personnelCount = PersonnelAction::withTrashed()
                        ->where(fn ($q) => $q->where('current_department_id', $dept->id)->orWhere('new_department_id', $dept->id))
                        ->count();
                    if ($personnelCount > 0) {
                        return "Cannot delete: {$personnelCount} personnel action record(s) reference this department.";
                    }

                    return null;
                },
            ],
            'snapshot_order' => [
                ['model' => Position::class, 'query' => fn (Department $dept) => Position::where('department_id', $dept->id), 'table' => 'positions'],
                ['model' => SectionDepartment::class, 'query' => fn (Department $dept) => SectionDepartment::withTrashed()->where('department_id', $dept->id), 'table' => 'section_departments'],
            ],
        ];
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────

    /** Cache of column listings per table to avoid repeated schema queries. */
    private array $columnCache = [];

    /**
     * Restore a single record with IDENTITY_INSERT handling for SQL Server.
     *
     * SQL Server's ODBC driver requires SET IDENTITY_INSERT to be executed via
     * unprepared() (PDO::exec) rather than statement() (PDO::prepare+execute),
     * because prepared statements don't reliably carry session-level SET state.
     * We also use the query builder's insert() (not Eloquent save()) to ensure
     * the INSERT runs on the same connection as the SET command.
     */
    private function restoreRecord(DeletedModel $deletedRecord): Model
    {
        $modelClass = $deletedRecord->model;
        $data = $deletedRecord->values;
        $originalId = $data['id'] ?? null;

        $model = new $modelClass;
        $table = $model->getTable();
        $connection = $model->getConnection();

        // Filter snapshot data to only actual table columns.
        // toArray() may include relationships, accessors, or appended attributes
        // that are not real columns and would cause insert errors.
        $filteredData = $this->filterToColumns($connection, $table, $data);

        // Remove ID from filtered data (will be re-added with IDENTITY_INSERT)
        unset($filteredData['id']);

        if ($originalId && $connection->getDriverName() === 'sqlsrv') {
            // Convert ISO 8601 date strings to SQL Server format.
            // toArray() serializes dates as "2026-02-07T05:59:56.000000Z" which
            // SQL Server's ODBC driver cannot parse via query builder insert().
            $filteredData = $this->convertDatesForSqlServer($filteredData);

            // unprepared() uses PDO::exec() which reliably sets session state.
            // Query builder insert() uses the same connection instance.
            $connection->unprepared("SET IDENTITY_INSERT [{$table}] ON");
            try {
                $connection->table($table)->insert(array_merge($filteredData, ['id' => $originalId]));
            } finally {
                $connection->unprepared("SET IDENTITY_INSERT [{$table}] OFF");
            }

            // Load the restored model via Eloquent so we return a proper Model instance
            return $modelClass::find($originalId);
        }

        // Non-SQL Server or no original ID
        $restored = new $modelClass;
        $restored->forceFill($filteredData);
        if ($originalId) {
            $restored->id = $originalId;
        }
        $restored->save();

        return $restored;
    }

    /**
     * Filter an array to only keys that are actual columns in the given table.
     */
    private function filterToColumns($connection, string $table, array $data): array
    {
        if (! isset($this->columnCache[$table])) {
            $this->columnCache[$table] = $connection->getSchemaBuilder()->getColumnListing($table);
        }

        return array_intersect_key($data, array_flip($this->columnCache[$table]));
    }

    /**
     * Get a display name for a model (used in manifest's root_display_name).
     */
    private function getDisplayName(Model $model): string
    {
        if (method_exists($model, 'getActivityLogName')) {
            return $model->getActivityLogName();
        }

        if (isset($model->name)) {
            return $model->name;
        }

        if (isset($model->title)) {
            return $model->title;
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
