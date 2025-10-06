<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonnelActionRequest;
use App\Models\PersonnelAction;
use App\Services\PersonnelActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Personnel Actions",
 *     description="Personnel action management endpoints"
 * )
 */
class PersonnelActionController extends Controller
{
    public function __construct(
        private PersonnelActionService $personnelActionService
    ) {}

    /**
     * @OA\Get(
     *     path="/personnel-actions",
     *     summary="Get personnel actions list",
     *     tags={"Personnel Actions"},
     *     security={{"sanctum":{}}}, *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="dept_head_approved",
     *         in="query",
     *         description="Filter by department head approval status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="coo_approved",
     *         in="query",
     *         description="Filter by COO approval status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="hr_approved",
     *         in="query",
     *         description="Filter by HR approval status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="accountant_approved",
     *         in="query",
     *         description="Filter by accountant approval status",
     *         required=false,
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="action_type",
     *         in="query",
     *         description="Filter by action type",
     *         required=false,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="employment_id",
     *         in="query",
     *         description="Filter by employment ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Personnel actions retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $actions = PersonnelAction::with([
            'employment.employee',
            'creator',
            'currentDepartment',
            'currentPosition',
            'currentWorkLocation',
            'newDepartment',
            'newPosition',
            'newWorkLocation',
        ])
            ->when($request->dept_head_approved !== null, fn ($q) => $q->where('dept_head_approved', (bool) $request->dept_head_approved))
            ->when($request->coo_approved !== null, fn ($q) => $q->where('coo_approved', (bool) $request->coo_approved))
            ->when($request->hr_approved !== null, fn ($q) => $q->where('hr_approved', (bool) $request->hr_approved))
            ->when($request->accountant_approved !== null, fn ($q) => $q->where('accountant_approved', (bool) $request->accountant_approved))
            ->when($request->action_type, fn ($q) => $q->where('action_type', $request->action_type))
            ->when($request->employment_id, fn ($q) => $q->where('employment_id', $request->employment_id))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/personnel-actions",
     *     summary="Create a new personnel action from SMRU-SF038 form",
     *     tags={"Personnel Actions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employment_id", "effective_date", "action_type"},
     *
     *             @OA\Property(property="employment_id", type="integer", description="Employment ID", example=15),
     *             @OA\Property(property="effective_date", type="string", format="date", description="Effective date", example="2025-11-01"),
     *             @OA\Property(property="action_type", type="string", description="Action type (appointment, fiscal_increment, title_change, voluntary_separation, position_change, transfer)", example="position_change"),
     *             @OA\Property(property="action_subtype", type="string", description="Action subtype", example="promotion"),
     *             @OA\Property(property="is_transfer", type="boolean", description="Is transfer action", example=false),
     *             @OA\Property(property="transfer_type", type="string", description="Transfer type (internal_department, site_to_site, attachment_position)", example="internal_department"),
     *             @OA\Property(property="new_department_id", type="integer", description="New department ID (from Section 3: New Information - Department)", example=5),
     *             @OA\Property(property="new_position_id", type="integer", description="New position ID (from Section 3: New Information - Position)", example=42),
     *             @OA\Property(property="new_work_location_id", type="integer", description="New work location ID (from Section 3: New Information - Location)", example=3),
     *             @OA\Property(property="new_salary", type="number", format="float", description="New salary (from Section 3: New Information - Salary)", example=65000.00),
     *             @OA\Property(property="new_work_schedule", type="string", description="New work schedule (from Section 3: New Information)", example="Monday-Friday 9AM-5PM"),
     *             @OA\Property(property="new_report_to", type="string", description="New report to (from Section 3: New Information)", example="John Doe"),
     *             @OA\Property(property="new_pay_plan", type="string", description="New pay plan (from Section 3: New Information)", example="Plan A"),
     *             @OA\Property(property="new_phone_ext", type="string", description="New phone extension (from Section 3: New Information)", example="1234"),
     *             @OA\Property(property="new_email", type="string", format="email", description="New email (from Section 3: New Information)", example="employee@smru.ac.th"),
     *             @OA\Property(property="comments", type="string", description="Comments (from Section 4: Comments/Details)", example="Annual performance promotion"),
     *             @OA\Property(property="change_details", type="string", description="Details of change (from Section 4)", example="Promoted based on excellent performance"),
     *             @OA\Property(property="dept_head_approved", type="boolean", description="Department Head approval (from Approved By section)", example=false),
     *             @OA\Property(property="coo_approved", type="boolean", description="COO of SMRU approval (from Approved By section)", example=false),
     *             @OA\Property(property="hr_approved", type="boolean", description="Human Resources Manager approval (from Approved By section)", example=false),
     *             @OA\Property(property="accountant_approved", type="boolean", description="Accountant Manager approval (from Approved By section)", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Personnel action created successfully"
     *     )
     * )
     */
    public function store(PersonnelActionRequest $request): JsonResponse
    {
        $personnelAction = $this->personnelActionService->createPersonnelAction(
            array_merge($request->validated(), [
                'created_by' => auth()->id(),
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Personnel action created successfully',
            'data' => $personnelAction->load([
                'employment.employee',
                'creator',
                'currentDepartment',
                'currentPosition',
                'currentWorkLocation',
                'newDepartment',
                'newPosition',
                'newWorkLocation',
            ]),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/personnel-actions/{personnelAction}",
     *     summary="Get personnel action details",
     *     tags={"Personnel Actions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="personnelAction",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Personnel action details retrieved successfully"
     *     )
     * )
     */
    public function show(PersonnelAction $personnelAction): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $personnelAction->load([
                'employment.employee',
                'creator',
                'currentDepartment',
                'currentPosition',
                'currentWorkLocation',
                'newDepartment',
                'newPosition',
                'newWorkLocation',
            ]),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/personnel-actions/{personnelAction}",
     *     summary="Update a personnel action from SMRU-SF038 form",
     *     tags={"Personnel Actions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="personnelAction",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employment_id", type="integer", description="Employment ID"),
     *             @OA\Property(property="effective_date", type="string", format="date", description="Effective date"),
     *             @OA\Property(property="action_type", type="string", description="Action type"),
     *             @OA\Property(property="action_subtype", type="string", description="Action subtype"),
     *             @OA\Property(property="is_transfer", type="boolean", description="Is transfer action"),
     *             @OA\Property(property="transfer_type", type="string", description="Transfer type"),
     *             @OA\Property(property="new_department_id", type="integer", description="New department ID"),
     *             @OA\Property(property="new_position_id", type="integer", description="New position ID"),
     *             @OA\Property(property="new_work_location_id", type="integer", description="New work location ID"),
     *             @OA\Property(property="new_salary", type="number", format="float", description="New salary"),
     *             @OA\Property(property="new_work_schedule", type="string", description="New work schedule"),
     *             @OA\Property(property="new_report_to", type="string", description="New report to"),
     *             @OA\Property(property="new_pay_plan", type="string", description="New pay plan"),
     *             @OA\Property(property="new_phone_ext", type="string", description="New phone extension"),
     *             @OA\Property(property="new_email", type="string", format="email", description="New email"),
     *             @OA\Property(property="comments", type="string", description="Comments"),
     *             @OA\Property(property="change_details", type="string", description="Details of change"),
     *             @OA\Property(property="dept_head_approved", type="boolean", description="Department head approval"),
     *             @OA\Property(property="coo_approved", type="boolean", description="COO approval"),
     *             @OA\Property(property="hr_approved", type="boolean", description="HR approval"),
     *             @OA\Property(property="accountant_approved", type="boolean", description="Accountant approval")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Personnel action updated successfully"
     *     )
     * )
     */
    public function update(PersonnelActionRequest $request, PersonnelAction $personnelAction): JsonResponse
    {
        $personnelAction->update(array_merge($request->validated(), [
            'updated_by' => auth()->id(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Personnel action updated successfully',
            'data' => $personnelAction->fresh()->load([
                'employment.employee',
                'creator',
                'currentDepartment',
                'currentPosition',
                'currentWorkLocation',
                'newDepartment',
                'newPosition',
                'newWorkLocation',
            ]),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/personnel-actions/{personnelAction}/approve",
     *     summary="Update approval status for a personnel action",
     *     tags={"Personnel Actions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="personnelAction",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"approval_type", "approved"},
     *
     *             @OA\Property(
     *                 property="approval_type",
     *                 type="string",
     *                 enum={"dept_head", "coo", "hr", "accountant"},
     *                 description="Type of approval to update"
     *             ),
     *             @OA\Property(
     *                 property="approved",
     *                 type="boolean",
     *                 description="Approval status (true = approved, false = rejected)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Approval status updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function approve(Request $request, PersonnelAction $personnelAction): JsonResponse
    {
        $validated = $request->validate([
            'approval_type' => 'required|string|in:dept_head,coo,hr,accountant',
            'approved' => 'required|boolean',
        ]);

        try {
            $this->personnelActionService->updateApproval(
                $personnelAction,
                $validated['approval_type'],
                $validated['approved']
            );

            $personnelAction = $personnelAction->fresh()->load([
                'employment.employee',
                'creator',
                'currentDepartment',
                'currentPosition',
                'currentWorkLocation',
                'newDepartment',
                'newPosition',
                'newWorkLocation',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Approval status updated successfully',
                'data' => $personnelAction,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update approval status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/personnel-actions/constants",
     *     summary="Get personnel action constants",
     *     tags={"Personnel Actions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Personnel action constants retrieved successfully"
     *     )
     * )
     */
    public function getConstants(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'action_types' => PersonnelAction::ACTION_TYPES,
                'action_subtypes' => PersonnelAction::ACTION_SUBTYPES,
                'transfer_types' => PersonnelAction::TRANSFER_TYPES,
                'statuses' => PersonnelAction::STATUSES,
            ],
        ]);
    }
}
