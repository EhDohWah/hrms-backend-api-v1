<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeBeneficiaryResource;
use App\Models\EmployeeBeneficiary;
use App\Models\User;
use App\Notifications\EmployeeActionNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Beneficiaries', description: 'API Endpoints for Employee Beneficiary management')]
class EmployeeBeneficiaryController extends Controller
{
    #[OA\Get(
        path: '/employee-beneficiaries',
        summary: 'Get all employee beneficiaries',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/EmployeeBeneficiary')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiaries retrieved successfully'),
                    ]
                )
            ),
        ]
    )]
    public function index()
    {
        $employeeBeneficiaries = EmployeeBeneficiary::with('employee')->get();

        return response()->json([
            'status' => 'success',
            'data' => EmployeeBeneficiaryResource::collection($employeeBeneficiaries),
            'message' => 'Employee beneficiaries retrieved successfully',
        ], 200);
    }

    #[OA\Post(
        path: '/employee-beneficiaries',
        summary: 'Create a new employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'beneficiary_name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'beneficiary_relationship', type: 'string', example: 'spouse'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
                    new OA\Property(property: 'updated_by', type: 'string', example: 'admin', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee beneficiary created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeBeneficiary'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary created successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation error'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'beneficiary_name' => 'required|string|max:255',
            'beneficiary_relationship' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ]);

        $employeeBeneficiary = EmployeeBeneficiary::create($validatedData);
        $employeeBeneficiary->load('employee');

        // Send notification using NotificationService
        $performedBy = auth()->user();
        if ($performedBy && $employeeBeneficiary->employee) {
            $employee = $employeeBeneficiary->employee;
            app(NotificationService::class)->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                'updated'
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary created successfully',
        ], 201);
    }

    #[OA\Get(
        path: '/employee-beneficiaries/{id}',
        summary: 'Get employee beneficiary by ID',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeBeneficiary'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary retrieved successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee beneficiary not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary not found'),
                    ]
                )
            ),
        ]
    )]
    public function show($id)
    {
        $employeeBeneficiary = EmployeeBeneficiary::with('employee')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary retrieved successfully',
        ], 200);
    }

    #[OA\Put(
        path: '/employee-beneficiaries/{id}',
        summary: 'Update an employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'beneficiary_name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'beneficiary_relationship', type: 'string', example: 'spouse'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '+1234567890'),
                    new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
                    new OA\Property(property: 'updated_by', type: 'string', example: 'admin', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee beneficiary updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeBeneficiary'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary updated successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee beneficiary not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary not found'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation error'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function update(Request $request, $id)
    {
        $employeeBeneficiary = EmployeeBeneficiary::findOrFail($id);

        $validatedData = $request->validate([
            'employee_id' => 'sometimes|required|integer|exists:employees,id',
            'beneficiary_name' => 'sometimes|required|string|max:255',
            'beneficiary_relationship' => 'sometimes|required|string|max:100',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'created_by' => 'sometimes|nullable|string|max:100',
            'updated_by' => 'sometimes|nullable|string|max:100',
        ]);

        $employeeBeneficiary->update($validatedData);
        $employeeBeneficiary->load('employee');

        // Send notification using NotificationService
        $performedBy = auth()->user();
        if ($performedBy && $employeeBeneficiary->employee) {
            $employee = $employeeBeneficiary->employee;
            app(NotificationService::class)->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                'updated'
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary updated successfully',
        ], 200);
    }

    #[OA\Delete(
        path: '/employee-beneficiaries/{id}',
        summary: 'Delete an employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee beneficiary deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary deleted successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee beneficiary not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiary not found'),
                    ]
                )
            ),
        ]
    )]
    public function destroy($id)
    {
        $employeeBeneficiary = EmployeeBeneficiary::findOrFail($id);
        // Store employee reference before deletion
        $employee = $employeeBeneficiary->employee;
        $performedBy = auth()->user();

        $employeeBeneficiary->delete();

        // Send notification using NotificationService
        if ($performedBy && $employee) {
            app(NotificationService::class)->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                'updated'
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Employee beneficiary deleted successfully',
        ], 200);
    }
}
