<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FundingAllocationService
{
    public function __construct(
        private readonly EmployeeFundingAllocationService $allocationSalaryService
    ) {}

    /**
     * Allocate employee to funding sources with comprehensive validation
     */
    public function allocateEmployee(Employee $employee, Employment $employment, array $allocations): array
    {
        // Validate total effort equals 100%
        $this->validateTotalEffort($allocations);

        $createdAllocations = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($allocations as $index => $allocationData) {
                $result = $this->createAllocation($employee, $employment, $allocationData, $index);

                if (isset($result['error'])) {
                    $errors[] = $result['error'];

                    continue;
                }

                $createdAllocations[] = $result['allocation'];
            }

            if (! empty($errors)) {
                DB::rollBack();

                return ['success' => false, 'errors' => $errors];
            }

            DB::commit();

            return [
                'success' => true,
                'allocations' => $createdAllocations,
                'summary' => [
                    'total_allocations' => count($createdAllocations),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'errors' => ['System error: '.$e->getMessage()]];
        }
    }

    /**
     * Create individual allocation with validation
     */
    private function createAllocation(Employee $employee, Employment $employment, array $data, int $index): array
    {
        $allocationType = 'grant'; // All allocations now use grant items (including hub/org funds)
        $currentUser = Auth::user()->name ?? 'system';

        return $this->createGrantAllocation($employee, $employment, $data, $index, $currentUser);
    }

    /**
     * Create grant-based allocation
     */
    private function createGrantAllocation(Employee $employee, Employment $employment, array $data, int $index, string $currentUser): array
    {
        // Validate grant item exists
        $grantItem = GrantItem::find($data['grant_item_id']);
        if (! $grantItem) {
            return ['error' => "Allocation #{$index}: Grant item not found"];
        }

        // Check grant capacity constraints
        $capacityCheck = $this->validateGrantCapacity($grantItem, $employment->id);
        if (! $capacityCheck['valid']) {
            return ['error' => "Allocation #{$index}: ".$capacityCheck['message']];
        }

        $fteDecimal = $data['fte'] / 100;
        $effectiveDate = $employment->start_date instanceof Carbon
            ? $employment->start_date
            : Carbon::parse($employment->start_date);
        $salaryContext = $this->allocationSalaryService->deriveSalaryContext($employment, $fteDecimal, $effectiveDate);

        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => $data['grant_item_id'],
            'fte' => $fteDecimal,
            'allocation_type' => 'grant',
            'allocated_amount' => $salaryContext['allocated_amount'],
            'salary_type' => $salaryContext['salary_type'],
            'start_date' => $employment->start_date,
            'end_date' => $employment->end_date ?? null,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        return ['allocation' => $allocation];
    }

    /**
     * Validate grant capacity constraints
     */
    public function validateGrantCapacity(GrantItem $grantItem, ?int $excludeEmploymentId = null): array
    {
        if (! $grantItem || $grantItem->grant_position_number <= 0) {
            return ['valid' => true]; // No capacity constraints
        }

        $today = Carbon::today();
        $query = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
            ->where('allocation_type', 'grant')
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });

        if ($excludeEmploymentId) {
            $query->where('employment_id', '!=', $excludeEmploymentId);
        }

        $currentAllocations = $query->count();

        if ($currentAllocations >= $grantItem->grant_position_number) {
            return [
                'valid' => false,
                'message' => "Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}",
            ];
        }

        return [
            'valid' => true,
            'available_slots' => $grantItem->grant_position_number - $currentAllocations,
            'total_slots' => $grantItem->grant_position_number,
        ];
    }

    /**
     * Calculate available slots for a grant
     */
    public function calculateAvailableSlots(Grant $grant): array
    {
        $grantItems = $grant->grantItems()->get();
        $summary = [];

        foreach ($grantItems as $grantItem) {
            $totalSlots = $grantItem->grant_position_number ?? 0;

            $capacityCheck = $this->validateGrantCapacity($grantItem);
            $allocatedSlots = ($capacityCheck['total_slots'] ?? 0) - ($capacityCheck['available_slots'] ?? 0);

            $summary[] = [
                'grant_item_id' => $grantItem->id,
                'position' => $grantItem->grant_position,
                'total_slots' => $totalSlots,
                'allocated_slots' => $allocatedSlots,
                'available_slots' => max(0, $totalSlots - $allocatedSlots),
                'utilization_percentage' => $totalSlots > 0 ? round(($allocatedSlots / $totalSlots) * 100, 2) : 0,
            ];
        }

        return $summary;
    }

    /**
     * Validate total effort equals 100%
     */
    private function validateTotalEffort(array $allocations): void
    {
        $totalEffort = array_sum(array_column($allocations, 'fte'));
        if ($totalEffort != 100) {
            throw new \InvalidArgumentException("Total effort of all allocations must equal exactly 100%. Current total: {$totalEffort}%");
        }
    }

    /**
     * Update existing allocations
     */
    public function updateAllocations(Employment $employment, array $newAllocations): array
    {
        $this->validateTotalEffort($newAllocations);

        DB::beginTransaction();

        try {
            // Delete existing allocations
            EmployeeFundingAllocation::where('employment_id', $employment->id)->delete();

            // Create new allocations
            $result = $this->allocateEmployee($employment->employee, $employment, $newAllocations);

            if (! $result['success']) {
                DB::rollBack();

                return $result;
            }

            DB::commit();

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'errors' => ['Update failed: '.$e->getMessage()]];
        }
    }

    /**
     * Get allocation summary for an employee
     */
    public function getAllocationSummary(Employee $employee): array
    {
        $allocations = $employee->employeeFundingAllocations()
            ->with(['grantItem.grant', 'employment'])
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->get();

        $summary = [
            'total_allocations' => $allocations->count(),
            'total_effort' => $allocations->sum('fte'),
            'funding_sources' => [],
            'by_type' => $allocations->groupBy('allocation_type')->map->count()->toArray(),
        ];

        foreach ($allocations as $allocation) {
            $fundingSource = [
                'id' => $allocation->id,
                'type' => 'grant',
                'effort_percentage' => $allocation->fte * 100,
                'allocated_amount' => $allocation->allocated_amount,
            ];

            if ($allocation->grantItem) {
                $fundingSource['grant'] = $allocation->grantItem->grant->name ?? 'Unknown Grant';
                $fundingSource['position'] = $allocation->grantItem->grant_position ?? 'Unknown Position';
            }

            $summary['funding_sources'][] = $fundingSource;
        }

        return $summary;
    }

    /**
     * Validate employment date overlaps
     */
    public function validateEmploymentDates(Employee $employee, Carbon $startDate, ?Carbon $endDate = null, ?int $excludeEmploymentId = null): array
    {
        $query = $employee->employments();

        if ($excludeEmploymentId) {
            $query->where('id', '!=', $excludeEmploymentId);
        }

        $existingEmployments = $query->get();

        foreach ($existingEmployments as $employment) {
            $existingStart = Carbon::parse($employment->start_date);
            $existingEnd = $employment->end_date ? Carbon::parse($employment->end_date) : null;

            // Check for overlap
            $hasOverlap = false;

            if ($endDate) {
                // New employment has end date
                if ($existingEnd) {
                    // Both have end dates
                    $hasOverlap = $startDate->lte($existingEnd) && $endDate->gte($existingStart);
                } else {
                    // Existing is ongoing
                    $hasOverlap = $startDate->lte($existingStart) || $endDate->gte($existingStart);
                }
            } else {
                // New employment is ongoing
                if ($existingEnd) {
                    // Existing has end date
                    $hasOverlap = $startDate->lte($existingEnd);
                } else {
                    // Both are ongoing - definitely overlap
                    $hasOverlap = true;
                }
            }

            if ($hasOverlap) {
                return [
                    'valid' => false,
                    'message' => "Employment dates overlap with existing employment (ID: {$employment->id}) from {$existingStart->format('Y-m-d')} to ".
                                ($existingEnd ? $existingEnd->format('Y-m-d') : 'ongoing'),
                ];
            }
        }

        return ['valid' => true];
    }
}
