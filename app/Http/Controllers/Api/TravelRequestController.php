<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTravelRequestRequest;
use App\Http\Requests\UpdateTravelRequestRequest;
use App\Models\Employee;
use App\Models\TravelRequest;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Travel Requests",
 *     description="Simple CRUD API for Travel Requests management - no approval workflow required"
 * )
 */
class TravelRequestController extends Controller
{
    /**
     * Display a listing of the travel requests with pagination and filtering.
     *
     * @OA\Get(
     *     path="/travel-requests",
     *     summary="Get all travel requests with pagination and filtering",
     *     description="Retrieve travel requests with server-side pagination, search, and filtering capabilities",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (max 100)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by employee staff ID, first name, or last name",
     *         required=false,
     *
     *         @OA\Schema(type="string", maxLength=255, example="EMP001")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_department",
     *         in="query",
     *         description="Filter by department names (comma-separated)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Information Technology,Human Resources")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_destination",
     *         in="query",
     *         description="Filter by destination",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Bangkok")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_transportation",
     *         in="query",
     *         description="Filter by transportation type",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"smru_vehicle", "public_transportation", "air", "other"}, example="air")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"start_date", "destination", "employee_name", "department", "created_at"}, example="start_date")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel requests retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel requests retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TravelRequest")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="filters", type="object",
     *                 @OA\Property(property="applied_filters", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="transportation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the transportation method when selecting 'Other'.")
     *                 ),
     *
     *                 @OA\Property(property="accommodation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the accommodation type when selecting 'Other'.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error retrieving travel requests"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
                'filter_department' => 'string|nullable',
                'filter_destination' => 'string|nullable',
                'filter_transportation' => 'string|nullable|in:smru_vehicle,public_transportation,air,other',
                'sort_by' => 'string|nullable|in:start_date,destination,employee_name,department,created_at',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build query with relationships
            $query = TravelRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
            ]);

            // Apply search if provided (search by employee staff ID, first name, or last name)
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->whereHas('employee', function ($q) use ($searchTerm) {
                    $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
                });
            }

            // Apply department filter if provided
            if (! empty($validated['filter_department'])) {
                $departments = array_map('trim', explode(',', $validated['filter_department']));
                $query->whereHas('department', function ($q) use ($departments) {
                    $q->whereIn('name', $departments);
                });
            }

            // Apply destination filter if provided
            if (! empty($validated['filter_destination'])) {
                $query->where('destination', 'LIKE', "%{$validated['filter_destination']}%");
            }

            // Apply transportation filter if provided
            if (! empty($validated['filter_transportation'])) {
                $query->where('transportation', $validated['filter_transportation']);
            }

            // Apply sorting
            switch ($sortBy) {
                case 'employee_name':
                    $query->whereHas('employee', function ($q) use ($sortOrder) {
                        $q->orderByRaw("CONCAT(first_name_en, ' ', last_name_en) {$sortOrder}");
                    });
                    break;

                case 'department':
                    $query->whereHas('department', function ($q) use ($sortOrder) {
                        $q->orderBy('name', $sortOrder);
                    });
                    break;

                default:
                    $query->orderBy($sortBy, $sortOrder);
                    break;
            }

            // Execute pagination
            $travelRequests = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['search'])) {
                $appliedFilters['search'] = $validated['search'];
            }
            if (! empty($validated['filter_department'])) {
                $appliedFilters['department'] = explode(',', $validated['filter_department']);
            }
            if (! empty($validated['filter_destination'])) {
                $appliedFilters['destination'] = $validated['filter_destination'];
            }
            if (! empty($validated['filter_transportation'])) {
                $appliedFilters['transportation'] = $validated['filter_transportation'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Travel requests retrieved successfully',
                'data' => $travelRequests->items(),
                'pagination' => [
                    'current_page' => $travelRequests->currentPage(),
                    'per_page' => $travelRequests->perPage(),
                    'total' => $travelRequests->total(),
                    'last_page' => $travelRequests->lastPage(),
                    'from' => $travelRequests->firstItem(),
                    'to' => $travelRequests->lastItem(),
                    'has_more_pages' => $travelRequests->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving travel requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created travel request in storage.
     *
     * @OA\Post(
     *     path="/travel-requests",
     *     summary="Create a new travel request",
     *     description="Create a new travel request - no approval workflow, directly stored in database. When transportation or accommodation is set to 'other', the corresponding _other_text field becomes required.",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="position_id", type="integer", example=1),
     *             @OA\Property(property="destination", type="string", example="New York"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="purpose", type="string", example="Business meeting"),
     *             @OA\Property(property="grant", type="string", example="Project X"),
     *             @OA\Property(property="transportation", type="string", example="air", description="Valid options: smru_vehicle, public_transportation, air, other"),
     *             @OA\Property(property="transportation_other_text", type="string", example="Private car rental with driver", description="Required when transportation is 'other'. Examples: 'Company shuttle', 'Motorcycle taxi', 'Rental car'"),
     *             @OA\Property(property="accommodation", type="string", example="smru_arrangement", description="Valid options: smru_arrangement, self_arrangement, other"),
     *             @OA\Property(property="accommodation_other_text", type="string", example="Family guest house near conference center", description="Required when accommodation is 'other'. Examples: 'Client-provided housing', 'Local guest house', 'Camping site'"),
     *             @OA\Property(property="request_by_date", type="string", format="date", example="2025-03-15"),
     *             @OA\Property(property="supervisor_approved", type="boolean", example=false),
     *             @OA\Property(property="supervisor_approved_date", type="string", format="date", example="2025-03-16"),
     *             @OA\Property(property="hr_acknowledged", type="boolean", example=false),
     *             @OA\Property(property="hr_acknowledgement_date", type="string", format="date", example="2025-03-17"),
     *             @OA\Property(property="remarks", type="string", example="Approved"),
     *             @OA\Property(property="created_by", type="string"),
     *             @OA\Property(property="updated_by", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Travel request created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequest")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="transportation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the transportation method when selecting 'Other'.")
     *                 ),
     *
     *                 @OA\Property(property="accommodation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the accommodation type when selecting 'Other'.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error creating travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function store(StoreTravelRequestRequest $request)
    {
        try {
            // Get validated data (includes all approval fields)
            $validatedData = $request->validated();

            // Add created_by if not provided
            if (! isset($validatedData['created_by'])) {
                $validatedData['created_by'] = auth()->user()->name ?? 'System';
            }

            // Create travel request with all validated data including approval fields
            $travelRequest = TravelRequest::create($validatedData);

            // Load relationships for response
            $travelRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Travel request created successfully',
                'data' => $travelRequest,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating travel request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified travel request.
     *
     * @OA\Get(
     *     path="/travel-requests/{id}",
     *     summary="Get a specific travel request",
     *     description="Retrieve a specific travel request with employee, department, and position details",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequest")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Travel request not found"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $travelRequest = TravelRequest::withRelations()->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Travel request retrieved successfully',
            'data' => $travelRequest,
        ], 200);
    }

    /**
     * Update the specified travel request in storage.
     *
     * @OA\Put(
     *     path="/travel-requests/{id}",
     *     summary="Update a travel request",
     *     description="Update an existing travel request - simple CRUD operation with no approval workflow. When transportation or accommodation is set to 'other', the corresponding _other_text field becomes required.",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer", example=1),
     *             @OA\Property(property="position_id", type="integer", example=1),
     *             @OA\Property(property="destination", type="string", example="New York"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="to_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="purpose", type="string", example="Business meeting"),
     *             @OA\Property(property="grant", type="string", example="Project X"),
     *             @OA\Property(property="transportation", type="string", example="air", description="Valid options: smru_vehicle, public_transportation, air, other"),
     *             @OA\Property(property="transportation_other_text", type="string", example="Private car rental with driver", description="Required when transportation is 'other'. Examples: 'Company shuttle', 'Motorcycle taxi', 'Rental car'"),
     *             @OA\Property(property="accommodation", type="string", example="smru_arrangement", description="Valid options: smru_arrangement, self_arrangement, other"),
     *             @OA\Property(property="accommodation_other_text", type="string", example="Family guest house near conference center", description="Required when accommodation is 'other'. Examples: 'Client-provided housing', 'Local guest house', 'Camping site'"),
     *             @OA\Property(property="request_by_date", type="string", format="date", example="2025-03-15"),
     *             @OA\Property(property="supervisor_approved", type="boolean", example=false),
     *             @OA\Property(property="supervisor_approved_date", type="string", format="date", example="2025-03-16"),
     *             @OA\Property(property="hr_acknowledged", type="boolean", example=false),
     *             @OA\Property(property="hr_acknowledgement_date", type="string", format="date", example="2025-03-17"),
     *             @OA\Property(property="remarks", type="string", example="Approved"),
     *             @OA\Property(property="created_by", type="string"),
     *             @OA\Property(property="updated_by", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequest")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="transportation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the transportation method when selecting 'Other'.")
     *                 ),
     *
     *                 @OA\Property(property="accommodation_other_text", type="array",
     *
     *                     @OA\Items(type="string", example="Please specify the accommodation type when selecting 'Other'.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error updating travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function update(UpdateTravelRequestRequest $request, $id)
    {
        try {
            $travelRequest = TravelRequest::findOrFail($id);

            // Get validated data (includes all approval fields)
            $validatedData = $request->validated();

            // Add updated_by
            $validatedData['updated_by'] = auth()->user()->name ?? 'System';

            // Update travel request with all validated data including approval fields
            $travelRequest->update($validatedData);

            // Load relationships for response
            $travelRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Travel request updated successfully',
                'data' => $travelRequest,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating travel request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified travel request from storage.
     *
     * @OA\Delete(
     *     path="/travel-requests/{id}",
     *     summary="Delete a travel request",
     *     description="Permanently delete a travel request from the database",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error deleting travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $travelRequest = TravelRequest::findOrFail($id);
        $travelRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Travel request deleted successfully',
        ], 200);
    }

    /**
     * Get available options for transportation and accommodation.
     *
     * @OA\Get(
     *     path="/travel-requests/options",
     *     summary="Get available options for transportation and accommodation",
     *     description="Get the list of available checkbox options for transportation and accommodation fields",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Options retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Options retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transportation", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="value", type="string", example="smru_vehicle"),
     *                         @OA\Property(property="label", type="string", example="SMRU vehicle")
     *                     )
     *                 ),
     *                 @OA\Property(property="accommodation", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="value", type="string", example="smru_arrangement"),
     *                         @OA\Property(property="label", type="string", example="SMRU arrangement")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function options()
    {
        return response()->json([
            'success' => true,
            'message' => 'Options retrieved successfully',
            'data' => [
                'transportation' => [
                    ['value' => 'smru_vehicle', 'label' => 'SMRU vehicle'],
                    ['value' => 'public_transportation', 'label' => 'Public transportation'],
                    ['value' => 'air', 'label' => 'Air'],
                    ['value' => 'other', 'label' => 'Other please specify'],
                ],
                'accommodation' => [
                    ['value' => 'smru_arrangement', 'label' => 'SMRU arrangement'],
                    ['value' => 'self_arrangement', 'label' => 'Self arrangement'],
                    ['value' => 'other', 'label' => 'Other please specify'],
                ],
            ],
        ], 200);
    }

    /**
     * Search travel requests by employee staff ID.
     *
     * @OA\Get(
     *     path="/travel-requests/search/employee/{staffId}",
     *     summary="Search travel requests by employee staff ID",
     *     description="Returns travel requests for a specific employee identified by their staff ID",
     *     operationId="searchTravelRequestsByStaffId",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="staffId",
     *         in="path",
     *         description="Staff ID of the employee to search for",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="EMP001")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (max 50)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel requests found successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel requests found for staff ID: EMP001"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TravelRequest")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=5),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=5),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=false)
     *             ),
     *             @OA\Property(property="employee_info", type="object",
     *                 @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                 @OA\Property(property="full_name", type="string", example="John Doe")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No employee found with staff ID: EMP999")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Staff ID is required and must be a valid string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to search travel requests"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function searchByStaffId(Request $request, $staffId)
    {
        try {
            // Validate the staff ID parameter
            if (empty($staffId) || ! is_string($staffId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff ID is required and must be a valid string',
                ], 422);
            }

            // Validate query parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:50',
            ]);

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // First, find the employee by staff ID
            $employee = Employee::where('staff_id', $staffId)
                ->select('id', 'staff_id', 'first_name_en', 'last_name_en')
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => "No employee found with staff ID: {$staffId}",
                ], 404);
            }

            // Build travel requests query with relationships
            $travelRequestsQuery = TravelRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
            ])->where('employee_id', $employee->id);

            // Order by most recent start date
            $travelRequestsQuery->orderBy('start_date', 'desc');

            // Execute pagination
            $travelRequests = $travelRequestsQuery->paginate($perPage, ['*'], 'page', $page);

            // Check if any records were found
            if ($travelRequests->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => "No travel requests found for staff ID: {$staffId}",
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => null,
                        'to' => null,
                        'has_more_pages' => false,
                    ],
                    'employee_info' => [
                        'staff_id' => $employee->staff_id,
                        'full_name' => $employee->first_name_en.' '.$employee->last_name_en,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Travel requests found for staff ID: {$staffId}",
                'data' => $travelRequests->items(),
                'pagination' => [
                    'current_page' => $travelRequests->currentPage(),
                    'per_page' => $travelRequests->perPage(),
                    'total' => $travelRequests->total(),
                    'last_page' => $travelRequests->lastPage(),
                    'from' => $travelRequests->firstItem(),
                    'to' => $travelRequests->lastItem(),
                    'has_more_pages' => $travelRequests->hasMorePages(),
                ],
                'employee_info' => [
                    'staff_id' => $employee->staff_id,
                    'full_name' => $employee->first_name_en.' '.$employee->last_name_en,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search travel requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
