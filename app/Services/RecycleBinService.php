<?php

namespace App\Services;

use App\Models\DeletedModel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Grant;
use App\Models\Payroll;
use Illuminate\Support\Collection;

class RecycleBinService
{
    private const SOFT_DELETE_MODELS = [
        'employee' => Employee::class,
        'grant' => Grant::class,
        'department' => Department::class,
        'payroll' => Payroll::class,
    ];

    public function __construct(
        private readonly UniversalRestoreService $restoreService,
    ) {}

    /**
     * List all deleted records (soft-deleted + legacy), sorted by deletion date.
     */
    public function listAll(): Collection
    {
        $softDeleted = collect();

        foreach (self::SOFT_DELETE_MODELS as $type => $modelClass) {
            $records = $modelClass::onlyTrashed()->get();
            foreach ($records as $record) {
                $softDeleted->push([
                    'type' => 'soft_delete',
                    'model_type' => class_basename($modelClass),
                    'original_id' => $record->id,
                    'display_name' => method_exists($record, 'getActivityLogName')
                        ? $record->getActivityLogName()
                        : ($record->name ?? "#{$record->id}"),
                    'deleted_at' => $record->deleted_at,
                    'deleted_ago' => $record->deleted_at->diffForHumans(),
                ]);
            }
        }

        $legacyRecords = DeletedModel::orderBy('created_at', 'desc')->get()
            ->map(fn (DeletedModel $r) => [
                'type' => 'legacy',
                'deleted_record_id' => $r->id,
                'model_type' => $r->model_type,
                'original_id' => $r->original_id,
                'display_name' => $this->restoreService->extractPrimaryInfo($r->model, $r->values),
                'deleted_at' => $r->created_at,
                'deleted_ago' => $r->deleted_time_ago,
            ]);

        return $softDeleted->concat($legacyRecords)->sortByDesc('deleted_at')->values();
    }

    /**
     * Restore a soft-deleted record by model type and ID.
     */
    public function restore(string $modelType, int $id): array
    {
        $modelClass = $this->resolveModelClass($modelType);
        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $record->restore();

        return [
            'id' => $record->id,
            'model_type' => class_basename($modelClass),
        ];
    }

    /**
     * Bulk restore soft-deleted records. Returns succeeded/failed arrays.
     */
    public function bulkRestore(array $items): array
    {
        $succeeded = [];
        $failed = [];

        foreach ($items as $item) {
            try {
                $modelClass = $this->resolveModelClass($item['model_type']);
                $record = $modelClass::onlyTrashed()->findOrFail($item['id']);
                $record->restore();

                $succeeded[] = [
                    'model_type' => class_basename($modelClass),
                    'id' => $record->id,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'model_type' => $item['model_type'],
                    'id' => $item['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }

    /**
     * Permanently delete a soft-deleted record.
     */
    public function permanentDelete(string $modelType, int $id): string
    {
        $modelClass = $this->resolveModelClass($modelType);
        $record = $modelClass::onlyTrashed()->findOrFail($id);
        $record->forceDelete();

        return class_basename($modelClass);
    }

    /**
     * Get recycle bin statistics.
     */
    public function stats(): array
    {
        $byModel = [];
        $totalSoftDeleted = 0;

        foreach (self::SOFT_DELETE_MODELS as $type => $modelClass) {
            $count = $modelClass::onlyTrashed()->count();
            $byModel[class_basename($modelClass)] = $count;
            $totalSoftDeleted += $count;
        }

        $legacyStats = $this->restoreService->getRecycleBinStats();

        return [
            'total_deleted' => $totalSoftDeleted + $legacyStats['total_deleted'],
            'by_model' => collect($byModel)->merge($legacyStats['by_model']),
            'soft_deleted_count' => $totalSoftDeleted,
            'legacy_count' => $legacyStats['total_deleted'],
        ];
    }

    /**
     * Restore a legacy flat record (Interview, JobOffer).
     */
    public function restoreLegacy(?int $deletedRecordId, ?string $modelClass, ?int $originalId): array
    {
        if ($deletedRecordId) {
            $restoredModel = $this->restoreService->restoreByDeletedRecordId($deletedRecordId);
        } else {
            $restoredModel = $this->restoreService->restoreByModelAndId($modelClass, $originalId);
        }

        return [
            'id' => $restoredModel->id,
            'model_class' => get_class($restoredModel),
            'model_type' => class_basename(get_class($restoredModel)),
        ];
    }

    /**
     * Bulk restore legacy flat records.
     */
    public function bulkRestoreLegacy(array $restoreRequests): array
    {
        $results = $this->restoreService->bulkRestore($restoreRequests);

        $successCount = count(array_filter($results, fn ($r) => $r['success']));
        $failureCount = count($results) - $successCount;

        return [
            'results' => $results,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ];
    }

    /**
     * Permanently delete a legacy flat record.
     */
    public function permanentDeleteLegacy(int $deletedRecordId): void
    {
        $this->restoreService->permanentlyDelete($deletedRecordId);
    }

    /**
     * Resolve model type string to fully qualified class name.
     */
    private function resolveModelClass(string $modelType): string
    {
        $type = strtolower($modelType);
        if (! isset(self::SOFT_DELETE_MODELS[$type])) {
            throw new \InvalidArgumentException(
                "Unknown model type: {$modelType}. Supported: ".implode(', ', array_keys(self::SOFT_DELETE_MODELS))
            );
        }

        return self::SOFT_DELETE_MODELS[$type];
    }
}
