<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeEducationRequest;
use App\Http\Requests\UpdateEmployeeEducationRequest;
use App\Http\Resources\EmployeeEducationResource;
use App\Models\EmployeeEducation;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Employee Education",
 *     description="API Endpoints for Employee Education"
 * )
 */
class EmployeeEducationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/api/employee-education",
     *     tags={"Employee Education"},
     *     summary="Get list of employee education records",
     *     description="Returns list of employee education records",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/EmployeeEducation")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return EmployeeEducationResource::collection(EmployeeEducation::orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/employee-education",
     *     tags={"Employee Education"},
     *     summary="Store new employee education record",
     *     description="Stores a new employee education record and returns it",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeEducation")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeEducation")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(StoreEmployeeEducationRequest $request)
    {
        $employeeEducation = EmployeeEducation::create($request->validated());
        return new EmployeeEducationResource($employeeEducation);
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/employee-education/{id}",
     *     tags={"Employee Education"},
     *     summary="Get employee education record by ID",
     *     description="Returns a single employee education record",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee education ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeEducation")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record not found"
     *     )
     * )
     */
    public function show(EmployeeEducation $employeeEducation)
    {
        return new EmployeeEducationResource($employeeEducation);
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/employee-education/{id}",
     *     tags={"Employee Education"},
     *     summary="Update employee education record",
     *     description="Updates an employee education record and returns it",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee education ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeEducation")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeEducation")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateEmployeeEducationRequest $request, EmployeeEducation $employeeEducation)
    {
        $employeeEducation->update($request->validated());
        return new EmployeeEducationResource($employeeEducation);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/employee-education/{id}",
     *     tags={"Employee Education"},
     *     summary="Delete employee education record",
     *     description="Deletes an employee education record",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee education ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record not found"
     *     )
     * )
     */
    public function destroy(EmployeeEducation $employeeEducation)
    {
        $employeeEducation->delete();
        return response()->json(null, 204);
    }
}
