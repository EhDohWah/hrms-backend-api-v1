<?php

namespace App\Services;

use App\Models\DeletedModel;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UniversalRestoreService
{
    /**
     * Get all deleted records from deleted_models table
     */
    public function getAllDeletedRecords(): array
    {
        $deletedRecords = DeletedModel::orderBy('created_at', 'desc')->get();

        return $deletedRecords->map(function ($record) {
            return [
                'deleted_record_id' => $record->id,
                'restoration_key' => $record->key,
                'model_class' => $record->model,
                'model_type' => class_basename($record->model),
                'original_id' => $record->values['id'] ?? null,
                'deleted_at' => $record->created_at,
                'deleted_ago' => $record->created_at->diffForHumans(),
                'primary_info' => $this->extractPrimaryInfo($record->model, $record->values),
                'data' => $record->values,
            ];
        })->toArray();
    }

    /**
     * Dynamic restoration: Use model class from deleted_models + original ID
     * This is the key method that makes restoration dynamic
     */
    public function restoreByModelAndId(string $modelClass, $originalId): Model
    {
        // Validate model class exists
        if (! class_exists($modelClass)) {
            throw new Exception("Model class {$modelClass} does not exist");
        }

        \Log::info('Restoring model', ['modelClass' => $modelClass, 'originalId' => $originalId, 'type' => gettype($originalId)]);

        // Find the deleted record using model class and original ID

        // Try multiple approaches to handle different data types
        // SQL Server uses JSON_VALUE() for JSON field queries
        $deletedRecord = DeletedModel::where('model', $modelClass)
            ->where(function ($query) use ($originalId) {
                $query->whereRaw("JSON_VALUE([values], '$.id') = ?", [$originalId])
                    // Try as string if it's numeric
                    ->orWhereRaw("JSON_VALUE([values], '$.id') = ?", [(string) $originalId])
                    // Try as integer if it's a string number
                    ->orWhereRaw("JSON_VALUE([values], '$.id') = ?", [(int) $originalId]);
            })
            ->first();

        if (! $deletedRecord) {
            throw new Exception(
                "No deleted record found for {$modelClass} with ID {$originalId}. Deleted record: ".json_encode($deletedRecord).'. Type: '.gettype($originalId)
            );
        }

        // Use the model class dynamically with Spatie's restore method
        // This is equivalent to: BlogPost::restore($key) but dynamic
        $restoredModel = $modelClass::restore($deletedRecord->key);

        // Log restoration
        Log::info('Dynamic restoration completed', [
            'model_class' => $modelClass,
            'original_id' => $originalId,
            'restored_id' => $restoredModel->id,
            'deleted_record_id' => $deletedRecord->id,
            'restoration_key' => $deletedRecord->key,
        ]);

        return $restoredModel;
    }

    /**
     * Alternative: Restore by deleted_models table ID
     * Get model class from deleted_models record, then restore
     */
    public function restoreByDeletedRecordId($deletedRecordId): Model
    {
        $deletedRecord = DeletedModel::findOrFail($deletedRecordId);

        // Extract model class and original ID from the deleted record
        $modelClass = $deletedRecord->model;
        $originalId = $deletedRecord->values['id'] ?? null;

        if (! $originalId) {
            throw new Exception('Original ID not found in deleted record');
        }

        // Use dynamic restoration
        return $this->restoreByModelAndId($modelClass, $originalId);
    }

    /**
     * Bulk dynamic restoration
     */
    public function bulkRestore(array $restoreRequests): array
    {
        $results = [];

        foreach ($restoreRequests as $index => $request) {
            try {
                if (isset($request['deleted_record_id'])) {
                    // Method 1: By deleted_models ID
                    $restored = $this->restoreByDeletedRecordId($request['deleted_record_id']);
                } elseif (isset($request['model_class'], $request['original_id'])) {
                    // Method 2: By model class and original ID (your preferred dynamic approach)
                    $restored = $this->restoreByModelAndId($request['model_class'], $request['original_id']);
                } else {
                    throw new Exception("Invalid restore request format at index {$index}");
                }

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'model_class' => get_class($restored),
                    'restored_id' => $restored->id,
                    'request' => $request,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'index' => $index,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'request' => $request,
                ];
            }
        }

        return $results;
    }

    /**
     * Extract primary identifying information for display
     */
    private function extractPrimaryInfo(string $modelClass, array $data): string
    {
        // Define display fields for each model
        $displayFields = [
            'App\Models\Interview' => ['candidate_name', 'job_position'],
            'App\Models\User' => ['name', 'email'],
            'App\Models\BlogPost' => ['title'],
            'App\Models\Department' => ['name'],
        ];

        if (! isset($displayFields[$modelClass])) {
            // Fallback to common fields
            $commonFields = ['name', 'title', 'candidate_name', 'first_name', 'email'];
            foreach ($commonFields as $field) {
                if (isset($data[$field]) && ! empty($data[$field])) {
                    return $data[$field];
                }
            }

            return 'ID: '.($data['id'] ?? 'Unknown');
        }

        $info = [];
        foreach ($displayFields[$modelClass] as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                $info[] = $data[$field];
            }
        }

        return implode(' - ', $info) ?: 'ID: '.($data['id'] ?? 'Unknown');
    }

    /**
     * Get recycle bin statistics
     */
    public function getRecycleBinStats(): array
    {
        $totalDeleted = DeletedModel::count();

        $modelStats = DeletedModel::selectRaw('model, COUNT(*) as count')
            ->groupBy('model')
            ->get()
            ->mapWithKeys(function ($stat) {
                return [class_basename($stat->model) => $stat->count];
            });

        return [
            'total_deleted' => $totalDeleted,
            'by_model' => $modelStats,
        ];
    }

    /**
     * Permanently delete records
     */
    public function permanentlyDelete($deletedRecordId): bool
    {
        $deletedRecord = DeletedModel::findOrFail($deletedRecordId);

        Log::info('Permanently deleted record', [
            'deleted_record_id' => $deletedRecordId,
            'model_class' => $deletedRecord->model,
            'original_id' => $deletedRecord->values['id'] ?? null,
        ]);

        return $deletedRecord->delete();
    }
}
