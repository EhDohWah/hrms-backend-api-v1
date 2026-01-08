<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeEducationRequest;
use App\Http\Requests\UpdateEmployeeEducationRequest;
use App\Http\Resources\EmployeeEducationResource;
use App\Models\EmployeeEducation;
use App\Models\User;
use App\Notifications\EmployeeActionNotification;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Education', description: 'API Endpoints for Employee Education')]
class EmployeeEducationController extends Controller
{
    #[OA\Get(
        path: '/employee-education',
        tags: ['Employee Education'],
        summary: 'Get list of employee education records',
        security: [['bearerAuth' => []]],
        description: 'Returns list of employee education records',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EmployeeEducation')
                )
            ),
        ]
    )]
    public function index()
    {
        return EmployeeEducationResource::collection(EmployeeEducation::orderBy('created_at', 'desc')->get());
    }

    #[OA\Post(
        path: '/employee-education',
        tags: ['Employee Education'],
        summary: 'Store new employee education record',
        description: 'Stores a new employee education record and returns it',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Employee education data',
            content: new OA\JsonContent(
                required: ['employee_id', 'school_name', 'degree', 'start_date', 'end_date', 'created_by', 'updated_by'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1, description: 'ID of the employee'),
                    new OA\Property(property: 'school_name', type: 'string', maxLength: 100, example: 'Harvard University', description: 'Name of the educational institution'),
                    new OA\Property(property: 'degree', type: 'string', maxLength: 100, example: 'Bachelor of Science in Computer Science', description: 'Degree or qualification obtained'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2018-09-01', description: 'Start date of education'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2022-06-30', description: 'End date of education'),
                    new OA\Property(property: 'created_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who created the record'),
                    new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who last updated the record'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee education record created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeEducation'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeEducationRequest $request)
    {
        $employeeEducation = EmployeeEducation::create($request->validated());

        // Send notification to all users about employee update
        $performedBy = auth()->user();
        if ($performedBy && $employeeEducation->employee) {
            $employee = $employeeEducation->employee;
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return new EmployeeEducationResource($employeeEducation);
    }

    #[OA\Get(
        path: '/employee-education/{id}',
        tags: ['Employee Education'],
        summary: 'Get employee education record by ID',
        description: 'Returns a single employee education record',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee education ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee education record retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeEducation'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    public function show(EmployeeEducation $employeeEducation)
    {
        return new EmployeeEducationResource($employeeEducation);
    }

    #[OA\Put(
        path: '/employee-education/{id}',
        tags: ['Employee Education'],
        summary: 'Update employee education record',
        description: 'Updates an employee education record and returns it',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee education ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Updated employee education data',
            content: new OA\JsonContent(
                required: ['employee_id', 'school_name', 'degree', 'start_date', 'end_date', 'created_by', 'updated_by'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1, description: 'ID of the employee'),
                    new OA\Property(property: 'school_name', type: 'string', maxLength: 100, example: 'Harvard University', description: 'Name of the educational institution'),
                    new OA\Property(property: 'degree', type: 'string', maxLength: 100, example: 'Bachelor of Science in Computer Science', description: 'Degree or qualification obtained'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2018-09-01', description: 'Start date of education'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2022-06-30', description: 'End date of education'),
                    new OA\Property(property: 'created_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who created the record'),
                    new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who last updated the record'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee education record updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeEducation'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Record not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeEducationRequest $request, EmployeeEducation $employeeEducation)
    {
        $employeeEducation->update($request->validated());

        // Send notification to all users about employee update
        $performedBy = auth()->user();
        if ($performedBy && $employeeEducation->employee) {
            $employee = $employeeEducation->employee;
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return new EmployeeEducationResource($employeeEducation);
    }

    #[OA\Delete(
        path: '/employee-education/{id}',
        tags: ['Employee Education'],
        summary: 'Delete employee education record',
        description: 'Deletes an employee education record',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee education ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    public function destroy(EmployeeEducation $employeeEducation)
    {
        // Store employee reference before deletion
        $employee = $employeeEducation->employee;
        $performedBy = auth()->user();

        $employeeEducation->delete();

        // Send notification to all users about employee update
        if ($performedBy && $employee) {
            $users = User::all();
            foreach ($users as $user) {
                $user->notify(new EmployeeActionNotification('updated', $employee, $performedBy));
            }
        }

        return response()->json(null, 204);
    }
}
