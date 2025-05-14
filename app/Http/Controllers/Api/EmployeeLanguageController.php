<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeLanguageRequest;
use App\Http\Requests\UpdateEmployeeLanguageRequest;
use App\Http\Resources\EmployeeLanguageResource;
use App\Models\EmployeeLanguage;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Employee Language",
 *     description="API Endpoints for Employee Language"
 * )
 */
class EmployeeLanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/api/employee-language",
     *     tags={"Employee Language"},
     *     summary="Get list of employee language records",
     *     description="Returns list of employee language records",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/EmployeeLanguage")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return EmployeeLanguageResource::collection(EmployeeLanguage::orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/api/employee-language",
     *     tags={"Employee Language"},
     *     summary="Store new employee language record",
     *     description="Stores a new employee language record and returns it",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeLanguage")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeLanguage")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(StoreEmployeeLanguageRequest $request)
    {
        $employeeLanguage = EmployeeLanguage::create($request->validated());
        return new EmployeeLanguageResource($employeeLanguage);
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/employee-language/{id}",
     *     tags={"Employee Language"},
     *     summary="Get employee language record by ID",
     *     description="Returns a single employee language record",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee language ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeLanguage")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Record not found"
     *     )
     * )
     */
    public function show(EmployeeLanguage $employeeLanguage)
    {
        return new EmployeeLanguageResource($employeeLanguage);
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/api/employee-language/{id}",
     *     tags={"Employee Language"},
     *     summary="Update existing employee language record",
     *     description="Updates an existing employee language record and returns it",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee language ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeLanguage")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeLanguage")
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
    public function update(UpdateEmployeeLanguageRequest $request, EmployeeLanguage $employeeLanguage)
    {
        $employeeLanguage->update($request->validated());
        return new EmployeeLanguageResource($employeeLanguage);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/api/employee-language/{id}",
     *     tags={"Employee Language"},
     *     summary="Delete employee language record",
     *     description="Deletes an employee language record",
     *     @OA\Parameter(
     *         name="id",
     *         description="Employee language ID",
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
    public function destroy(EmployeeLanguage $employeeLanguage)
    {
        $employeeLanguage->delete();
        return response()->json(null, 204);
    }
}
