<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterSubsidiaryAdvanceRequest;
use App\Http\Requests\UpdateInterSubsidiaryAdvanceRequest;
use App\Http\Resources\InterSubsidiaryAdvanceResource;
use App\Models\InterSubsidiaryAdvance;
use App\Models\Payroll;
use App\Models\Subsidiary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InterSubsidiaryAdvanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/inter-subsidiary-advances",
     *     summary="Get all inter-subsidiary advances with enhanced filtering and pagination",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="from_subsidiary",
     *         in="query",
     *         description="Filter by source subsidiary",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="SMRU")
     *     ),
     *
     *     @OA\Parameter(
     *         name="to_subsidiary",
     *         in="query",
     *         description="Filter by destination subsidiary",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="BHF")
     *     ),
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by settlement status",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"pending", "settled"}, example="pending")
     *     ),
     *
     *     @OA\Parameter(
     *         name="date_range",
     *         in="query",
     *         description="Filter by advance date range (YYYY-MM-DD,YYYY-MM-DD)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="2025-01-01,2025-01-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/InterSubsidiaryAdvanceResource")),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="summary", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'from_subsidiary' => 'string|nullable',
                'to_subsidiary' => 'string|nullable',
                'status' => 'string|nullable|in:pending,settled',
                'date_range' => 'string|nullable',
                'sort_by' => 'string|nullable|in:advance_date,amount,from_subsidiary,to_subsidiary',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $sortBy = $validated['sort_by'] ?? 'advance_date';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Build query with relationships
            $query = InterSubsidiaryAdvance::with([
                'viaGrant:id,code,name,subsidiary',
                'fromSubsidiary:code,name',
                'toSubsidiary:code,name',
            ]);

            // Apply filters
            if (! empty($validated['from_subsidiary'])) {
                $query->where('from_subsidiary', $validated['from_subsidiary']);
            }

            if (! empty($validated['to_subsidiary'])) {
                $query->where('to_subsidiary', $validated['to_subsidiary']);
            }

            if (! empty($validated['status'])) {
                if ($validated['status'] === 'pending') {
                    $query->whereNull('settlement_date');
                } else {
                    $query->whereNotNull('settlement_date');
                }
            }

            if (! empty($validated['date_range'])) {
                $dates = explode(',', $validated['date_range']);
                if (count($dates) === 2) {
                    $query->whereBetween('advance_date', [trim($dates[0]), trim($dates[1])]);
                }
            }

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            // Execute pagination
            $advances = $query->paginate($perPage);

            // Calculate summary statistics
            $summary = $this->calculateAdvanceSummary($advances->items());

            return response()->json([
                'success' => true,
                'data' => InterSubsidiaryAdvanceResource::collection($advances->items()),
                'pagination' => [
                    'current_page' => $advances->currentPage(),
                    'per_page' => $advances->perPage(),
                    'total' => $advances->total(),
                    'last_page' => $advances->lastPage(),
                    'from' => $advances->firstItem(),
                    'to' => $advances->lastItem(),
                    'has_more_pages' => $advances->hasMorePages(),
                ],
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inter-subsidiary advances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inter-subsidiary-advances",
     *     summary="Create a new inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoreInterSubsidiaryAdvanceRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance recorded."),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
     *         )
     *     )
     * )
     */
    public function store(StoreInterSubsidiaryAdvanceRequest $request)
    {
        $data = $request->validated() + [
            'created_by' => auth()->user()->username ?? null,
        ];
        $item = InterSubsidiaryAdvance::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Advance recorded.',
            'data' => new InterSubsidiaryAdvanceResource($item->load('viaGrant')),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Get a specific inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function show($id)
    {
        $item = InterSubsidiaryAdvance::with('viaGrant')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new InterSubsidiaryAdvanceResource($item),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Update an inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/UpdateInterSubsidiaryAdvanceRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance updated."),
     *             @OA\Property(property="data", ref="#/components/schemas/InterSubsidiaryAdvanceResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function update(UpdateInterSubsidiaryAdvanceRequest $request, $id)
    {
        $item = InterSubsidiaryAdvance::findOrFail($id);
        $item->update($request->validated() + [
            'updated_by' => auth()->user()->username ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Advance updated.',
            'data' => new InterSubsidiaryAdvanceResource($item->load('viaGrant')),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/inter-subsidiary-advances/{id}",
     *     summary="Delete an inter-subsidiary advance",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the advance",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance deleted.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $item = InterSubsidiaryAdvance::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Advance deleted.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/inter-subsidiary-advances/bulk-settle",
     *     summary="Bulk settle multiple advances",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"advance_ids", "settlement_date"},
     *
     *             @OA\Property(property="advance_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}),
     *             @OA\Property(property="settlement_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Bulk settlement for January payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advances settled successfully"),
     *             @OA\Property(property="settled_count", type="integer", example=3),
     *             @OA\Property(property="total_amount", type="number", example=125000.00)
     *         )
     *     )
     * )
     */
    public function bulkSettle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'advance_ids' => 'required|array|min:1',
            'advance_ids.*' => 'integer|exists:inter_subsidiary_advances,id',
            'settlement_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $advances = InterSubsidiaryAdvance::whereIn('id', $request->advance_ids)
                ->whereNull('settlement_date')
                ->get();

            if ($advances->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No unsettled advances found with the provided IDs',
                ], 404);
            }

            $totalAmount = 0;
            $settledCount = 0;

            foreach ($advances as $advance) {
                $advance->update([
                    'settlement_date' => $request->settlement_date,
                    'notes' => $request->notes ? $advance->notes.' | '.$request->notes : $advance->notes,
                    'updated_by' => auth()->user()->username ?? 'system',
                ]);

                $totalAmount += $advance->amount;
                $settledCount++;

                Log::info('Advance settled in bulk', [
                    'advance_id' => $advance->id,
                    'amount' => $advance->amount,
                    'settlement_date' => $request->settlement_date,
                    'settled_by' => auth()->user()->username ?? 'system',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Advances settled successfully',
                'settled_count' => $settledCount,
                'total_amount' => $totalAmount,
                'settlement_date' => $request->settlement_date,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to settle advances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/inter-subsidiary-advances/summary",
     *     summary="Get inter-subsidiary advances summary and statistics",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period for summary",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"current_month", "last_month", "current_year", "custom"}, example="current_month")
     *     ),
     *
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for custom period (required if period=custom)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for custom period (required if period=custom)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getSummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'string|nullable|in:current_month,last_month,current_year,custom',
                'start_date' => 'required_if:period,custom|date',
                'end_date' => 'required_if:period,custom|date|after_or_equal:start_date',
            ]);

            $period = $validated['period'] ?? 'current_month';

            // Determine date range based on period
            [$startDate, $endDate] = $this->getDateRange($period, $validated);

            // Get advances for the period
            $advances = InterSubsidiaryAdvance::with(['viaGrant:id,code,name'])
                ->whereBetween('advance_date', [$startDate, $endDate])
                ->get();

            // Calculate summary statistics
            $summary = [
                'period' => [
                    'type' => $period,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ],
                'totals' => [
                    'total_advances' => $advances->count(),
                    'total_amount' => $advances->sum('amount'),
                    'pending_advances' => $advances->whereNull('settlement_date')->count(),
                    'pending_amount' => $advances->whereNull('settlement_date')->sum('amount'),
                    'settled_advances' => $advances->whereNotNull('settlement_date')->count(),
                    'settled_amount' => $advances->whereNotNull('settlement_date')->sum('amount'),
                ],
                'by_subsidiary' => [
                    'from_subsidiaries' => $advances->groupBy('from_subsidiary')
                        ->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'total_amount' => $group->sum('amount'),
                                'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                            ];
                        }),
                    'to_subsidiaries' => $advances->groupBy('to_subsidiary')
                        ->map(function ($group) {
                            return [
                                'count' => $group->count(),
                                'total_amount' => $group->sum('amount'),
                                'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                            ];
                        }),
                ],
                'by_grant' => $advances->groupBy('viaGrant.code')
                    ->map(function ($group) {
                        return [
                            'grant_name' => $group->first()->viaGrant->name ?? 'Unknown',
                            'count' => $group->count(),
                            'total_amount' => $group->sum('amount'),
                            'pending_amount' => $group->whereNull('settlement_date')->sum('amount'),
                        ];
                    }),
                'aging_analysis' => $this->getAgingAnalysis($advances->whereNull('settlement_date')),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/inter-subsidiary-advances/auto-create",
     *     summary="Auto-create advances for payroll period",
     *     tags={"Inter-Subsidiary Advances"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"payroll_period_date"},
     *
     *             @OA\Property(property="payroll_period_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="dry_run", type="boolean", example=false, description="If true, only returns what would be created")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="created_count", type="integer"),
     *             @OA\Property(property="total_amount", type="number")
     *         )
     *     )
     * )
     */
    public function autoCreateAdvances(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payroll_period_date' => 'required|date',
            'dry_run' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payrollPeriodDate = Carbon::parse($request->payroll_period_date);
            $dryRun = $request->boolean('dry_run', false);

            // Find payrolls that need inter-subsidiary advances
            $payrolls = Payroll::with([
                'employment.employee',
                'employeeFundingAllocation.orgFunded.grant',
                'employeeFundingAllocation.positionSlot.grantItem.grant',
            ])
                ->whereDate('pay_period_date', $payrollPeriodDate)
                ->whereDoesntHave('interSubsidiaryAdvances')
                ->get();

            $advancesToCreate = [];
            $totalAmount = 0;

            foreach ($payrolls as $payroll) {
                $employee = $payroll->employment->employee;
                $allocation = $payroll->employeeFundingAllocation;

                // Determine funding subsidiary
                $fundingSubsidiary = null;
                $grant = null;

                if ($allocation->allocation_type === 'grant' && $allocation->positionSlot) {
                    $fundingSubsidiary = $allocation->positionSlot->grantItem->grant->subsidiary ?? null;
                    $grant = $allocation->positionSlot->grantItem->grant;
                } elseif ($allocation->allocation_type === 'org_funded' && $allocation->orgFunded) {
                    $fundingSubsidiary = $allocation->orgFunded->grant->subsidiary ?? null;
                    $grant = $allocation->orgFunded->grant;
                }

                // Check if advance is needed
                if ($fundingSubsidiary && $grant && $fundingSubsidiary !== $employee->subsidiary) {
                    $advanceData = [
                        'payroll_id' => $payroll->id,
                        'from_subsidiary' => $fundingSubsidiary,
                        'to_subsidiary' => $employee->subsidiary,
                        'via_grant_id' => $grant->id,
                        'amount' => $payroll->net_salary,
                        'advance_date' => $payrollPeriodDate,
                        'notes' => "Auto-generated for {$employee->staff_id} payroll",
                        'created_by' => auth()->user()->username ?? 'system',
                        'updated_by' => auth()->user()->username ?? 'system',
                    ];

                    $advancesToCreate[] = $advanceData;
                    $totalAmount += $payroll->net_salary;
                }
            }

            if (! $dryRun && ! empty($advancesToCreate)) {
                DB::beginTransaction();

                foreach ($advancesToCreate as $advanceData) {
                    InterSubsidiaryAdvance::create($advanceData);
                }

                DB::commit();

                Log::info('Auto-created inter-subsidiary advances', [
                    'payroll_period' => $payrollPeriodDate->format('Y-m-d'),
                    'count' => count($advancesToCreate),
                    'total_amount' => $totalAmount,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $dryRun
                    ? 'Dry run completed - advances would be created'
                    : 'Advances created successfully',
                'dry_run' => $dryRun,
                'created_count' => count($advancesToCreate),
                'total_amount' => $totalAmount,
                'payroll_period_date' => $payrollPeriodDate->format('Y-m-d'),
                'advances_preview' => $dryRun ? $advancesToCreate : null,
            ]);

        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-create advances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Helper methods

    private function calculateAdvanceSummary($advances): array
    {
        $collection = collect($advances);

        return [
            'total_count' => $collection->count(),
            'total_amount' => $collection->sum('amount'),
            'pending_count' => $collection->whereNull('settlement_date')->count(),
            'pending_amount' => $collection->whereNull('settlement_date')->sum('amount'),
            'settled_count' => $collection->whereNotNull('settlement_date')->count(),
            'settled_amount' => $collection->whereNotNull('settlement_date')->sum('amount'),
            'by_subsidiary' => $collection->groupBy('from_subsidiary')->map->count()->toArray(),
        ];
    }

    private function getDateRange(string $period, array $validated): array
    {
        switch ($period) {
            case 'current_month':
                return [now()->startOfMonth(), now()->endOfMonth()];

            case 'last_month':
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];

            case 'current_year':
                return [now()->startOfYear(), now()->endOfYear()];

            case 'custom':
                return [
                    Carbon::parse($validated['start_date']),
                    Carbon::parse($validated['end_date']),
                ];

            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    private function getAgingAnalysis($pendingAdvances): array
    {
        $today = now();
        $analysis = [
            '0-30_days' => ['count' => 0, 'amount' => 0],
            '31-60_days' => ['count' => 0, 'amount' => 0],
            '61-90_days' => ['count' => 0, 'amount' => 0],
            'over_90_days' => ['count' => 0, 'amount' => 0],
        ];

        foreach ($pendingAdvances as $advance) {
            $daysOld = $today->diffInDays(Carbon::parse($advance->advance_date));

            if ($daysOld <= 30) {
                $analysis['0-30_days']['count']++;
                $analysis['0-30_days']['amount'] += $advance->amount;
            } elseif ($daysOld <= 60) {
                $analysis['31-60_days']['count']++;
                $analysis['31-60_days']['amount'] += $advance->amount;
            } elseif ($daysOld <= 90) {
                $analysis['61-90_days']['count']++;
                $analysis['61-90_days']['amount'] += $advance->amount;
            } else {
                $analysis['over_90_days']['count']++;
                $analysis['over_90_days']['amount'] += $advance->amount;
            }
        }

        return $analysis;
    }
}
