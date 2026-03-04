<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LeaveCalculation\DateRangeRequest;
use App\Http\Requests\LeaveCalculation\YearStatisticsRequest;
use App\Services\LeaveCalculationService;
use Illuminate\Http\JsonResponse;

/**
 * Provides endpoints for calculating working days for leave requests.
 */
class LeaveCalculationController extends BaseApiController
{
    public function __construct(
        private readonly LeaveCalculationService $calculationService,
    ) {}

    /**
     * Calculate working days between two dates.
     */
    public function calculateWorkingDays(DateRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $workingDays = $this->calculationService->calculateWorkingDays(
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->successResponse([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'working_days' => $workingDays,
        ], 'Working days calculated successfully');
    }

    /**
     * Calculate working days with detailed breakdown.
     */
    public function calculateWorkingDaysDetailed(DateRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $details = $this->calculationService->calculateWorkingDaysDetailed(
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->successResponse($details, 'Detailed working days calculated successfully');
    }

    /**
     * Get non-working dates within a date range.
     */
    public function getNonWorkingDates(DateRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $nonWorkingDates = $this->calculationService->getNonWorkingDates(
            $validated['start_date'],
            $validated['end_date']
        );

        return $this->successResponse([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'non_working_dates' => $nonWorkingDates,
            'count' => count($nonWorkingDates),
        ], 'Non-working dates retrieved successfully');
    }

    /**
     * Get holiday statistics for a year.
     */
    public function getYearStatistics(YearStatisticsRequest $request, int $year): JsonResponse
    {
        $statistics = $this->calculationService->getYearStatistics($year);

        return $this->successResponse($statistics, 'Year statistics retrieved successfully');
    }
}
