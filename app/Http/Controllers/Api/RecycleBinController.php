<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UniversalRestoreService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Recycle Bin",
 *     description="Simple dynamic recycle bin for mixed model restoration"
 * )
 */
class RecycleBinController extends Controller
{
    protected $restoreService;

    public function __construct(UniversalRestoreService $restoreService)
    {
        $this->restoreService = $restoreService;
    }

    /**
     * @OA\Get(
     *     path="/recycle-bin",
     *     summary="Get all deleted records from all models",
     *     description="Retrieve all deleted records from the deleted_models table",
     *     security={{"bearerAuth":{}}},
     *     tags={"Recycle Bin"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="All deleted records retrieved",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="deleted_record_id", type="integer", example=1),
     *                     @OA\Property(property="model_class", type="string", example="App\\Models\\Interview"),
     *                     @OA\Property(property="model_type", type="string", example="Interview"),
     *                     @OA\Property(property="original_id", type="integer", example=5),
     *                     @OA\Property(property="primary_info", type="string", example="John Doe - Developer"),
     *                     @OA\Property(property="restoration_key", type="string", example="abc123def456"),
     *                     @OA\Property(property="deleted_at", type="string", format="date-time"),
     *                     @OA\Property(property="deleted_ago", type="string", example="2 hours ago"),
     *                     @OA\Property(property="data", type="object", description="Original record data")
     *                 )
     *             ),
     *             @OA\Property(property="total_count", type="integer", example=25)
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/recycle-bin/restore",
     *     summary="Dynamic restoration using model class and ID",
     *     description="Restore any model dynamically by specifying the model class and original ID",
     *     tags={"Recycle Bin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     title="Restore by Model Class and ID (Dynamic)",
     *
     *                     @OA\Property(property="model_class", type="string", example="App\\Models\\Interview", description="Full model class name from deleted_models table"),
     *                     @OA\Property(property="original_id", type="integer", example=5, description="Original record ID"),
     *                     required={"model_class", "original_id"}
     *                 ),
     *
     *                 @OA\Schema(
     *                     title="Restore by Deleted Record ID",
     *
     *                     @OA\Property(property="deleted_record_id", type="integer", example=1, description="ID from deleted_models table"),
     *                     required={"deleted_record_id"}
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Record restored successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Record restored successfully"),
     *             @OA\Property(
     *                 property="restored_record",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="model_class", type="string", example="App\\Models\\Interview"),
     *                 @OA\Property(property="model_type", type="string", example="Interview")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Restoration failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No deleted record found for App\\Models\\Interview with ID 5")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/recycle-bin/bulk-restore",
     *     summary="Bulk dynamic restoration",
     *     description="Restore multiple records from different models dynamically",
     *     tags={"Recycle Bin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="restore_requests",
     *                 type="array",
     *                 description="Array of restore requests for different models",
     *
     *                 @OA\Items(
     *                     oneOf={
     *
     *                         @OA\Schema(
     *
     *                             @OA\Property(property="model_class", type="string", example="App\\Models\\Interview"),
     *                             @OA\Property(property="original_id", type="integer", example=5)
     *                         ),
     *
     *                         @OA\Schema(
     *
     *                             @OA\Property(property="deleted_record_id", type="integer", example=1)
     *                         )
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Bulk restore completed")
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/recycle-bin/stats",
     *     summary="Get recycle bin statistics",
     *     tags={"Recycle Bin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Recycle bin statistics retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="total_deleted", type="integer", example=42),
     *                 @OA\Property(
     *                     property="by_model",
     *                     type="object",
     *                     description="Count of deleted records by model type"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Failed to load statistics",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to load statistics")
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/recycle-bin/{deletedRecordId}",
     *     summary="Permanently delete a record",
     *     tags={"Recycle Bin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="deletedRecordId",
     *         in="path",
     *         required=true,
     *         description="ID of the deleted record to permanently delete",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Record permanently deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Record permanently deleted")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Failed to permanently delete record",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to permanently delete record: Record not found")
     *         )
     *     )
     * )
     */
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
