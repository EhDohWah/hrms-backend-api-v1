<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UniversalRestoreService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Recycle Bin', description: 'Simple dynamic recycle bin for mixed model restoration')]
class RecycleBinController extends Controller
{
    protected $restoreService;

    public function __construct(UniversalRestoreService $restoreService)
    {
        $this->restoreService = $restoreService;
    }

    #[OA\Get(path: '/recycle-bin', summary: 'Get all deleted records from all models', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'All deleted records retrieved')])]
    public function index(): JsonResponse
    {
        try {
            // Get ALL deleted records from deleted_models table
            $deletedRecords = $this->restoreService->getAllDeletedRecords();

            return response()->json([
                'success' => true,
                'data' => $deletedRecords,
                'total_count' => count($deletedRecords),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deleted records: '.$e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(path: '/recycle-bin/restore', summary: 'Dynamic restoration using model class and ID', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Record restored successfully'), new OA\Response(response: 422, description: 'Restoration failed')])]
    public function restore(Request $request): JsonResponse
    {
        // Validate the request
        $request->validate([
            'model_class' => 'required_without:deleted_record_id|string',
            'original_id' => 'required_without:deleted_record_id|integer',
            'deleted_record_id' => 'required_without_all:model_class,original_id|integer|exists:deleted_models,id',
        ]);

        try {
            if ($request->has('deleted_record_id')) {
                // Method 1: Restore by deleted_models table ID
                $restoredModel = $this->restoreService->restoreByDeletedRecordId($request->deleted_record_id);
            } else {
                // Method 2: Dynamic restoration by model class and original ID
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

    #[OA\Post(path: '/recycle-bin/bulk-restore', summary: 'Bulk dynamic restoration', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Bulk restore completed')])]
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
                'message' => "Restored {$successCount} records successfully".
                           ($failureCount > 0 ? ", {$failureCount} failed" : ''),
                'results' => $results,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk restore failed: '.$e->getMessage(),
            ], 422);
        }
    }

    #[OA\Get(path: '/recycle-bin/stats', summary: 'Get recycle bin statistics', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Recycle bin statistics retrieved successfully'), new OA\Response(response: 422, description: 'Failed to load statistics')])]
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->restoreService->getRecycleBinStats();

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
            ], 422);
        }
    }

    #[OA\Delete(path: '/recycle-bin/{deletedRecordId}', summary: 'Permanently delete a record', tags: ['Recycle Bin'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'deletedRecordId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Record permanently deleted successfully'), new OA\Response(response: 422, description: 'Failed to permanently delete record')])]
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
