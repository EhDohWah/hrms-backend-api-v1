<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\IndexTravelRequestRequest;
use App\Http\Requests\SearchByStaffIdTravelRequestRequest;
use App\Http\Requests\StoreTravelRequestRequest;
use App\Http\Requests\UpdateTravelRequestRequest;
use App\Http\Resources\TravelRequestResource;
use App\Models\TravelRequest;
use App\Services\TravelRequestService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Travel Requests',
    description: 'Simple CRUD API for Travel Requests management - no approval workflow required'
)]
class TravelRequestController extends BaseApiController
{
    public function __construct(
        private readonly TravelRequestService $travelRequestService,
    ) {}

    /**
     * Display a listing of the travel requests with pagination and filtering.
     */
    #[OA\Get(
        path: '/travel-requests',
        summary: 'Get all travel requests with pagination and filtering',
        description: 'Retrieve travel requests with server-side pagination, search, and filtering capabilities',
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Page number for pagination',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, example: 1),
    )]
    #[OA\Parameter(
        name: 'per_page',
        in: 'query',
        description: 'Number of items per page (max 100)',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 10),
    )]
    #[OA\Parameter(
        name: 'search',
        in: 'query',
        description: 'Search by employee staff ID, first name, or last name',
        required: false,
        schema: new OA\Schema(type: 'string', maxLength: 255, example: 'EMP001'),
    )]
    #[OA\Parameter(
        name: 'filter_department',
        in: 'query',
        description: 'Filter by department names (comma-separated)',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'Information Technology,Human Resources'),
    )]
    #[OA\Parameter(
        name: 'filter_destination',
        in: 'query',
        description: 'Filter by destination',
        required: false,
        schema: new OA\Schema(type: 'string', example: 'Bangkok'),
    )]
    #[OA\Parameter(
        name: 'filter_transportation',
        in: 'query',
        description: 'Filter by transportation type',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['smru_vehicle', 'public_transportation', 'air', 'other'], example: 'air'),
    )]
    #[OA\Parameter(
        name: 'sort_by',
        in: 'query',
        description: 'Sort by field',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['start_date', 'destination', 'employee_name', 'department', 'created_at'], example: 'start_date'),
    )]
    #[OA\Parameter(
        name: 'sort_order',
        in: 'query',
        description: 'Sort order',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], example: 'desc'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Travel requests retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel requests retrieved successfully'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TravelRequest')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                new OA\Property(property: 'filters', type: 'object', properties: [
                    new OA\Property(property: 'applied_filters', type: 'object'),
                ]),
            ],
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function index(IndexTravelRequestRequest $request): JsonResponse
    {
        $paginator = $this->travelRequestService->list($request->validated());

        return TravelRequestResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Travel requests retrieved successfully',
                'filters' => ['applied_filters' => $this->travelRequestService->buildAppliedFilters($request->validated())],
            ])
            ->response();
    }

    /**
     * Store a newly created travel request in storage.
     */
    #[OA\Post(
        path: '/travel-requests',
        summary: 'Create a new travel request',
        description: "Create a new travel request - no approval workflow, directly stored in database. When transportation or accommodation is set to 'other', the corresponding _other_text field becomes required.",
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id'],
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                new OA\Property(property: 'department_id', type: 'integer', example: 1),
                new OA\Property(property: 'position_id', type: 'integer', example: 1),
                new OA\Property(property: 'destination', type: 'string', example: 'New York'),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2023-01-01'),
                new OA\Property(property: 'to_date', type: 'string', format: 'date', example: '2023-01-05'),
                new OA\Property(property: 'purpose', type: 'string', example: 'Business meeting'),
                new OA\Property(property: 'grant', type: 'string', example: 'Project X'),
                new OA\Property(property: 'transportation', type: 'string', example: 'air', description: 'Valid options: smru_vehicle, public_transportation, air, other'),
                new OA\Property(property: 'transportation_other_text', type: 'string', example: 'Private car rental with driver', description: "Required when transportation is 'other'. Examples: 'Company shuttle', 'Motorcycle taxi', 'Rental car'"),
                new OA\Property(property: 'accommodation', type: 'string', example: 'smru_arrangement', description: 'Valid options: smru_arrangement, self_arrangement, other'),
                new OA\Property(property: 'accommodation_other_text', type: 'string', example: 'Family guest house near conference center', description: "Required when accommodation is 'other'. Examples: 'Client-provided housing', 'Local guest house', 'Camping site'"),
                new OA\Property(property: 'request_by_date', type: 'string', format: 'date', example: '2025-03-15'),
                new OA\Property(property: 'supervisor_approved', type: 'boolean', example: false),
                new OA\Property(property: 'supervisor_approved_date', type: 'string', format: 'date', example: '2025-03-16'),
                new OA\Property(property: 'hr_acknowledged', type: 'boolean', example: false),
                new OA\Property(property: 'hr_acknowledgement_date', type: 'string', format: 'date', example: '2025-03-17'),
                new OA\Property(property: 'remarks', type: 'string', example: 'Approved'),
                new OA\Property(property: 'created_by', type: 'string'),
                new OA\Property(property: 'updated_by', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Travel request created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel request created successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/TravelRequest'),
            ],
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function store(StoreTravelRequestRequest $request): JsonResponse
    {
        $travelRequest = $this->travelRequestService->create($request->validated());

        return TravelRequestResource::make($travelRequest)
            ->additional(['success' => true, 'message' => 'Travel request created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified travel request.
     */
    #[OA\Get(
        path: '/travel-requests/{id}',
        summary: 'Get a specific travel request',
        description: 'Retrieve a specific travel request with employee, department, and position details',
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Travel request ID',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Travel request retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel request retrieved successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/TravelRequest'),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Travel request not found',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function show(TravelRequest $travelRequest): JsonResponse
    {
        $travelRequest = $this->travelRequestService->show($travelRequest);

        return TravelRequestResource::make($travelRequest)
            ->additional(['success' => true, 'message' => 'Travel request retrieved successfully'])
            ->response();
    }

    /**
     * Update the specified travel request in storage.
     */
    #[OA\Put(
        path: '/travel-requests/{id}',
        summary: 'Update a travel request',
        description: "Update an existing travel request - simple CRUD operation with no approval workflow. When transportation or accommodation is set to 'other', the corresponding _other_text field becomes required.",
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Travel request ID',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                new OA\Property(property: 'department_id', type: 'integer', example: 1),
                new OA\Property(property: 'position_id', type: 'integer', example: 1),
                new OA\Property(property: 'destination', type: 'string', example: 'New York'),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2023-01-01'),
                new OA\Property(property: 'to_date', type: 'string', format: 'date', example: '2023-01-05'),
                new OA\Property(property: 'purpose', type: 'string', example: 'Business meeting'),
                new OA\Property(property: 'grant', type: 'string', example: 'Project X'),
                new OA\Property(property: 'transportation', type: 'string', example: 'air', description: 'Valid options: smru_vehicle, public_transportation, air, other'),
                new OA\Property(property: 'transportation_other_text', type: 'string', example: 'Private car rental with driver', description: "Required when transportation is 'other'. Examples: 'Company shuttle', 'Motorcycle taxi', 'Rental car'"),
                new OA\Property(property: 'accommodation', type: 'string', example: 'smru_arrangement', description: 'Valid options: smru_arrangement, self_arrangement, other'),
                new OA\Property(property: 'accommodation_other_text', type: 'string', example: 'Family guest house near conference center', description: "Required when accommodation is 'other'. Examples: 'Client-provided housing', 'Local guest house', 'Camping site'"),
                new OA\Property(property: 'request_by_date', type: 'string', format: 'date', example: '2025-03-15'),
                new OA\Property(property: 'supervisor_approved', type: 'boolean', example: false),
                new OA\Property(property: 'supervisor_approved_date', type: 'string', format: 'date', example: '2025-03-16'),
                new OA\Property(property: 'hr_acknowledged', type: 'boolean', example: false),
                new OA\Property(property: 'hr_acknowledgement_date', type: 'string', format: 'date', example: '2025-03-17'),
                new OA\Property(property: 'remarks', type: 'string', example: 'Approved'),
                new OA\Property(property: 'created_by', type: 'string'),
                new OA\Property(property: 'updated_by', type: 'string'),
            ],
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Travel request updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel request updated successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/TravelRequest'),
            ],
        ),
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function update(UpdateTravelRequestRequest $request, TravelRequest $travelRequest): JsonResponse
    {
        $travelRequest = $this->travelRequestService->update($travelRequest, $request->validated());

        return TravelRequestResource::make($travelRequest)
            ->additional(['success' => true, 'message' => 'Travel request updated successfully'])
            ->response();
    }

    /**
     * Remove the specified travel request from storage.
     */
    #[OA\Delete(
        path: '/travel-requests/{id}',
        summary: 'Delete a travel request',
        description: 'Permanently delete a travel request from the database',
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'Travel request ID',
        required: true,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Response(
        response: 200,
        description: 'Travel request deleted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel request deleted successfully'),
            ],
        ),
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function destroy(TravelRequest $travelRequest): JsonResponse
    {
        $this->travelRequestService->delete($travelRequest);

        return $this->successResponse(null, 'Travel request deleted successfully');
    }

    /**
     * Get available options for transportation and accommodation.
     */
    #[OA\Get(
        path: '/travel-requests/options',
        summary: 'Get available options for transportation and accommodation',
        description: 'Get the list of available checkbox options for transportation and accommodation fields',
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'Options retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Options retrieved successfully'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'transportation', type: 'array', items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'value', type: 'string', example: 'smru_vehicle'),
                            new OA\Property(property: 'label', type: 'string', example: 'SMRU vehicle'),
                        ],
                    )),
                    new OA\Property(property: 'accommodation', type: 'array', items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'value', type: 'string', example: 'smru_arrangement'),
                            new OA\Property(property: 'label', type: 'string', example: 'SMRU arrangement'),
                        ],
                    )),
                ]),
            ],
        ),
    )]
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Options retrieved successfully',
            'data' => $this->travelRequestService->getOptions(),
        ]);
    }

    /**
     * Search travel requests by employee staff ID.
     */
    #[OA\Get(
        path: '/travel-requests/search/employee/{staffId}',
        summary: 'Search travel requests by employee staff ID',
        description: 'Returns travel requests for a specific employee identified by their staff ID',
        operationId: 'searchTravelRequestsByStaffId',
        tags: ['Travel Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(
        name: 'staffId',
        in: 'path',
        description: 'Staff ID of the employee to search for',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'EMP001'),
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Page number for pagination',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, example: 1),
    )]
    #[OA\Parameter(
        name: 'per_page',
        in: 'query',
        description: 'Number of items per page (max 50)',
        required: false,
        schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50, example: 10),
    )]
    #[OA\Response(
        response: 200,
        description: 'Travel requests found successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Travel requests found for staff ID: EMP001'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TravelRequest')),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                new OA\Property(property: 'employee_info', type: 'object', properties: [
                    new OA\Property(property: 'staff_id', type: 'string', example: 'EMP001'),
                    new OA\Property(property: 'full_name', type: 'string', example: 'John Doe'),
                ]),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Employee not found',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error',
        content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
    )]
    public function searchByStaffId(SearchByStaffIdTravelRequestRequest $request, string $staffId): JsonResponse
    {
        $result = $this->travelRequestService->searchByStaffId($staffId, $request->validated());

        return TravelRequestResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => "Travel requests found for staff ID: {$staffId}",
                'employee_info' => [
                    'staff_id' => $result['employee']->staff_id,
                    'full_name' => "{$result['employee']->first_name_en} {$result['employee']->last_name_en}",
                ],
            ])
            ->response();
    }
}
