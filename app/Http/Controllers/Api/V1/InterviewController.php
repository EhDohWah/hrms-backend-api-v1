<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexInterviewRequest;
use App\Http\Requests\StoreInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Interview;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * InterviewController
 *
 * Manages interview CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()           : List all interviews with filtering
 * - show()            : Get single interview by ID
 * - store()           : Create new interview
 * - update()          : Update interview
 * - destroy()         : Delete interview
 * - byCandidateName() : Look up interview by candidate name
 */
#[OA\Tag(
    name: 'Interviews',
    description: 'API Endpoints for managing interviews'
)]
class InterviewController extends BaseApiController
{
    public function __construct(
        private readonly InterviewService $interviewService,
    ) {}

    /**
     * Display a listing of interviews with filtering and pagination.
     */
    #[OA\Get(
        path: '/api/v1/interviews',
        operationId: 'getInterviews',
        summary: 'List all interviews with pagination and filtering',
        description: 'Returns a paginated list of interviews. Supports filtering by job_position and hired_status, sorting by candidate_name, job_position, or interview_date.',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by candidate name, interviewer name, or job position', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter_job_position', in: 'query', description: 'Filter by job position (comma-separated)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter_hired_status', in: 'query', description: 'Filter by hired status (comma-separated)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', description: 'Sort field', schema: new OA\Schema(type: 'string', enum: ['candidate_name', 'job_position', 'interview_date', 'created_at']))]
    #[OA\Parameter(name: 'sort_order', in: 'query', description: 'Sort order', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']))]
    #[OA\Response(
        response: 200,
        description: 'Interviews retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Interviews retrieved successfully'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Interview')),
                new OA\Property(property: 'meta', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function index(IndexInterviewRequest $request): JsonResponse
    {
        $paginator = $this->interviewService->list($request->validated());

        return InterviewResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Interviews retrieved successfully',
            ])
            ->response();
    }

    /**
     * Display the specified interview.
     */
    #[OA\Get(
        path: '/api/v1/interviews/{id}',
        operationId: 'getInterview',
        summary: 'Get interview details',
        description: 'Get details of a specific interview',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Interview retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Interview retrieved successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Interview'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Interview not found')]
    public function show(Interview $interview): JsonResponse
    {
        return InterviewResource::make($interview)
            ->additional(['success' => true, 'message' => 'Interview retrieved successfully'])
            ->response();
    }

    /**
     * Store a newly created interview.
     */
    #[OA\Post(
        path: '/api/v1/interviews',
        operationId: 'storeInterview',
        summary: 'Create a new interview',
        description: 'Create a new interview record',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Interview')
    )]
    #[OA\Response(response: 201, description: 'Interview created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreInterviewRequest $request): JsonResponse
    {
        $interview = $this->interviewService->create($request->validated());

        return InterviewResource::make($interview)
            ->additional(['success' => true, 'message' => 'Interview created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified interview.
     */
    #[OA\Put(
        path: '/api/v1/interviews/{id}',
        operationId: 'updateInterview',
        summary: 'Update an interview',
        description: 'Update an existing interview record',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Interview')
    )]
    #[OA\Response(response: 200, description: 'Interview updated successfully')]
    #[OA\Response(response: 404, description: 'Interview not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateInterviewRequest $request, Interview $interview): JsonResponse
    {
        $interview = $this->interviewService->update($interview, $request->validated());

        return InterviewResource::make($interview)
            ->additional(['success' => true, 'message' => 'Interview updated successfully'])
            ->response();
    }

    /**
     * Remove the specified interview.
     */
    #[OA\Delete(
        path: '/api/v1/interviews/{id}',
        operationId: 'deleteInterview',
        summary: 'Delete an interview',
        description: 'Delete an existing interview record',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Interview deleted successfully')]
    #[OA\Response(response: 404, description: 'Interview not found')]
    public function destroy(Interview $interview): JsonResponse
    {
        $this->interviewService->delete($interview);

        return $this->successResponse(null, 'Interview deleted successfully');
    }

    /**
     * Get interview by candidate name (case-insensitive).
     */
    #[OA\Get(
        path: '/api/v1/interviews/by-candidate/{candidateName}',
        operationId: 'getInterviewByCandidateName',
        summary: 'Get interview by candidate name',
        description: 'Returns a specific interview by candidate name (case-insensitive match)',
        tags: ['Interviews'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'candidateName', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Interview retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Interview retrieved successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Interview'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Interview not found')]
    public function byCandidateName(string $candidateName): JsonResponse
    {
        $interview = $this->interviewService->findByCandidateName($candidateName);

        if (! $interview) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found',
            ], 404);
        }

        return InterviewResource::make($interview)
            ->additional(['success' => true, 'message' => 'Interview retrieved successfully'])
            ->response();
    }

    /**
     * Batch delete multiple interviews.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Interview::findOrFail($id);
                $this->interviewService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} interview(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
