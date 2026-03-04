<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexJobOfferRequest;
use App\Http\Requests\StoreJobOfferRequest;
use App\Http\Requests\UpdateJobOfferRequest;
use App\Http\Resources\JobOfferResource;
use App\Models\JobOffer;
use App\Services\JobOfferService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Job Offers',
    description: 'Operations related to job offers',
)]
class JobOfferController extends BaseApiController
{
    public function __construct(
        private readonly JobOfferService $jobOfferService
    ) {}

    /**
     * Display a listing of job offers.
     */
    #[OA\Get(
        path: '/job-offers',
        operationId: 'getJobOffers',
        summary: 'List all job offers with pagination and filtering',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter_position', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'filter_status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['job_offer_id', 'candidate_name', 'position_name', 'date', 'status', 'created_at']))]
    #[OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']))]
    #[OA\Response(response: 200, description: 'Job offers retrieved successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    public function index(IndexJobOfferRequest $request): JsonResponse
    {
        $paginator = $this->jobOfferService->list($request->validated());

        return JobOfferResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Job offers retrieved successfully',
            ])
            ->response();
    }

    /**
     * Display the specified job offer.
     */
    #[OA\Get(
        path: '/job-offers/{id}',
        operationId: 'getJobOfferById',
        summary: 'Get a job offer by ID',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job offer retrieved successfully')]
    #[OA\Response(response: 404, description: 'Job offer not found')]
    public function show(JobOffer $jobOffer): JsonResponse
    {
        return JobOfferResource::make($jobOffer)
            ->additional([
                'success' => true,
                'message' => 'Job offer retrieved successfully',
            ])
            ->response();
    }

    /**
     * Store a newly created job offer.
     */
    #[OA\Post(
        path: '/job-offers',
        operationId: 'storeJobOffer',
        summary: 'Create a new job offer',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['date', 'candidate_name', 'position_name', 'probation_salary', 'pass_probation_salary', 'acceptance_deadline', 'acceptance_status', 'note'],
            properties: [
                new OA\Property(property: 'date', type: 'string', format: 'date'),
                new OA\Property(property: 'candidate_name', type: 'string'),
                new OA\Property(property: 'position_name', type: 'string'),
                new OA\Property(property: 'probation_salary', type: 'number'),
                new OA\Property(property: 'pass_probation_salary', type: 'number'),
                new OA\Property(property: 'acceptance_deadline', type: 'string', format: 'date'),
                new OA\Property(property: 'acceptance_status', type: 'string'),
                new OA\Property(property: 'note', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(response: 201, description: 'Job offer created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreJobOfferRequest $request): JsonResponse
    {
        $jobOffer = $this->jobOfferService->create(
            $request->validated(),
            $request->user()
        );

        return JobOfferResource::make($jobOffer)
            ->additional([
                'success' => true,
                'message' => 'Job offer created successfully',
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified job offer.
     */
    #[OA\Put(
        path: '/job-offers/{id}',
        operationId: 'updateJobOffer',
        summary: 'Update a job offer',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'date', type: 'string', format: 'date'),
                new OA\Property(property: 'candidate_name', type: 'string'),
                new OA\Property(property: 'position_name', type: 'string'),
                new OA\Property(property: 'probation_salary', type: 'number'),
                new OA\Property(property: 'pass_probation_salary', type: 'number'),
                new OA\Property(property: 'acceptance_deadline', type: 'string', format: 'date'),
                new OA\Property(property: 'acceptance_status', type: 'string'),
                new OA\Property(property: 'note', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Job offer updated successfully')]
    #[OA\Response(response: 404, description: 'Job offer not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateJobOfferRequest $request, JobOffer $jobOffer): JsonResponse
    {
        $jobOffer = $this->jobOfferService->update(
            $jobOffer,
            $request->validated(),
            $request->user()
        );

        return JobOfferResource::make($jobOffer)
            ->additional([
                'success' => true,
                'message' => 'Job offer updated successfully',
            ])
            ->response();
    }

    /**
     * Remove the specified job offer.
     */
    #[OA\Delete(
        path: '/job-offers/{id}',
        operationId: 'deleteJobOffer',
        summary: 'Delete a job offer',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job offer deleted successfully')]
    #[OA\Response(response: 404, description: 'Job offer not found')]
    public function destroy(JobOffer $jobOffer): JsonResponse
    {
        $this->jobOfferService->delete($jobOffer);

        return $this->successResponse(null, 'Job offer deleted successfully');
    }

    /**
     * Get job offer by candidate name.
     */
    #[OA\Get(
        path: '/job-offers/by-candidate/{candidateName}',
        operationId: 'getJobOfferByCandidateName',
        summary: 'Get a job offer by candidate name',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'candidateName', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Job offer retrieved successfully')]
    #[OA\Response(response: 404, description: 'Job offer not found')]
    public function byCandidateName(string $candidateName): JsonResponse
    {
        $candidateName = urldecode($candidateName);

        $jobOffer = $this->jobOfferService->findByCandidateName($candidateName);

        if (! $jobOffer) {
            return $this->errorResponse('Job offer not found', 404);
        }

        return JobOfferResource::make($jobOffer)
            ->additional([
                'success' => true,
                'message' => 'Job offer retrieved successfully',
            ])
            ->response();
    }

    /**
     * Generate a PDF job offer letter.
     */
    #[OA\Get(
        path: '/job-offers/{custom_offer_id}/pdf',
        operationId: 'generateJobOfferPdf',
        summary: 'Generate a PDF job offer letter',
        tags: ['Job Offers'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'custom_offer_id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'PDF generated successfully',
        content: new OA\MediaType(
            mediaType: 'application/pdf',
            schema: new OA\Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[OA\Response(response: 404, description: 'Job offer not found')]
    public function generatePdf(string $customOfferId)
    {
        $pdf = $this->jobOfferService->generatePdf($customOfferId);

        if (! $pdf) {
            return $this->errorResponse('Job offer not found', 404);
        }

        return $pdf;
    }

    /**
     * Batch delete multiple job offers.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\JobOffer::findOrFail($id);
                $this->jobOfferService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} job offer(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
