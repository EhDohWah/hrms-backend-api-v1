<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmploymentTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmploymentRequest;
use App\Http\Requests\UpdateProbationStatusRequest;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Services\EmployeeFundingAllocationService;
use App\Traits\HasCacheManagement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employments', description: 'API Endpoints for managing employee employment records')]
class EmploymentController extends Controller
{
    use HasCacheManagement;

    public function __construct(
        private readonly EmployeeFundingAllocationService $employeeFundingAllocationService
    ) {}

    /**
     * Override model name for cache management
     */
    protected function getModelName(): string
    {
        return 'employment';
    }

    /**
     * Display a listing of employments with advanced pagination, filtering, and sorting
     */
    #[OA\Get(
        path: '/employments',
        summary: 'Get employment records with advanced filtering and pagination',
        description: 'Returns a paginated list of employment records with filtering by organization and work location',
        operationId: 'getEmployments',
        security: [['bearerAuth' => []]],
        tags: ['Employments']
    )]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'filter_organization', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Employments retrieved successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_organization' => 'string|nullable',
                'filter_site' => 'string|nullable',
                'filter_department' => 'string|nullable',
                'sort_by' => 'string|nullable|in:staff_id,employee_name,site,start_date',
                'sort_order' => 'string|nullable|in:asc,desc',
                'include_allocations' => 'boolean', // New parameter for conditional loading
            ]);

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'start_date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build optimized query with selective fields
            $query = Employment::select([
                'id',
                'employee_id',
                'position_id',
                'department_id',
                'section_department_id',
                'site_id',
                'pay_method',
                'start_date',
                'pass_probation_date',
                'end_probation_date',
                'probation_salary',
                'pass_probation_salary',
                'health_welfare',
                'pvd',
                'saving_fund',
                'status',
                'created_at',
                'updated_at',
                'created_by',
                'updated_by',
            ])->with([
                'employee:id,staff_id,organization,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
            ]);

            // Conditionally load allocations if requested
            if ($validated['include_allocations'] ?? false) {
                $query->with([
                    'employeeFundingAllocations:id,employment_id,fte,allocated_amount,grant_item_id',
                    'employeeFundingAllocations.grantItem:id,grant_id,grant_position',
                    'employeeFundingAllocations.grantItem.grant:id,name',
                ]);
            }

            // Apply organization filter (through employee relationship)
            if (! empty($validated['filter_organization'])) {
                $subsidiaries = array_map('trim', explode(',', $validated['filter_organization']));
                $query->whereHas('employee', function ($q) use ($subsidiaries) {
                    $q->whereIn('organization', $subsidiaries);
                });
            }

            // Apply site filter (by site name or ID)
            if (! empty($validated['filter_site'])) {
                $sites = array_map('trim', explode(',', $validated['filter_site']));
                $query->where(function ($q) use ($sites) {
                    $q->whereHas('site', function ($sq) use ($sites) {
                        $sq->whereIn('name', $sites);
                    })->orWhereIn('site_id', array_filter($sites, 'is_numeric'));
                });
            }

            // Apply department filter (by department name from departments table)
            if (! empty($validated['filter_department'])) {
                $departments = array_map('trim', explode(',', $validated['filter_department']));
                $query->whereHas('department', function ($dq) use ($departments) {
                    $dq->whereIn('name', $departments);
                });
            }

            // Apply sorting optimizations
            switch ($sortBy) {
                case 'staff_id':
                    $query->addSelect([
                        'sort_staff_id' => Employee::select('staff_id')
                            ->whereColumn('employees.id', 'employments.employee_id')
                            ->limit(1),
                    ])->orderBy('sort_staff_id', $sortOrder);
                    break;

                case 'employee_name':
                    $query->addSelect([
                        'sort_employee_name' => Employee::selectRaw("CONCAT(COALESCE(first_name_en, ''), ' ', COALESCE(last_name_en, ''))")
                            ->whereColumn('employees.id', 'employments.employee_id')
                            ->limit(1),
                    ])->orderBy('sort_employee_name', $sortOrder);
                    break;

                case 'site':
                    $query->addSelect([
                        'sort_site_name' => DB::table('sites')
                            ->select('name')
                            ->whereColumn('sites.id', 'employments.site_id')
                            ->limit(1),
                    ])->orderBy('sort_site_name', $sortOrder);
                    break;

                case 'start_date':
                default:
                    $query->orderBy('start_date', $sortOrder);
                    break;
            }

            // Use the new caching system instead of manual cache management
            $filters = array_filter([
                'filter_organization' => $validated['filter_organization'] ?? null,
                'filter_site' => $validated['filter_site'] ?? null,
                'filter_department' => $validated['filter_department'] ?? null,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'include_allocations' => $validated['include_allocations'] ?? false,
            ]);

            // Cache and paginate results using the new caching system
            $employments = $this->cacheAndPaginate($query, $filters, $perPage);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_organization'])) {
                $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
            }
            if (! empty($validated['filter_site'])) {
                $appliedFilters['site'] = explode(',', $validated['filter_site']);
            }
            if (! empty($validated['filter_department'])) {
                $appliedFilters['department'] = explode(',', $validated['filter_department']);
            }

            // Fetch global benefit percentages from settings (TaxSetting is single source of truth)
            $globalBenefits = [
                'health_welfare_percentage' => \App\Models\BenefitSetting::getActiveSetting('health_welfare_percentage'),
                'pvd_percentage' => \App\Models\TaxSetting::getValue('PVD_FUND_RATE'),
                'saving_fund_percentage' => \App\Models\TaxSetting::getValue('SAVING_FUND_RATE'),
            ];

            // Add global benefit percentages to each employment item
            $items = $employments->items();
            foreach ($items as $item) {
                $item->health_welfare_percentage = $globalBenefits['health_welfare_percentage'];
                $item->pvd_percentage = $globalBenefits['pvd_percentage'];
                $item->saving_fund_percentage = $globalBenefits['saving_fund_percentage'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Employments retrieved successfully',
                'data' => $items,
                'pagination' => [
                    'current_page' => $employments->currentPage(),
                    'per_page' => $employments->perPage(),
                    'total' => $employments->total(),
                    'last_page' => $employments->lastPage(),
                    'from' => $employments->firstItem(),
                    'to' => $employments->lastItem(),
                    'has_more_pages' => $employments->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employments/search/staff-id/{staffId}',
        summary: 'Search employment records by staff ID',
        description: 'Returns employment records for a specific employee identified by their staff ID. Includes all related information like employee details, department position, work location, and funding allocations',
        operationId: 'searchEmploymentByStaffId',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staffId', in: 'path', required: true, description: 'Staff ID of the employee to search for', schema: new OA\Schema(type: 'string', example: 'EMP001')),
            new OA\Parameter(name: 'include_inactive', in: 'query', required: false, description: 'Include inactive/ended employment records', schema: new OA\Schema(type: 'boolean', example: false, default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employment records found successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
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
                'include_inactive' => 'boolean',
            ]);

            $includeInactive = $validated['include_inactive'] ?? false;

            // First, find the employee by staff ID
            $employee = Employee::where('staff_id', $staffId)
                ->select('id', 'staff_id', 'organization', 'first_name_en', 'last_name_en')
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => "No employee found with staff ID: {$staffId}",
                ], 404);
            }

            // Build employment query with relationships - EXACTLY like index() method
            $employmentsQuery = Employment::with([
                'employee:id,staff_id,organization,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
                'employeeFundingAllocations',
            ])->where('employee_id', $employee->id);

            // Filter active/inactive employments if requested
            if (! $includeInactive) {
                $employmentsQuery->where(function ($query) {
                    $query->whereNull('end_probation_date')
                        ->orWhere('end_probation_date', '>', now());
                });
            }

            // Order by start date (most recent first) - EXACTLY like index() method
            $employments = $employmentsQuery->orderBy('start_date', 'desc')->get();

            // Calculate employment statistics
            $totalEmployments = $employments->count();
            $activeEmployments = $employments->filter(function ($employment) {
                return ! $employment->end_probation_date || $employment->end_probation_date > now();
            })->count();
            $inactiveEmployments = $totalEmployments - $activeEmployments;

            // Add computed full name to employee data
            $employeeData = $employee->toArray();
            $employeeData['full_name'] = trim($employee->first_name_en.' '.$employee->last_name_en);

            // Return EXACTLY the same format as index() method
            return response()->json([
                'success' => true,
                'message' => "Employment records found for staff ID: {$staffId}",
                'data' => $employments, // ← FIXED: Return employments directly like index() method
                'employee_summary' => [
                    'staff_id' => $employee->staff_id,
                    'full_name' => $employeeData['full_name'],
                    'organization' => $employee->organization,
                ],
                'statistics' => [
                    'total_employments' => $totalEmployments,
                    'active_employments' => $activeEmployments,
                    'inactive_employments' => $inactiveEmployments,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search employment records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/employments',
        operationId: 'createEmployment',
        tags: ['Employments'],
        summary: 'Create employment record (optionally with funding allocations)',
        description: 'Creates an employment record. Funding allocations are optional and can be added separately via the EmployeeFundingAllocation API. If allocations are provided, they will be created together with the employment.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employment')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employment created successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmploymentRequest $request)
    {
        try {
            // ============================================================================
            // SECTION 1: GET VALIDATED DATA FROM FORM REQUEST
            // ============================================================================
            $validated = $request->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // Auto-calculate pass_probation_date if not provided
            // Default is 3 months after start_date
            if (! isset($validated['pass_probation_date']) && isset($validated['start_date'])) {
                $validated['pass_probation_date'] = Carbon::parse($validated['start_date'])->addMonths(3)->format('Y-m-d');
            }

            // ============================================================================
            // SECTION 2: BUSINESS LOGIC VALIDATION
            // ============================================================================

            // Check if allocations are provided and validate total effort
            // Note: Allocations are now optional - employment can be created without allocations
            $hasAllocations = ! empty($validated['allocations']) && is_array($validated['allocations']) && count($validated['allocations']) > 0;

            if ($hasAllocations) {
                // Validate that the total effort of all allocations equals exactly 100%
                $totalEffort = array_sum(array_column($validated['allocations'], 'fte'));
                if ($totalEffort != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total effort of all allocations must equal exactly 100%',
                        'current_total' => $totalEffort,
                    ], 422);
                }
            }

            // Check if employee already has active employment (using date-based logic)
            $today = Carbon::today();
            $existingActiveEmployment = Employment::where('employee_id', $validated['employee_id'])
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_probation_date')
                        ->orWhere('end_probation_date', '>=', $today);
                })
                ->exists();

            if ($existingActiveEmployment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has an active employment record. Please end the existing employment first.',
                ], 422);
            }

            // ============================================================================
            // SECTION 3: DATABASE TRANSACTION START
            // ============================================================================
            DB::beginTransaction();

            $createdFundingAllocations = [];
            $errors = [];
            $warnings = [];

            // ============================================================================
            // SECTION 4: CREATE EMPLOYMENT RECORD
            // ============================================================================
            $employmentData = array_merge(
                collect($validated)->except('allocations')->toArray(),
                [
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]
            );

            $employment = Employment::create($employmentData);

            // Create initial probation record
            // NOTE: Probation status is now tracked in probation_records table, not employments.probation_status
            if ($employment->pass_probation_date) {
                app(\App\Services\ProbationRecordService::class)->createInitialRecord($employment);
            }

            // Determine effective start date for salary calculations
            $startDate = Carbon::parse($validated['start_date']);

            // ============================================================================
            // SECTION 5: PROCESS ALLOCATIONS (OPTIONAL)
            // Note: Allocations are now optional - employment can be created without allocations
            // Allocations can be added later via POST /employee-funding-allocations
            // ============================================================================
            if ($hasAllocations) {
                foreach ($validated['allocations'] as $index => $allocationData) {
                    try {
                        $allocationType = 'grant';

                        // ============================================================================
                        // SECTION 5A: HANDLE GRANT ALLOCATIONS
                        // ============================================================================
                        if ($allocationType === 'grant') {
                            // Validate grant item exists
                            if (empty($allocationData['grant_item_id'])) {
                                $errors[] = "Allocation #{$index}: grant_item_id is required for grant allocations";

                                continue;
                            }

                            $grantItem = GrantItem::find($allocationData['grant_item_id']);
                            if (! $grantItem) {
                                $errors[] = "Allocation #{$index}: Grant item not found";

                                continue;
                            }

                            // Check grant capacity constraints using date-based logic
                            if ($grantItem->grant_position_number > 0) {
                                $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                                    ->where('start_date', '<=', $today)
                                    ->where(function ($query) use ($today) {
                                        $query->whereNull('end_probation_date')
                                            ->orWhere('end_probation_date', '>=', $today);
                                    })
                                    ->count();

                                if ($currentAllocations >= $grantItem->grant_position_number) {
                                    $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";

                                    continue;
                                }
                            }

                            $fteDecimal = $allocationData['fte'] / 100;
                            $salaryContext = $this->employeeFundingAllocationService->deriveSalaryContext(
                                $employment,
                                $fteDecimal,
                                $startDate
                            );

                            // Create grant funding allocation
                            $fundingAllocation = EmployeeFundingAllocation::create(array_merge([
                                'employee_id' => $employment->employee_id,
                                'employment_id' => $employment->id,
                                'grant_item_id' => $allocationData['grant_item_id'],
                                'grant_id' => null,
                                'fte' => $fteDecimal,
                                'status' => 'active',
                                'start_date' => $validated['start_date'],
                                'end_date' => $allocationData['end_date'] ?? $validated['end_probation_date'] ?? null,
                                'created_by' => $currentUser,
                                'updated_by' => $currentUser,
                            ], $salaryContext));

                            $createdFundingAllocations[] = $fundingAllocation;
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Allocation #{$index}: ".$e->getMessage();
                    }
                }
            } // End of hasAllocations block

            // ============================================================================
            // SECTION 6: HANDLE ERRORS AND ROLLBACK IF NECESSARY
            // Note: Only rollback if allocations were requested but ALL failed
            // If no allocations were requested, employment creation is successful
            // ============================================================================
            if ($hasAllocations && empty($createdFundingAllocations) && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employment and allocations',
                    'errors' => $errors,
                ], 422);
            }

            // If some allocations failed but others succeeded, add warnings
            if (! empty($errors)) {
                $warnings = array_merge($warnings, $errors);
            }

            // ============================================================================
            // SECTION 7: COMMIT TRANSACTION AND PREPARE RESPONSE
            // ============================================================================
            DB::commit();

            // Load the created records with their relationships
            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
            ])->find($employment->id);

            $fundingAllocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,start_date,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'grantItem.grant:id,name,code',
            ])->whereIn('id', collect($createdFundingAllocations)->pluck('id'))->get();

            // ============================================================================
            // SECTION 8: RETURN SUCCESS RESPONSE
            // ============================================================================

            // Determine appropriate message based on whether allocations were created
            $message = $hasAllocations
                ? 'Employment and funding allocations created successfully'
                : 'Employment created successfully. Add funding allocations via the separate allocation API when ready.';

            $response = [
                'success' => true,
                'message' => $message,
                'data' => [
                    'employment' => $employmentWithRelations,
                    'funding_allocations' => $fundingAllocationsWithRelations,
                ],
                'summary' => [
                    'employment_created' => true,
                    'employment_id' => $employment->id,  // Include employment_id for frontend to use in allocation creation
                    'funding_allocations_created' => count($createdFundingAllocations),
                    'allocations_required' => ! $hasAllocations,  // Flag to indicate allocations should be added
                ],
            ];

            if (! empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            // Invalidate employment-related caches after successful creation
            $this->invalidateCacheAfterWrite($employment);

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment and funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/employments/upload',
        summary: 'Upload employment data from Excel file',
        description: 'Upload an Excel file containing employment records. The import is processed asynchronously in the background with chunk processing and duplicate checking. Existing employments will be updated, new ones will be created',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Excel file to upload (xlsx, xls, csv)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Employment data import started successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Import failed'),
        ]
    )]
    public function upload(Request $request)
    {
        try {
            // Validate file
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $file = $request->file('file');

        try {
            // Generate unique import ID
            $importId = 'employment_import_'.uniqid();

            // Get authenticated user
            $userId = auth()->id();

            // Queue the import
            $import = new \App\Imports\EmploymentsImport($importId, $userId);
            $import->queue($file);

            return response()->json([
                'success' => true,
                'message' => 'Employment import started successfully. You will receive a notification when the import is complete.',
                'data' => [
                    'import_id' => $importId,
                    'status' => 'processing',
                ],
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start employment import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/downloads/employment-template',
        summary: 'Download employment import template',
        description: 'Downloads an Excel template for bulk employment import with validation rules and sample data',
        operationId: 'downloadEmploymentTemplate',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Template file downloaded successfully'),
            new OA\Response(response: 500, description: 'Failed to generate template'),
        ]
    )]
    public function downloadEmploymentTemplate()
    {
        try {
            $export = new EmploymentTemplateExport;
            $tempFile = $export->generate();
            $filename = $export->getFilename();

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate employment template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employments/{id}',
        summary: 'Get employment record by ID',
        description: 'Returns a specific employment record by ID',
        operationId: 'getEmployment',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to return', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employment not found'),
        ]
    )]
    public function show($id)
    {
        try {
            $employment = Employment::with(['employee', 'department', 'position', 'site'])->findOrFail($id);

            // Add global benefit percentages (TaxSetting is single source of truth for PVD/Saving Fund)
            $employment->health_welfare_percentage = \App\Models\BenefitSetting::getActiveSetting('health_welfare_percentage');
            $employment->pvd_percentage = \App\Models\TaxSetting::getValue('PVD_FUND_RATE');
            $employment->saving_fund_percentage = \App\Models\TaxSetting::getValue('SAVING_FUND_RATE');

            return response()->json([
                'success' => true,
                'data' => $employment,
                'message' => 'Employment retrieved successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        }
    }

    #[OA\Post(
        path: '/employments/{id}/complete-probation',
        summary: 'Complete probation period manually',
        description: 'Manually triggers probation completion, updating funding allocations from probation_salary to pass_probation_salary',
        operationId: 'completeProbation',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Probation completed successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 400, description: 'Invalid request or probation already completed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function completeProbation($id)
    {
        try {
            // Find the employment record
            $employment = Employment::with('employeeFundingAllocations')->findOrFail($id);

            // Use ProbationTransitionService to handle the completion
            $probationService = app(\App\Services\ProbationTransitionService::class);
            $result = $probationService->handleProbationCompletion($employment, now());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Probation completed successfully and funding allocations updated',
                'data' => [
                    'employment' => $result['employment'],
                    'updated_allocations' => $result['employment']->employeeFundingAllocations,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete probation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/employments/{id}/probation-status',
        summary: 'Update probation status for an employment',
        description: 'Allows HR to manually mark probation as passed or failed with optional reason/notes',
        operationId: 'updateProbationStatus',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employment ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['passed', 'failed']),
                    new OA\Property(property: 'decision_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Probation status updated successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 422, description: 'Unable to update probation status'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function updateProbationStatus(UpdateProbationStatusRequest $request, int $id)
    {
        try {
            $employment = Employment::with(['activeAllocations', 'probationHistory'])->findOrFail($id);

            $validated = $request->validated();
            $action = $validated['action'];
            $decisionDate = isset($validated['decision_date'])
                ? Carbon::parse($validated['decision_date'])
                : now();

            $probationService = app(\App\Services\ProbationTransitionService::class);

            if ($action === 'passed') {
                $result = $probationService->handleProbationCompletion($employment, $decisionDate);
            } else {
                $result = $probationService->handleManualProbationFailure(
                    $employment,
                    $decisionDate,
                    $validated['reason'] ?? null,
                    $validated['notes'] ?? null
                );
            }

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Unable to update probation status',
                ], 422);
            }

            $employment->refresh();
            $historySummary = app(\App\Services\ProbationRecordService::class)->getHistory($employment);

            return response()->json([
                'success' => true,
                'message' => $action === 'passed'
                    ? 'Probation marked as passed successfully.'
                    : 'Probation marked as failed successfully.',
                'data' => [
                    'employment' => $employment,
                    'probation_history' => $historySummary,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update probation status: '.$e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/employments/calculate-allocation',
        summary: 'Calculate funding allocation amount in real-time',
        description: 'Calculates the allocated amount for a funding allocation based on FTE percentage and employment salary',
        operationId: 'calculateAllocationAmount',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employment_id', 'fte'],
                properties: [
                    new OA\Property(property: 'employment_id', type: 'integer', example: 123),
                    new OA\Property(property: 'fte', type: 'number', format: 'float', example: 60),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Calculation successful'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function calculateAllocationAmount(Request $request)
    {
        try {
            // ============================================================================
            // VALIDATION
            // ============================================================================
            $validator = Validator::make($request->all(), [
                'employment_id' => 'nullable|exists:employments,id',
                'fte' => 'required|numeric|min:0|max:100',
                'probation_salary' => 'nullable|numeric|min:0',
                'pass_probation_salary' => 'nullable|numeric|min:0',
                'pass_probation_date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'calculation_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $fte = $request->input('fte');
            $calculationDate = $request->input('calculation_date')
                ? Carbon::parse($request->input('calculation_date'))
                : Carbon::now();

            // ============================================================================
            // DETERMINE SALARY SOURCE (Existing Employment OR Raw Data)
            // ============================================================================
            $employmentId = $request->input('employment_id');
            $probationSalary = null;
            $passProbationSalary = null;
            $passProbationDate = null;
            $startDate = null;

            if ($employmentId) {
                // Use existing employment data
                $employment = Employment::findOrFail($employmentId);
                $probationSalary = $employment->probation_salary;
                $passProbationSalary = $employment->pass_probation_salary;
                $passProbationDate = $employment->pass_probation_date
                    ? Carbon::parse($employment->pass_probation_date)
                    : null;
                $startDate = $employment->start_date
                    ? Carbon::parse($employment->start_date)
                    : null;
            } else {
                // Use raw salary data from request (for new employment before saving)
                $probationSalary = $request->input('probation_salary');
                $passProbationSalary = $request->input('pass_probation_salary');
                $passProbationDate = $request->input('pass_probation_date')
                    ? Carbon::parse($request->input('pass_probation_date'))
                    : null;
                $startDate = $request->input('start_date')
                    ? Carbon::parse($request->input('start_date'))
                    : null;

                // Validate that we have at least one salary
                if (! $probationSalary && ! $passProbationSalary) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Either employment_id or salary data must be provided',
                        'errors' => ['salary' => ['Either probation_salary or pass_probation_salary is required']],
                    ], 422);
                }
            }

            // ============================================================================
            // DATE-BASED SALARY SELECTION LOGIC
            // ============================================================================
            $baseSalary = null;
            $salaryType = null;
            $salaryTypeLabel = null;
            $isProbationPeriod = false;

            // If pass_probation_date exists, use date comparison
            if ($passProbationDate) {
                if ($calculationDate->lt($passProbationDate)) {
                    // Before probation completion date
                    $isProbationPeriod = true;
                    if ($probationSalary) {
                        $baseSalary = $probationSalary;
                        $salaryType = 'probation_salary';
                        $salaryTypeLabel = 'Probation Salary';
                    } else {
                        // Fallback to pass_probation_salary if probation_salary not set
                        $baseSalary = $passProbationSalary;
                        $salaryType = 'pass_probation_salary';
                        $salaryTypeLabel = 'Pass Probation Salary (Fallback)';
                    }
                } else {
                    // After or on probation completion date
                    $isProbationPeriod = false;
                    $baseSalary = $passProbationSalary;
                    $salaryType = 'pass_probation_salary';
                    $salaryTypeLabel = 'Pass Probation Salary';
                }
            } else {
                // No pass_probation_date set - use probation_salary if available, else pass_probation_salary
                $isProbationPeriod = false; // Assume not in probation if date not set
                if ($probationSalary) {
                    $baseSalary = $probationSalary;
                    $salaryType = 'probation_salary';
                    $salaryTypeLabel = 'Probation Salary';
                } else {
                    $baseSalary = $passProbationSalary;
                    $salaryType = 'pass_probation_salary';
                    $salaryTypeLabel = 'Pass Probation Salary';
                }
            }

            // Final validation
            if (! $baseSalary || $baseSalary <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid salary found for calculation',
                    'errors' => ['salary' => ['A valid salary amount is required for allocation calculation']],
                ], 422);
            }

            // ============================================================================
            // CALCULATE ALLOCATION
            // ============================================================================
            $fteDecimal = $fte / 100;
            $allocatedAmount = round($baseSalary * $fteDecimal, 2);

            // Format currency (Thai Baht)
            $formattedBaseSalary = '฿'.number_format($baseSalary, 2);
            $formattedAmount = '฿'.number_format($allocatedAmount, 2);

            // Build calculation formula
            $calculationFormula = "{$formattedBaseSalary} × {$fte}% = {$formattedAmount}";

            // ============================================================================
            // RETURN RESPONSE
            // ============================================================================
            return response()->json([
                'success' => true,
                'message' => 'Allocation amount calculated successfully',
                'data' => [
                    'employment_id' => $employmentId,
                    'fte' => floatval($fte),
                    'fte_decimal' => $fteDecimal,
                    'base_salary' => floatval($baseSalary),
                    'salary_type' => $salaryType,
                    'salary_type_label' => $salaryTypeLabel,
                    'allocated_amount' => $allocatedAmount,
                    'formatted_amount' => $formattedAmount,
                    'formatted_base_salary' => $formattedBaseSalary,
                    'calculation_formula' => $calculationFormula,
                    'calculation_date' => $calculationDate->format('Y-m-d'),
                    'pass_probation_date' => $passProbationDate ? $passProbationDate->format('Y-m-d') : null,
                    'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                    'is_probation_period' => $isProbationPeriod,
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate allocation amount',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employments/{id}/funding-allocations',
        summary: 'Get funding allocations for an employment record',
        description: 'Returns all funding allocations associated with a specific employment record, including related grant and position slot information',
        operationId: 'getEmploymentFundingAllocations',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function fundingAllocations($id)
    {
        try {
            // Find the employment record with employee information
            $employment = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
            ])->findOrFail($id);

            // Get all funding allocations (active + inactive) with related data
            // Both active and inactive are shown so users can view history and re-activate
            // Closed allocations (permanently ended via trash) are excluded
            $fundingAllocations = EmployeeFundingAllocation::with([
                'grantItem:id,grant_id,grant_position,grant_salary,budgetline_code',
                'grantItem.grant:id,name,code',
                'employment:id,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title,department_id',
            ])
                ->where('employment_id', $id)
                ->whereIn('status', ['active', 'inactive'])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate summary statistics (only count active allocations for FTE)
            $activeAllocations = $fundingAllocations->where('status', 'active');
            $totalAllocations = $fundingAllocations->count();
            $totalFte = $activeAllocations->sum('fte');

            // Format the response data using Resource
            $formattedAllocations = EmployeeFundingAllocationResource::collection($fundingAllocations);

            return response()->json([
                'success' => true,
                'message' => 'Funding allocations retrieved successfully',
                'data' => [
                    'employment_id' => $employment->id,
                    'employee' => [
                        'id' => $employment->employee->id,
                        'staff_id' => $employment->employee->staff_id,
                        'name' => $employment->employee->first_name_en.' '.$employment->employee->last_name_en,
                        'organization' => $employment->employee->organization,
                    ],
                    'funding_allocations' => $formattedAllocations,
                    'summary' => [
                        'total_allocations' => $totalAllocations,
                        'total_fte' => $totalFte,
                        'total_fte_percentage' => ($totalFte * 100).'%',
                        'total_grants' => $fundingAllocations->count(),
                    ],
                ],
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment record not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/employments/{id}',
        operationId: 'updateEmploymentWithFundingAllocations',
        tags: ['Employments'],
        summary: 'Update employment record with optional funding allocations',
        description: 'Updates an employment record and optionally replaces funding allocations',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employment')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employment and allocations updated successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, $id)
    {
        try {
            // ============================================================================
            // SECTION 1: REQUEST VALIDATION
            // ============================================================================
            $validator = Validator::make($request->all(), [
                // Employment fields - all optional for updates
                'employee_id' => 'nullable|exists:employees,id',
                'position_id' => [
                    'nullable',
                    'integer',
                    'exists:positions,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->filled(['department_id', 'position_id'])) {
                            $position = \App\Models\Position::find($value);
                            if ($position && $position->department_id != $request->department_id) {
                                $fail('The selected position must belong to the selected department.');
                            }
                        }
                    },
                ],
                'department_id' => 'nullable|exists:departments,id',
                'section_department_id' => 'nullable|exists:section_departments,id',
                'site_id' => 'nullable|exists:sites,id',
                'pay_method' => 'nullable|string',
                'start_date' => 'nullable|date',
                'pass_probation_date' => 'nullable|date',
                'end_probation_date' => 'nullable|date|after_or_equal:start_date',
                'probation_salary' => 'nullable|numeric',
                'pass_probation_salary' => 'nullable|numeric',
                'health_welfare' => 'nullable|boolean',
                'pvd' => 'nullable|boolean',
                'saving_fund' => 'nullable|boolean',
                // NOTE: Benefit percentages are managed globally in benefit_settings table

                // Allocation fields - optional for updates
                'allocations' => 'nullable|array|min:1',
                'allocations.*.grant_item_id' => 'required_with:allocations|nullable|exists:grant_items,id',
                'allocations.*.fte' => 'required_with:allocations|numeric|min:0|max:100',
                'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // ============================================================================
            // SECTION 2: BUSINESS LOGIC VALIDATION
            // ============================================================================

            // Find the existing employment record
            $employment = Employment::findOrFail($id);

            // Store old start_date to check if it changed
            $oldStartDate = $employment->start_date;

            // If start_date is being changed and pass_probation_date is not explicitly provided,
            // recalculate pass_probation_date (3 months from new start_date)
            if (isset($validated['start_date']) && ! isset($validated['pass_probation_date'])) {
                $newStartDate = Carbon::parse($validated['start_date']);
                if (! $oldStartDate || ! $newStartDate->eq(Carbon::parse($oldStartDate))) {
                    $validated['pass_probation_date'] = $newStartDate->copy()->addMonths(3)->format('Y-m-d');
                }
            }

            // Additional validation for department_id and position_id relationship
            $departmentId = $validated['department_id'] ?? $employment->department_id;
            $positionId = $validated['position_id'] ?? $employment->position_id;

            if ($departmentId && $positionId) {
                $position = \App\Models\Position::find($positionId);
                if ($position && $position->department_id != $departmentId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The selected position must belong to the selected department.',
                        'errors' => ['position_id' => ['The selected position must belong to the selected department.']],
                    ], 422);
                }
            }

            // If allocations are provided, validate that the total effort equals exactly 100%
            $allocationsProvided = ! empty($validated['allocations']);
            if ($allocationsProvided) {
                $totalEffort = array_sum(array_column($validated['allocations'], 'fte'));
                if ($totalEffort != 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Total effort of all allocations must equal exactly 100%',
                        'current_total' => $totalEffort,
                    ], 422);
                }
            }

            // Validate date constraints if dates are being updated
            if (isset($validated['start_date']) && isset($validated['end_probation_date'])) {
                if ($validated['end_probation_date'] && $validated['start_date'] > $validated['end_probation_date']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'End probation date must be after or equal to start date',
                    ], 422);
                }
            }

            // ============================================================================
            // SECTION 3: DATABASE TRANSACTION START
            // ============================================================================
            DB::beginTransaction();

            $createdFundingAllocations = [];
            $removedAllocationsCount = 0;
            $errors = [];
            $warnings = [];

            // ============================================================================
            // SECTION 4: UPDATE EMPLOYMENT RECORD
            // ============================================================================

            // Store original values before update
            $original = $employment->getOriginal();

            $employmentData = collect($validated)->except('allocations')->toArray();
            if (! empty($employmentData)) {
                $employmentData['updated_by'] = $currentUser;
                $employment->update($employmentData);
            }

            // Refresh employment to get updated values
            $employment = $employment->fresh();

            // ============================================================================
            // SECTION 4A: HANDLE PROBATION STATUS CHANGES
            // ============================================================================
            $probationService = app(\App\Services\ProbationTransitionService::class);

            // Check for probation extension
            if (isset($validated['pass_probation_date']) &&
                isset($original['pass_probation_date']) &&
                $validated['pass_probation_date'] !== $original['pass_probation_date']) {

                $probationService->handleProbationExtension(
                    $employment,
                    $original['pass_probation_date'],
                    $validated['pass_probation_date']
                );
            }

            // Check for early termination (employment ended before probation completion)
            if (isset($validated['end_probation_date']) &&
                $employment->pass_probation_date &&
                Carbon::parse($validated['end_probation_date'])->lt($employment->pass_probation_date)) {

                $result = $probationService->handleEarlyTermination($employment);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Employment terminated during probation. Allocations marked as terminated.',
                    'data' => $employment->fresh(['activeAllocations', 'terminatedAllocations']),
                    'probation_result' => $result,
                ]);
            }

            // ============================================================================
            // SECTION 5: HANDLE FUNDING ALLOCATIONS (if provided)
            // ============================================================================
            if ($allocationsProvided) {
                // Remove existing funding allocations
                $existingAllocations = EmployeeFundingAllocation::where('employment_id', $employment->id)->get();
                $removedAllocationsCount = $existingAllocations->count();

                // Delete existing allocations
                EmployeeFundingAllocation::where('employment_id', $employment->id)->delete();

                // Process new allocations
                $today = Carbon::today();
                $allocationStartDate = isset($validated['start_date'])
                    ? Carbon::parse($validated['start_date'])
                    : ($employment->start_date instanceof Carbon ? $employment->start_date : Carbon::parse($employment->start_date));

                foreach ($validated['allocations'] as $index => $allocationData) {
                    try {
                        $allocationType = 'grant';

                        // ============================================================================
                        // SECTION 5A: HANDLE GRANT ALLOCATIONS
                        // ============================================================================
                        if ($allocationType === 'grant') {
                            // Validate grant item exists
                            $grantItem = GrantItem::find($allocationData['grant_item_id']);
                            if (! $grantItem) {
                                $errors[] = "Allocation #{$index}: Grant item not found";

                                continue;
                            }

                            // Check grant capacity constraints using date-based logic
                            if ($grantItem && $grantItem->grant_position_number > 0) {
                                $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
                                    ->where('employment_id', '!=', $employment->id) // Exclude current employment
                                    ->where('start_date', '<=', $today)
                                    ->where(function ($query) use ($today) {
                                        $query->whereNull('end_date')
                                            ->orWhere('end_date', '>=', $today);
                                    })
                                    ->count();

                                if ($currentAllocations >= $grantItem->grant_position_number) {
                                    $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";

                                    continue;
                                }
                            }

                            $fteDecimal = $allocationData['fte'] / 100;
                            $salaryContext = $this->employeeFundingAllocationService->deriveSalaryContext(
                                $employment,
                                $fteDecimal,
                                $allocationStartDate
                            );

                            // Create grant funding allocation
                            $fundingAllocation = EmployeeFundingAllocation::create([
                                'employee_id' => $employment->employee_id,
                                'employment_id' => $employment->id,
                                'grant_item_id' => $allocationData['grant_item_id'],
                                'grant_id' => null,
                                'fte' => $fteDecimal,
                                'allocated_amount' => $salaryContext['allocated_amount'],
                                'salary_type' => $salaryContext['salary_type'],
                                'start_date' => $allocationStartDate,
                                'end_date' => $allocationData['end_date'] ?? $validated['end_probation_date'] ?? $employment->end_probation_date,
                                'created_by' => $currentUser,
                                'updated_by' => $currentUser,
                            ]);

                            $createdFundingAllocations[] = $fundingAllocation;
                        }

                    } catch (\Exception $e) {
                        $errors[] = "Allocation #{$index}: ".$e->getMessage();
                    }
                }
            }

            // ============================================================================
            // SECTION 6: HANDLE ERRORS AND ROLLBACK IF NECESSARY
            // ============================================================================
            if ($allocationsProvided && empty($createdFundingAllocations) && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employment and allocations',
                    'errors' => $errors,
                ], 422);
            }

            // If some allocations failed but others succeeded, add warnings
            if (! empty($errors)) {
                $warnings = array_merge($warnings, $errors);
            }

            // ============================================================================
            // SECTION 7: COMMIT TRANSACTION AND PREPARE RESPONSE
            // ============================================================================
            DB::commit();

            // Load the updated records with their relationships
            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'department:id,name',
                'position:id,title,department_id',
                'site:id,name',
                'employeeFundingAllocations',
            ])->find($employment->id);

            $responseData = [
                'employment' => $employmentWithRelations,
            ];

            // Include allocation data if allocations were updated
            if ($allocationsProvided) {
                $fundingAllocationsWithRelations = EmployeeFundingAllocation::with([
                    'employee:id,staff_id,first_name_en,last_name_en',
                    'employment:id,start_date',
                    'grantItem.grant:id,name,code',
                ])->whereIn('id', collect($createdFundingAllocations)->pluck('id'))->get();

                $responseData['funding_allocations'] = $fundingAllocationsWithRelations;
            }

            // ============================================================================
            // SECTION 8: RETURN SUCCESS RESPONSE
            // ============================================================================
            $response = [
                'success' => true,
                'message' => $allocationsProvided
                    ? 'Employment and funding allocations updated successfully'
                    : 'Employment updated successfully',
                'data' => $responseData,
                'summary' => [
                    'employment_updated' => true,
                    'allocations_updated' => $allocationsProvided,
                    'old_allocations_removed' => $removedAllocationsCount,
                    'funding_allocations_created' => count($createdFundingAllocations),
                ],
            ];

            if (! empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            // Invalidate employment-related caches after successful update
            $this->invalidateCacheAfterWrite($employment);

            return response()->json($response, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment record not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment and funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Delete(
        path: '/employments/{id}',
        summary: 'Delete an employment record',
        description: 'Deletes an employment record by ID',
        operationId: 'deleteEmployment',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to delete', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employment deleted successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
        ]
    )]
    public function destroy($id)
    {
        try {
            $employment = Employment::findOrFail($id);
            $employment->delete();

            // Invalidate employment-related caches after successful deletion
            $this->invalidateCacheAfterWrite($employment);

            return response()->json([
                'success' => true,
                'message' => 'Employment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employment: '.$e->getMessage(),
            ], 500);
        }
    }

    #[OA\Get(
        path: '/employments/{id}/probation-history',
        summary: 'Get probation history for employment',
        description: 'Returns probation records history including extensions, passed/failed events',
        operationId: 'getProbationHistory',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employment ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Probation history retrieved successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function probationHistory($id)
    {
        try {
            $employment = Employment::with('probationHistory')->findOrFail($id);

            $service = app(\App\Services\ProbationRecordService::class);
            $history = $service->getHistory($employment);

            return response()->json([
                'success' => true,
                'message' => 'Probation history retrieved successfully',
                'data' => $history,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve probation history: '.$e->getMessage(),
            ], 500);
        }
    }
}
