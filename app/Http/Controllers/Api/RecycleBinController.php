<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeletedModel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Grant;
use App\Models\Payroll;
use App\Services\UniversalRestoreService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Recycle Bin', description: 'Recycle bin for soft-deleted records and legacy deleted models')]
class RecycleBinController extends Controller
{
    /**
     * Soft-deletable model type mapping.
     */
    private const SOFT_DELETE_MODELS = [
        'employee' => Employee::class,
        'grant' => Grant::class,
        'department' => Department::class,
        'payroll' => Payroll::class,
    ];

    public function __construct(
        protected UniversalRestoreService $restoreService,
    ) {}

    /**
     * List all deleted records (soft-deleted + legacy).
     */
    #[OA\Get(path: '/recycle-bin', summary: 'Get all deleted records', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'All deleted records retrieved')])]
    public function index(): JsonResponse
    {
        try {
            // Soft-deleted records from Employee, Grant, Department
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

            // Legacy flat deletions (Interview, JobOffer via KeepsDeletedModels)
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

            $all = $softDeleted->concat($legacyRecords)->sortByDesc('deleted_at')->values();

            return response()->json([
                'success' => true,
                'data' => $all,
                'total_count' => $all->count(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deleted records: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted record by model type and ID.
     */
    #[OA\Post(path: '/recycle-bin/restore/{modelType}/{id}', summary: 'Restore a soft-deleted record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'modelType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['employee', 'grant', 'department', 'payroll'])), new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Record restored'), new OA\Response(response: 404, description: 'Record not found'), new OA\Response(response: 422, description: 'Restoration failed')])]
    public function restore(string $modelType, int $id): JsonResponse
    {
        try {
            $modelClass = $this->resolveModelClass($modelType);
            $record = $modelClass::onlyTrashed()->findOrFail($id);
            $record->restore();

            return response()->json([
                'success' => true,
                'message' => class_basename($modelClass).' restored successfully',
                'restored_record' => [
                    'id' => $record->id,
                    'model_type' => class_basename($modelClass),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted record not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restoration failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk restore soft-deleted records.
     */
    #[OA\Post(path: '/recycle-bin/bulk-restore', summary: 'Bulk restore soft-deleted records', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(content: new OA\JsonContent(required: ['items'], properties: [new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'model_type', type: 'string'), new OA\Property(property: 'id', type: 'integer')]))])), responses: [new OA\Response(response: 200, description: 'Bulk restore completed')])]
    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.model_type' => 'required|string',
            'items.*.id' => 'required|integer',
        ]);

        $succeeded = [];
        $failed = [];

        foreach ($request->items as $item) {
            try {
                $modelClass = $this->resolveModelClass($item['model_type']);
                $record = $modelClass::onlyTrashed()->findOrFail($item['id']);
                $record->restore();

                $succeeded[] = [
                    'model_type' => class_basename($modelClass),
                    'id' => $record->id,
                ];
            } catch (Exception $e) {
                $failed[] = [
                    'model_type' => $item['model_type'],
                    'id' => $item['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $successCount = count($succeeded);
        $failureCount = count($failed);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "Restored {$successCount} record(s)"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ], $failureCount === 0 ? 200 : 207);
    }

    /**
     * Permanently delete a soft-deleted record.
     */
    #[OA\Delete(path: '/recycle-bin/permanent/{modelType}/{id}', summary: 'Permanently delete a soft-deleted record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'modelType', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['employee', 'grant', 'department', 'payroll'])), new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Permanently deleted'), new OA\Response(response: 404, description: 'Record not found'), new OA\Response(response: 422, description: 'Failed')])]
    public function permanentDelete(string $modelType, int $id): JsonResponse
    {
        try {
            $modelClass = $this->resolveModelClass($modelType);
            $record = $modelClass::onlyTrashed()->findOrFail($id);
            $record->forceDelete();

            return response()->json([
                'success' => true,
                'message' => class_basename($modelClass).' permanently deleted',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deleted record not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get recycle bin statistics.
     */
    #[OA\Get(path: '/recycle-bin/stats', summary: 'Get recycle bin statistics', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Statistics retrieved'), new OA\Response(response: 422, description: 'Failed')])]
    public function stats(): JsonResponse
    {
        try {
            $byModel = [];
            $totalSoftDeleted = 0;

            foreach (self::SOFT_DELETE_MODELS as $type => $modelClass) {
                $count = $modelClass::onlyTrashed()->count();
                $byModel[class_basename($modelClass)] = $count;
                $totalSoftDeleted += $count;
            }

            // Legacy stats from deleted_models
            $legacyStats = $this->restoreService->getRecycleBinStats();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_deleted' => $totalSoftDeleted + $legacyStats['total_deleted'],
                    'by_model' => collect($byModel)->merge($legacyStats['by_model']),
                    'soft_deleted_count' => $totalSoftDeleted,
                    'legacy_count' => $legacyStats['total_deleted'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
            ], 422);
        }
    }

    // ─── LEGACY METHODS (for Interview/JobOffer flat restores) ────────────

    /**
     * Legacy: Restore a single flat record (Interview, JobOffer).
     */
    #[OA\Post(path: '/recycle-bin/restore-legacy', summary: 'Legacy: restore a flat record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Record restored'), new OA\Response(response: 422, description: 'Failed')])]
    public function restoreLegacy(Request $request): JsonResponse
    {
        $request->validate([
            'model_class' => 'required_without:deleted_record_id|string',
            'original_id' => 'required_without:deleted_record_id|integer',
            'deleted_record_id' => 'required_without_all:model_class,original_id|integer|exists:deleted_models,id',
        ]);

        try {
            if ($request->has('deleted_record_id')) {
                $restoredModel = $this->restoreService->restoreByDeletedRecordId($request->deleted_record_id);
            } else {
                $restoredModel = $this->restoreService->restoreByModelAndId(
                    $request->model_class,
                    $request->original_id
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Record restored successfully',
                'restored_record' => [
                    'id' => $restoredModel->id,
                    'model_class' => get_class($restoredModel),
                    'model_type' => class_basename(get_class($restoredModel)),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Legacy: Bulk restore flat records (Interview, JobOffer).
     */
    #[OA\Post(path: '/recycle-bin/bulk-restore-legacy', summary: 'Legacy: bulk restore flat records', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Bulk restore completed')])]
    public function bulkRestoreLegacy(Request $request): JsonResponse
    {
        $request->validate([
            'restore_requests' => 'required|array',
            'restore_requests.*.model_class' => 'required_without:restore_requests.*.deleted_record_id|string',
            'restore_requests.*.original_id' => 'required_without:restore_requests.*.deleted_record_id|integer',
            'restore_requests.*.deleted_record_id' => 'required_without_all:restore_requests.*.model_class,restore_requests.*.original_id|integer|exists:deleted_models,id',
        ]);

        try {
            $results = $this->restoreService->bulkRestore($request->restore_requests);

            $successCount = count(array_filter($results, fn ($r) => $r['success']));
            $failureCount = count($results) - $successCount;

            return response()->json([
                'success' => true,
                'message' => "Restored {$successCount} records successfully"
                    .($failureCount > 0 ? ", {$failureCount} failed" : ''),
                'results' => $results,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk restore failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Legacy: Permanently delete a flat record by deleted_models ID.
     */
    #[OA\Delete(path: '/recycle-bin/legacy/{deletedRecordId}', summary: 'Legacy: permanently delete a flat record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'deletedRecordId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Permanently deleted'), new OA\Response(response: 422, description: 'Failed')])]
    public function permanentDeleteLegacy($deletedRecordId): JsonResponse
    {
        try {
            $this->restoreService->permanentlyDelete($deletedRecordId);

            return response()->json([
                'success' => true,
                'message' => 'Record permanently deleted',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete record: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resolve model type string to class.
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
