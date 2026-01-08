<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeChild;
use App\Models\User;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Children', description: 'API Endpoints for Employee Children management')]
class EmployeeChildrenController extends Controller
{
    #[OA\Get(
        path: '/employee-children',
        summary: 'Get all employee children',
        tags: ['Employee Children'],
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
                            items: new OA\Items(ref: '#/components/schemas/EmployeeChild')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee children retrieved successfully'),
                    ]
                )
            ),
        ]
    )]
    public function index()
    {
        $employeeChildren = EmployeeChild::all();

        return response()->json([
            'status' => 'success',
            'data' => $employeeChildren,
            'message' => 'Employee children retrieved successfully',
        ], 200);
    }

    #[OA\Post(
        path: '/employee-children',
        summary: 'Create a new employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2020-01-01'),
                    new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
                    new OA\Property(property: 'updated_by', type: 'string', example: 'admin', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee child created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child created successfully'),
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
            'name' => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ]);

        $employeeChild = EmployeeChild::create($validatedData);

        // Send notification to all users about employee update
        $performedBy = auth()->user();
        if ($performedBy && $employeeChild->employee) {
            $employee = $employeeChild->employee;
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child created successfully',
        ], 201);
    }

    #[OA\Get(
        path: '/employee-children/{id}',
        summary: 'Get employee child by ID',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee child ID',
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
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child retrieved successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
                    ]
                )
            ),
        ]
    )]
    public function show($id)
    {
        $employeeChild = EmployeeChild::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child retrieved successfully',
        ], 200);
    }

    #[OA\Put(
        path: '/employee-children/{id}',
        summary: 'Update an employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee child ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2020-01-01'),
                    new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
                    new OA\Property(property: 'updated_by', type: 'string', example: 'admin', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee child updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child updated successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
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
        $employeeChild = EmployeeChild::findOrFail($id);

        $validatedData = $request->validate([
            'employee_id' => 'sometimes|required|integer|exists:employees,id',
            'name' => 'sometimes|required|string|max:100',
            'date_of_birth' => 'sometimes|required|date',
            'created_by' => 'sometimes|nullable|string|max:100',
            'updated_by' => 'sometimes|nullable|string|max:100',
        ]);

        $employeeChild->update($validatedData);

        // Send notification to all users about employee update
        $performedBy = auth()->user();
        if ($performedBy && $employeeChild->employee) {
            $employee = $employeeChild->employee;
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child updated successfully',
        ], 200);
    }

    #[OA\Delete(
        path: '/employee-children/{id}',
        summary: 'Delete an employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee child ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee child deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child deleted successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
                    ]
                )
            ),
        ]
    )]
    public function destroy($id)
    {
        $employeeChild = EmployeeChild::findOrFail($id);
        // Store employee reference before deletion
        $employee = $employeeChild->employee;
        $performedBy = auth()->user();

        $employeeChild->delete();

        // Send notification to all users about employee update
        if ($performedBy && $employee) {
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Employee child deleted successfully',
        ], 200);
    }
}
