<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeaveCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * LeaveCalculationController
 *
 * Provides endpoints for calculating working days for leave requests.
 * Excludes weekends (Saturday/Sunday) and organization holidays.
 *
 * @OA\Tag(
 *     name="Leave Calculation",
 *     description="API Endpoints for calculating leave working days"
 * )
 */
class LeaveCalculationController extends Controller
{
    public function __construct(
        protected LeaveCalculationService $calculationService
    ) {}

    /**
     * Calculate working days between two dates.
     *
     * @OA\Post(
     *     path="/leave-calculation/working-days",
     *     summary="Calculate working days for leave request",
     *     description="Calculates the number of working days excluding weekends and holidays.",
     *     tags={"Leave Calculation"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"start_date", "end_date"},
     *
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-22")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Working days calculated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="start_date", type="string", format="date"),
     *                 @OA\Property(property="end_date", type="string", format="date"),
     *                 @OA\Property(property="working_days", type="integer", example=5)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function calculateWorkingDays(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $workingDays = $this->calculationService->calculateWorkingDays(
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Working days calculated successfully',
                'data' => [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'working_days' => $workingDays,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error calculating working days: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate working days',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate working days with detailed breakdown.
     *
     * @OA\Post(
     *     path="/leave-calculation/working-days-detailed",
     *     summary="Calculate working days with detailed breakdown",
     *     description="Returns working days count plus detailed list of excluded dates with reasons.",
     *     tags={"Leave Calculation"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"start_date", "end_date"},
     *
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-22")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Detailed working days calculated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="start_date", type="string", format="date"),
     *                 @OA\Property(property="end_date", type="string", format="date"),
     *                 @OA\Property(property="working_days", type="integer"),
     *                 @OA\Property(property="total_calendar_days", type="integer"),
     *                 @OA\Property(property="weekend_days", type="integer"),
     *                 @OA\Property(property="holiday_days", type="integer"),
     *                 @OA\Property(property="excluded_dates", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="day_name", type="string"),
     *                         @OA\Property(property="reason", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(property="working_dates", type="array",
     *
     *                     @OA\Items(type="object",
     *
     *                         @OA\Property(property="date", type="string", format="date"),
     *                         @OA\Property(property="day_name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function calculateWorkingDaysDetailed(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $details = $this->calculationService->calculateWorkingDaysDetailed(
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Detailed working days calculated successfully',
                'data' => $details,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error calculating detailed working days: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate working days',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get non-working dates within a range.
     *
     * @OA\Get(
     *     path="/leave-calculation/non-working-dates",
     *     summary="Get non-working dates in a range",
     *     description="Returns all weekends and holidays within the specified date range.",
     *     tags={"Leave Calculation"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Non-working dates retrieved successfully"
     *     )
     * )
     */
    public function getNonWorkingDates(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $nonWorkingDates = $this->calculationService->getNonWorkingDates(
                $validated['start_date'],
                $validated['end_date']
            );

            return response()->json([
                'success' => true,
                'message' => 'Non-working dates retrieved successfully',
                'data' => [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'non_working_dates' => $nonWorkingDates,
                    'count' => count($nonWorkingDates),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving non-working dates: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve non-working dates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get holiday statistics for a year.
     *
     * @OA\Get(
     *     path="/leave-calculation/year-statistics/{year}",
     *     summary="Get holiday statistics for a year",
     *     tags={"Leave Calculation"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="year", in="path", required=true, @OA\Schema(type="integer", example=2025)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Year statistics retrieved successfully"
     *     )
     * )
     */
    public function getYearStatistics(int $year): JsonResponse
    {
        try {
            if ($year < 2000 || $year > 2100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Year must be between 2000 and 2100',
                ], 422);
            }

            $statistics = $this->calculationService->getYearStatistics($year);

            return response()->json([
                'success' => true,
                'message' => 'Year statistics retrieved successfully',
                'data' => $statistics,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving year statistics: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve year statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
