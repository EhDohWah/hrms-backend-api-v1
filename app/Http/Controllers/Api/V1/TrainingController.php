<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\Training\IndexTrainingRequest;
use App\Http\Requests\Training\StoreTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Http\Resources\TrainingResource;
use App\Models\Training;
use App\Services\TrainingService;
use Illuminate\Http\JsonResponse;

/**
 * Handles CRUD operations for training programs.
 */
class TrainingController extends BaseApiController
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {}

    /**
     * List all training programs with filtering, sorting, and pagination.
     */
    public function index(IndexTrainingRequest $request): JsonResponse
    {
        $result = $this->trainingService->list($request->validated());

        return TrainingResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => 'Trainings retrieved successfully',
                'filters' => ['applied_filters' => $result['applied_filters']],
            ])
            ->response();
    }

    /**
     * Create a new training program.
     */
    public function store(StoreTrainingRequest $request): JsonResponse
    {
        $training = $this->trainingService->store($request->validated(), $request->user());

        return TrainingResource::make($training)
            ->additional(['success' => true, 'message' => 'Training created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a specific training program by ID.
     */
    public function show(Training $training): JsonResponse
    {
        return TrainingResource::make($training)
            ->additional(['success' => true, 'message' => 'Training retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing training program.
     */
    public function update(UpdateTrainingRequest $request, Training $training): JsonResponse
    {
        $training = $this->trainingService->update($training, $request->validated(), $request->user());

        return TrainingResource::make($training)
            ->additional(['success' => true, 'message' => 'Training updated successfully'])
            ->response();
    }

    /**
     * Delete a training program.
     */
    public function destroy(Training $training): JsonResponse
    {
        $this->trainingService->destroy($training);

        return $this->successResponse(null, 'Training deleted successfully');
    }

    /**
     * Batch delete multiple training programs.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Training::findOrFail($id);
                $this->trainingService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} training(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
