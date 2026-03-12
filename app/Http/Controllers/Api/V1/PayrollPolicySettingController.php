<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePayrollPolicySettingRequest;
use App\Http\Requests\UpdatePayrollPolicySettingRequest;
use App\Http\Resources\PayrollPolicySettingResource;
use App\Models\PayrollPolicySetting;
use App\Services\PayrollPolicySettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages payroll policy settings (one row per policy).
 */
class PayrollPolicySettingController extends BaseApiController
{
    public function __construct(
        private readonly PayrollPolicySettingService $payrollPolicySettingService,
    ) {}

    /**
     * List all payroll policy settings.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['filter_category', 'filter_is_active']);

        // Cast filter_is_active to boolean if present
        if (array_key_exists('filter_is_active', $filters) && $filters['filter_is_active'] !== null) {
            $filters['filter_is_active'] = filter_var($filters['filter_is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $settings = $this->payrollPolicySettingService->list($filters);

        return response()->json([
            'success' => true,
            'message' => 'Payroll policy settings retrieved successfully',
            'data' => PayrollPolicySettingResource::collection($settings),
            'categories' => PayrollPolicySetting::getCategories(),
        ]);
    }

    /**
     * Get a specific payroll policy setting by ID.
     */
    public function show(string $id): JsonResponse
    {
        $policy = $this->payrollPolicySettingService->show((int) $id);

        return PayrollPolicySettingResource::make($policy)
            ->additional(['success' => true, 'message' => 'Payroll policy setting retrieved successfully'])
            ->response();
    }

    /**
     * Create a new payroll policy setting.
     */
    public function store(StorePayrollPolicySettingRequest $request): JsonResponse
    {
        $policy = $this->payrollPolicySettingService->store($request->validated());

        return PayrollPolicySettingResource::make($policy)
            ->additional(['success' => true, 'message' => 'Payroll policy setting created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing payroll policy setting.
     */
    public function update(UpdatePayrollPolicySettingRequest $request, string $id): JsonResponse
    {
        $policy = $this->payrollPolicySettingService->update((int) $id, $request->validated());

        return PayrollPolicySettingResource::make($policy)
            ->additional(['success' => true, 'message' => 'Payroll policy setting updated successfully'])
            ->response();
    }

    /**
     * Delete a payroll policy setting.
     */
    public function destroy(string $id): JsonResponse
    {
        $this->payrollPolicySettingService->destroy((int) $id);

        return $this->successResponse(null, 'Payroll policy setting deleted successfully');
    }
}
