<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchRestoreRequest;
use App\Models\DeletedModel;
use App\Models\DeletionManifest;
use App\Services\SafeDeleteService;
use App\Services\UniversalRestoreService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Recycle Bin', description: 'Recycle bin for safe delete and restore with cascading relationship support')]
class RecycleBinController extends Controller
{
    public function __construct(
        protected UniversalRestoreService $restoreService,
        protected SafeDeleteService $safeDeleteService,
    ) {}

    /**
     * List all deleted records (manifest-based + legacy).
     */
    #[OA\Get(path: '/recycle-bin', summary: 'Get all deleted records from all models', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'All deleted records retrieved')])]
    public function index(): JsonResponse
    {
        try {
            // Get manifest-based deletions (Employee, Grant, Department)
            $manifests = DeletionManifest::orderBy('created_at', 'desc')->get()
                ->map(fn (DeletionManifest $m) => [
                    'type' => 'manifest',
                    'deletion_key' => $m->deletion_key,
                    'model_class' => $m->root_model,
                    'model_type' => $m->root_model_type,
                    'original_id' => $m->root_id,
                    'display_name' => $m->root_display_name,
                    'deleted_at' => $m->created_at,
                    'deleted_ago' => $m->deleted_time_ago,
                    'deleted_by' => $m->deleted_by_name,
                    'reason' => $m->reason,
                    'child_records_count' => $m->snapshot_count - 1,
                ]);

            // Get legacy flat deletions (Interview, JobOffer -- not in any manifest)
            $allManifestKeys = DeletionManifest::pluck('snapshot_keys')->flatten()->toArray();
            $legacyRecords = DeletedModel::when(! empty($allManifestKeys), function ($query) use ($allManifestKeys) {
                $query->whereNotIn('key', $allManifestKeys);
            })
                ->orderBy('created_at', 'desc')->get()
                ->map(fn (DeletedModel $r) => [
                    'type' => 'legacy',
                    'deleted_record_id' => $r->id,
                    'model_class' => $r->model,
                    'model_type' => $r->model_type,
                    'original_id' => $r->original_id,
                    'display_name' => $this->restoreService->extractPrimaryInfo($r->model, $r->values),
                    'deleted_at' => $r->created_at,
                    'deleted_ago' => $r->deleted_time_ago,
                    'deleted_by' => null,
                    'reason' => null,
                    'child_records_count' => 0,
                ]);

            $all = $manifests->concat($legacyRecords)->sortByDesc('deleted_at')->values();

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
     * Restore a manifest-based deletion by its deletion key.
     */
    #[OA\Post(path: '/recycle-bin/restore/{deletionKey}', summary: 'Restore a cascaded deletion by its key', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'deletionKey', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Record and all children restored'), new OA\Response(response: 422, description: 'Restoration failed')])]
    public function restoreByKey(string $deletionKey): JsonResponse
    {
        try {
            $restoredModel = $this->safeDeleteService->restore($deletionKey);

            return response()->json([
                'success' => true,
                'message' => 'Record and all related records restored successfully',
                'restored_record' => [
                    'id' => $restoredModel->getKey(),
                    'model_class' => get_class($restoredModel),
                    'model_type' => class_basename(get_class($restoredModel)),
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Restoration failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk restore manifest-based deletions by their deletion keys.
     */
    #[OA\Post(path: '/recycle-bin/bulk-restore-keys', summary: 'Bulk restore cascaded deletions by keys', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Bulk restore completed')])]
    public function bulkRestoreByKeys(BatchRestoreRequest $request): JsonResponse
    {
        try {
            $results = $this->safeDeleteService->bulkRestore($request->validated()['deletion_keys']);

            $successCount = count($results['succeeded']);
            $failureCount = count($results['failed']);

            return response()->json([
                'success' => $failureCount === 0,
                'message' => "Restored {$successCount} record(s) successfully"
                    .($failureCount > 0 ? ", {$failureCount} failed" : ''),
                'succeeded' => $results['succeeded'],
                'failed' => $results['failed'],
            ], $failureCount === 0 ? 200 : 207);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk restore failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Permanently delete a manifest-based deletion by its key.
     */
    #[OA\Delete(path: '/recycle-bin/permanent/{deletionKey}', summary: 'Permanently delete a manifest and all snapshots', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'deletionKey', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Permanently deleted'), new OA\Response(response: 422, description: 'Failed')])]
    public function permanentDeleteByKey(string $deletionKey): JsonResponse
    {
        try {
            $this->safeDeleteService->permanentlyDelete($deletionKey);

            return response()->json([
                'success' => true,
                'message' => 'Record and all related snapshots permanently deleted',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete: '.$e->getMessage(),
            ], 422);
        }
    }

    // ─── LEGACY METHODS (for Interview/JobOffer flat restores) ────────────

    /**
     * Legacy: Restore a single flat record (Interview, JobOffer).
     */
    #[OA\Post(path: '/recycle-bin/restore-legacy', summary: 'Legacy: restore a flat record by model class and ID', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Record restored successfully'), new OA\Response(response: 422, description: 'Restoration failed')])]
    public function restore(Request $request): JsonResponse
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
    public function bulkRestore(Request $request): JsonResponse
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
     * Get recycle bin statistics.
     */
    #[OA\Get(path: '/recycle-bin/stats', summary: 'Get recycle bin statistics', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Recycle bin statistics retrieved successfully'), new OA\Response(response: 422, description: 'Failed to load statistics')])]
    public function stats(): JsonResponse
    {
        try {
            // Legacy stats from deleted_models
            $legacyStats = $this->restoreService->getRecycleBinStats();

            // Manifest stats (cascading deletes)
            $manifestStats = DeletionManifest::selectRaw('root_model, COUNT(*) as count')
                ->groupBy('root_model')
                ->get()
                ->mapWithKeys(fn ($stat) => [class_basename($stat->root_model) => $stat->count]);

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_deleted' => $legacyStats['total_deleted'] + DeletionManifest::count(),
                    'by_model' => $manifestStats->merge($legacyStats['by_model']),
                    'manifests_count' => DeletionManifest::count(),
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

    /**
     * Legacy: Permanently delete a flat record by deleted_models ID.
     */
    #[OA\Delete(path: '/recycle-bin/{deletedRecordId}', summary: 'Legacy: permanently delete a flat record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'deletedRecordId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Record permanently deleted successfully'), new OA\Response(response: 422, description: 'Failed to permanently delete record')])]
    public function permanentDelete($deletedRecordId): JsonResponse
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
}
