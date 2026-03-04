<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RecycleBin\BulkRestoreLegacyRequest;
use App\Http\Requests\RecycleBin\BulkRestoreRequest;
use App\Http\Requests\RecycleBin\RestoreLegacyRequest;
use App\Services\RecycleBinService;
use Illuminate\Http\JsonResponse;

/**
 * Handles recycle bin operations for soft-deleted and legacy deleted records.
 */
class RecycleBinController extends BaseApiController
{
    public function __construct(
        private readonly RecycleBinService $recycleBinService,
    ) {}

    /**
     * List all deleted records (soft-deleted + legacy).
     */
    public function index(): JsonResponse
    {
        $all = $this->recycleBinService->listAll();

        return $this->successResponse([
            'items' => $all,
            'total_count' => $all->count(),
        ], 'Deleted records retrieved successfully');
    }

    /**
     * Restore a soft-deleted record by model type and ID.
     */
    public function restore(string $modelType, int $id): JsonResponse
    {
        $restored = $this->recycleBinService->restore($modelType, $id);

        return $this->successResponse(
            ['restored_record' => $restored],
            "{$restored['model_type']} restored successfully"
        );
    }

    /**
     * Bulk restore soft-deleted records.
     */
    public function bulkRestore(BulkRestoreRequest $request): JsonResponse
    {
        $result = $this->recycleBinService->bulkRestore($request->validated()['items']);

        $successCount = count($result['succeeded']);
        $failureCount = count($result['failed']);
        $message = "Restored {$successCount} record(s)"
            .($failureCount > 0 ? ", {$failureCount} failed" : '');

        // 207 Multi-Status when some items fail — success:false signals partial failure
        return response()->json([
            'success' => $failureCount === 0,
            'message' => $message,
            'data' => [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
            ],
        ], $failureCount === 0 ? 200 : 207);
    }

    /**
     * Permanently delete a soft-deleted record.
     */
    public function permanentDelete(string $modelType, int $id): JsonResponse
    {
        $modelName = $this->recycleBinService->permanentDelete($modelType, $id);

        return $this->successResponse(null, "{$modelName} permanently deleted");
    }

    /**
     * Get recycle bin statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->recycleBinService->stats();

        return $this->successResponse($stats, 'Recycle bin statistics retrieved successfully');
    }

    /**
     * Legacy: Restore a single flat record (Interview, JobOffer).
     */
    public function restoreLegacy(RestoreLegacyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $restored = $this->recycleBinService->restoreLegacy(
            $validated['deleted_record_id'] ?? null,
            $validated['model_class'] ?? null,
            $validated['original_id'] ?? null,
        );

        return $this->successResponse(
            ['restored_record' => $restored],
            'Record restored successfully'
        );
    }

    /**
     * Legacy: Bulk restore flat records (Interview, JobOffer).
     */
    public function bulkRestoreLegacy(BulkRestoreLegacyRequest $request): JsonResponse
    {
        $result = $this->recycleBinService->bulkRestoreLegacy($request->validated()['restore_requests']);

        return $this->successResponse(
            ['results' => $result['results']],
            "Restored {$result['success_count']} records successfully"
                .($result['failure_count'] > 0 ? ", {$result['failure_count']} failed" : '')
        );
    }

    /**
     * Legacy: Permanently delete a flat record by deleted_models ID.
     */
    public function permanentDeleteLegacy(int $deletedRecordId): JsonResponse
    {
        $this->recycleBinService->permanentDeleteLegacy($deletedRecordId);

        return $this->successResponse(null, 'Record permanently deleted');
    }
}
