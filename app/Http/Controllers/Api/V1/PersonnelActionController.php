<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PersonnelAction\ApprovePersonnelActionRequest;
use App\Http\Requests\PersonnelAction\IndexPersonnelActionRequest;
use App\Http\Requests\StorePersonnelActionRequest;
use App\Models\PersonnelAction;
use App\Services\PersonnelActionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles personnel action management (SMRU-SF038 form).
 */
#[OA\Tag(name: 'Personnel Actions', description: 'API Endpoints for Personnel Action management')]
class PersonnelActionController extends BaseApiController
{
    public function __construct(
        private readonly PersonnelActionService $personnelActionService,
    ) {}

    #[OA\Get(
        path: '/personnel-actions',
        summary: 'List personnel actions with filtering and pagination',
        operationId: 'getPersonnelActions',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        parameters: [
            new OA\Parameter(name: 'action_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'employment_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(IndexPersonnelActionRequest $request): JsonResponse
    {
        $actions = $this->personnelActionService->list($request->validated());

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    #[OA\Post(
        path: '/personnel-actions',
        summary: 'Create a new personnel action',
        operationId: 'createPersonnelAction',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PersonnelAction')),
        responses: [
            new OA\Response(response: 201, description: 'Personnel action created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StorePersonnelActionRequest $request): JsonResponse
    {
        $personnelAction = $this->personnelActionService->store(
            array_merge($request->validated(), ['created_by' => auth()->id()])
        );

        return response()->json([
            'success' => true,
            'message' => 'Personnel action created successfully',
            'data' => $personnelAction,
        ], 201);
    }

    #[OA\Get(
        path: '/personnel-actions/{personnelAction}',
        summary: 'Get personnel action details',
        operationId: 'getPersonnelAction',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        parameters: [
            new OA\Parameter(name: 'personnelAction', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Personnel action not found'),
        ]
    )]
    public function show(PersonnelAction $personnelAction): JsonResponse
    {
        $personnelAction = $this->personnelActionService->show($personnelAction);

        return response()->json([
            'success' => true,
            'message' => 'Personnel action retrieved successfully',
            'data' => $personnelAction,
        ]);
    }

    #[OA\Put(
        path: '/personnel-actions/{personnelAction}',
        summary: 'Update a personnel action',
        operationId: 'updatePersonnelAction',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        parameters: [
            new OA\Parameter(name: 'personnelAction', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PersonnelAction')),
        responses: [
            new OA\Response(response: 200, description: 'Personnel action updated successfully'),
            new OA\Response(response: 404, description: 'Personnel action not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(StorePersonnelActionRequest $request, PersonnelAction $personnelAction): JsonResponse
    {
        $personnelAction = $this->personnelActionService->update(
            $personnelAction,
            array_merge($request->validated(), ['updated_by' => auth()->id()])
        );

        return response()->json([
            'success' => true,
            'message' => 'Personnel action updated successfully',
            'data' => $personnelAction,
        ]);
    }

    #[OA\Patch(
        path: '/personnel-actions/{personnelAction}/approve',
        summary: 'Update approval status for a personnel action',
        operationId: 'approvePersonnelAction',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        parameters: [
            new OA\Parameter(name: 'personnelAction', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['approval_type', 'approved'],
                properties: [
                    new OA\Property(property: 'approval_type', type: 'string', enum: ['dept_head', 'coo', 'hr', 'accountant']),
                    new OA\Property(property: 'approved', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Approval status updated successfully'),
            new OA\Response(response: 404, description: 'Personnel action not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function approve(ApprovePersonnelActionRequest $request, PersonnelAction $personnelAction): JsonResponse
    {
        $validated = $request->validated();

        $personnelAction = $this->personnelActionService->approve(
            $personnelAction,
            $validated['approval_type'],
            $validated['approved'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Approval status updated successfully',
            'data' => $personnelAction,
        ]);
    }

    #[OA\Get(
        path: '/personnel-actions/constants',
        summary: 'Get personnel action constants for dropdowns',
        operationId: 'getPersonnelActionConstants',
        security: [['bearerAuth' => []]],
        tags: ['Personnel Actions'],
        responses: [
            new OA\Response(response: 200, description: 'Constants retrieved successfully'),
        ]
    )]
    public function constants(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Constants retrieved successfully',
            'data' => $this->personnelActionService->constants(),
        ]);
    }
}
